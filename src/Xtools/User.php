<?php

namespace Xtools;

/**
 * A User is a wiki user who has the same username across all projects in an Xtools installation.
 */
class User extends Model
{

    /** @var int */
    protected $id;

    /** @var string */
    protected $username;

    /**
     * Create a new User given a username.
     * @param string $username
     */
    public function __construct($username)
    {
        $this->username = ucfirst($username);
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
}
