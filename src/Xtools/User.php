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

    /** @var string Expiry of the current block of the user */
    protected $blockExpiry;

    /**
     * Create a new User given a username.
     * @param string $username
     */
    public function __construct($username)
    {
        $this->username = ucfirst(trim($username));
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
     * Get a md5 hash of the username to be used as a cache key.
     * This ensures the cache key does not contain reserved characters.
     * You could also use the ID, but that may require an unnecessary DB query.
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
        return filter_var($this->username, FILTER_VALIDATE_IP);
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
