<?php
/**
 * This file contains only the User class.
 */

namespace Xtools;

use Exception;
use DateTime;

/**
 * A User is a wiki user who has the same username across all projects in an XTools installation.
 */
class User extends Model
{

    /** @var int The user's ID. */
    protected $id;

    /** @var string The user's username. */
    protected $username;

    /** @var DateTime|bool Expiry of the current block of the user. */
    protected $blockExpiry;

    /** @var int Quick cache of edit counts, keyed by project domain. */
    protected $editCounts = [];

    /**
     * Create a new User given a username.
     * @param string $username
     */
    public function __construct($username)
    {
        $this->username = ucfirst(str_replace('_', ' ', trim($username)));
    }

    /**
     * Get the username.
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Unique identifier for this User, to be used in cache keys.
     * Use of md5 ensures the cache key does not contain reserved characters.
     * You could also use the ID, but that may require an unnecessary DB query.
     * @see Repository::getCacheKey()
     * @return string
     */
    public function getCacheKey()
    {
        return md5($this->username);
    }

    /**
     * Get the user's ID on the given project.
     * @param Project $project
     * @return int
     */
    public function getId(Project $project)
    {
        return $this->getRepository()->getId($project->getDatabaseName(), $this->getUsername());
    }

    /**
     * Get the user's registration date on the given project.
     * @param Project $project
     * @return DateTime|false False if no registration date was found.
     */
    public function getRegistrationDate(Project $project)
    {
        $registrationDate = $this->getRepository()->getRegistrationDate(
            $project->getDatabaseName(),
            $this->getUsername()
        );

        return DateTime::createFromFormat('YmdHis', $registrationDate);
    }

    /**
     * Get the user's (system) edit count.
     * @param Project $project
     * @return int
     */
    public function getEditCount(Project $project)
    {
        $domain = $project->getDomain();
        if (isset($this->editCounts[$domain])) {
            return $this->editCounts[$domain];
        }

        $this->editCounts[$domain] = (int) $this->getRepository()->getEditCount(
            $project->getDatabaseName(),
            $this->getUsername()
        );

        return $this->editCounts[$domain];
    }

    /**
     * Maximum number of edits to process, based on configuration.
     * @return int
     */
    public function maxEdits()
    {
        return $this->getRepository()->maxEdits();
    }

    /**
     * Get a list of this user's groups on the given project.
     * @param Project $project The project.
     * @return string[]
     */
    public function getGroups(Project $project)
    {
        $groupsData = $this->getRepository()->getGroups($project, $this->getUsername());
        $groups = preg_grep('/\*/', $groupsData, PREG_GREP_INVERT);
        sort($groups);
        return $groups;
    }

    /**
     * Get a list of this user's groups on all projects.
     * @param Project $project A project to query; if not provided, the default will be used.
     */
    public function getGlobalGroups(Project $project = null)
    {
        return $this->getRepository()->getGlobalGroups($this->getUsername(), $project);
    }

    /**
     * Does this user exist on the given project.
     * @param Project $project
     * @return bool
     */
    public function existsOnProject(Project $project)
    {
        $id = $this->getId($project);
        return $id > 0;
    }

    /**
     * Is this user an Administrator on the given project?
     * @param Project $project The project.
     * @return bool
     */
    public function isAdmin(Project $project)
    {
        return (false !== array_search('sysop', $this->getGroups($project)));
    }

    /**
     * Is this user an anonymous user (IP)?
     * @return bool
     */
    public function isAnon()
    {
        return (bool) filter_var($this->username, FILTER_VALIDATE_IP);
    }

    /**
     * Get the expiry of the current block on the user
     * @param Project $project The project.
     * @return DateTime|bool Expiry as DateTime, true if indefinite,
     *                       or false if they are not blocked.
     */
    public function getBlockExpiry(Project $project)
    {
        if (isset($this->blockExpiry)) {
            return $this->blockExpiry;
        }

        $expiry = $this->getRepository()->getBlockExpiry(
            $project->getDatabaseName(),
            $this->getId($project)
        );

        if ($expiry === 'infinity') {
            $this->blockExpiry = true;
        } elseif ($expiry === false) {
            $this->blockExpiry = false;
        } else {
            $this->blockExpiry = new DateTime($expiry);
        }

        return $this->blockExpiry;
    }

    /**
     * Is this user currently blocked on the given project?
     * @param Project $project The project.
     * @return bool
     */
    public function isBlocked(Project $project)
    {
        return $this->getBlockExpiry($project) !== false;
    }

    /**
     * Does the user have more edits than maximum amount allowed for processing?
     * @param Project $project
     * @return bool
     */
    public function hasTooManyEdits(Project $project)
    {
        $editCount = $this->getEditCount($project);
        return $this->maxEdits() > 0 && $editCount > $this->maxEdits();
    }

    /**
     * Get edit count within given timeframe and namespace
     * @param Project $project
     * @param int|string $namespace Namespace ID or 'all' for all namespaces
     * @param string $start Start date in a format accepted by strtotime()
     * @param string $end End date in a format accepted by strtotime()
     * @return int
     */
    public function countEdits(Project $project, $namespace = 'all', $start = '', $end = '')
    {
        return (int) $this->getRepository()->countEdits($project, $this, $namespace, $start, $end);
    }

    /**
     * Is this user the same as the current XTools user?
     * @return bool
     */
    public function isCurrentlyLoggedIn()
    {
        try {
            $ident = $this->getRepository()->getXtoolsUserInfo();
        } catch (Exception $exception) {
            return false;
        }
        return isset($ident->username) && $ident->username === $this->getUsername();
    }
}
