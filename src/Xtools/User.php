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
     */
    public function getGroups(Project $project)
    {
    }

    /**
     * Get the full URL to Special:UserRights for this user on the given project.
     * @param Project $project
     * @return string
     */
    public function userRightsUrl(Project $project)
    {
        return $project->getUrl() . $project->getScriptPath() . "?title=Special:UserRights&user=" .
               $this->getUsername();
    }
}
