<?php
/**
 * This file contains only the User class.
 */

declare(strict_types = 1);

namespace AppBundle\Model;

use DateTime;
use Exception;

/**
 * A User is a wiki user who has the same username across all projects in an XTools installation.
 */
class User extends Model
{
    /** @var string The user's username. */
    protected $username;

    /** @var int Quick cache of edit counts, keyed by project domain. */
    protected $editCounts = [];

    /**
     * Create a new User given a username.
     * @param string $username
     */
    public function __construct(string $username)
    {
        $this->username = ucfirst(str_replace('_', ' ', trim($username)));

        // IPv6 address are stored as uppercase in the database.
        if ($this->isAnon()) {
            $this->username = strtoupper($this->username);
        }
    }

    /**
     * Get the username.
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Unique identifier for this User, to be used in cache keys. Use of md5 ensures the cache key does not contain
     * reserved characters. You could also use the ID, but that may require an unnecessary DB query.
     * @see Repository::getCacheKey()
     * @return string
     */
    public function getCacheKey(): string
    {
        return md5($this->username);
    }

    /**
     * Get the user's ID on the given project.
     * @param Project $project
     * @return int|null
     */
    public function getId(Project $project): ?int
    {
        $ret = $this->getRepository()->getIdAndRegistration(
            $project->getDatabaseName(),
            $this->getUsername()
        );

        return $ret ? (int)$ret['userId'] : null;
    }

    /**
     * Get the user's actor ID on the given project.
     * @param Project $project
     * @return int
     */
    public function getActorId(Project $project): int
    {
        return (int)$this->getRepository()->getActorId(
            $project->getDatabaseName(),
            $this->getUsername()
        );
    }

    /**
     * Get the user's registration date on the given project.
     * @param Project $project
     * @return DateTime|null null if no registration date was found.
     */
    public function getRegistrationDate(Project $project): ?DateTime
    {
        $ret = $this->getRepository()->getIdAndRegistration(
            $project->getDatabaseName(),
            $this->getUsername()
        );

        return null !== $ret['regDate']
            ? DateTime::createFromFormat('YmdHis', $ret['regDate'])
            : null;
    }

    /**
     * Get a user's local user rights on the given Project.
     * @param Project $project
     * @return string[]
     */
    public function getUserRights(Project $project): array
    {
        return $this->getRepository()->getUserRights($project, $this);
    }

    /**
     * Get a list of this user's global rights.
     * @param Project|null $project A project to query; if not provided, the default will be used.
     * @return string[]
     */
    public function getGlobalUserRights(?Project $project = null): array
    {
        return $this->getRepository()->getGlobalUserRights($this->getUsername(), $project);
    }

    /**
     * Get the user's (system) edit count.
     * @param Project $project
     * @return int
     */
    public function getEditCount(Project $project): int
    {
        $domain = $project->getDomain();
        if (isset($this->editCounts[$domain])) {
            return $this->editCounts[$domain];
        }

        $this->editCounts[$domain] = (int)$this->getRepository()->getEditCount(
            $project->getDatabaseName(),
            $this->getUsername()
        );

        return $this->editCounts[$domain];
    }

    /**
     * Maximum number of edits to process, based on configuration.
     * @return int
     */
    public function maxEdits(): int
    {
        return $this->getRepository()->maxEdits();
    }

    /**
     * Does this user exist on the given project.
     * @param Project $project
     * @return bool
     */
    public function existsOnProject(Project $project): bool
    {
        return $this->getId($project) > 0;
    }

    /**
     * Is this user an Administrator on the given project?
     * @param Project $project The project.
     * @return bool
     */
    public function isAdmin(Project $project): bool
    {
        return false !== array_search('sysop', $this->getUserRights($project));
    }

    /**
     * Is this user an anonymous user (IP)?
     * @return bool
     */
    public function isAnon(): bool
    {
        return (bool)filter_var($this->username, FILTER_VALIDATE_IP);
    }

    /**
     * Get the expiry of the current block on the user
     * @param Project $project The project.
     * @return DateTime|bool Expiry as DateTime, true if indefinite, or false if they are not blocked.
     */
    public function getBlockExpiry(Project $project)
    {
        $expiry = $this->getRepository()->getBlockExpiry(
            $project->getDatabaseName(),
            $this->getUsername()
        );

        if ('infinity' === $expiry) {
            return true;
        } elseif (false === $expiry) {
            return false;
        } else {
            return new DateTime($expiry);
        }
    }

    /**
     * Is this user currently blocked on the given project?
     * @param Project $project The project.
     * @return bool
     */
    public function isBlocked(Project $project): bool
    {
        return false !== $this->getBlockExpiry($project);
    }

    /**
     * Does the user have more edits than maximum amount allowed for processing?
     * @param Project $project
     * @return bool
     */
    public function hasTooManyEdits(Project $project): bool
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
    public function countEdits(Project $project, $namespace = 'all', $start = '', $end = ''): int
    {
        return (int) $this->getRepository()->countEdits($project, $this, $namespace, $start, $end);
    }

    /**
     * Is this user the same as the current XTools user?
     * @return bool
     */
    public function isCurrentlyLoggedIn(): bool
    {
        try {
            $ident = $this->getRepository()->getXtoolsUserInfo();
        } catch (Exception $exception) {
            return false;
        }
        return isset($ident->username) && $ident->username === $this->getUsername();
    }
}
