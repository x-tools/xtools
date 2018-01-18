<?php
/**
 * This file contains only the SimpleEditCounter class.
 */

namespace Xtools;

use Symfony\Component\DependencyInjection\Container;

/**
 * A SimpleEditCounter provides basic edit count stats about a user.
 * This class is too 'simple' to bother with tests, we just get
 * the results of the query and return it.
 * @codeCoverageIgnore
 */
class SimpleEditCounter extends Model
{
    /** @var Container The DI container. */
    protected $container;

    /** @var Project The project. */
    protected $project;

    /** @var User The user. */
    protected $user;

    /** @var string|int Which namespace we are querying for. */
    protected $namespace;

    /** @var false|int Start date as Unix timestamp. */
    protected $start;

    /** @var false|int End date as Unix timestamp. */
    protected $end;

    /** @var array The Simple Edit Counter results. */
    protected $data = [
        'userId' => null,
        'deletedEditCount' => 0,
        'liveEditCount' => 0,
        'userGroups' => [],
        'globalUserGroups' => [],
    ];

    /**
     * Constructor for the SimpleEditCounter class.
     * @param Container $container The DI container.
     * @param Project   $project
     * @param User      $user
     * @param string    $namespace Namespace ID or 'all'.
     * @param false|int $start As Unix timestamp.
     * @param false|int $end As Unix timestamp.
     */
    public function __construct(
        Container $container,
        Project $project,
        User $user,
        $namespace = 'all',
        $start = false,
        $end = false
    ) {
        $this->container = $container;
        $this->project = $project;
        $this->user = $user;
        $this->namespace = $namespace == '' ? 0 : $namespace;
        $this->start = $start;
        $this->end = $end;
    }

    /**
     * Fetch the data from the database and API,
     * then set class properties with the values.
     */
    public function prepareData()
    {
        $results = $this->getRepository()->fetchData(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end
        );

        // Iterate over the results, putting them in the right variables
        foreach ($results as $row) {
            switch ($row['source']) {
                case 'id':
                    $this->data['userId'] = $row['value'];
                    break;
                case 'arch':
                    $this->data['deletedEditCount'] = $row['value'];
                    break;
                case 'rev':
                    $this->data['liveEditCount'] = $row['value'];
                    break;
                case 'groups':
                    $this->data['userGroups'][] = $row['value'];
                    break;
            }
        }

        if (!$this->container->getParameter('app.single_wiki')) {
            $this->data['globalUserGroups'] = $this->user->getGlobalGroups($this->project);
        }
    }

    /**
     * Get the namespace set on the class instance.
     * @return int|string Namespace ID or 'all' for all namespaces.
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Get date opening date range.
     * @return false|int
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Get date closing date range.
     * @return false|int
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * Has date range?
     * @return bool
     */
    public function hasDateRange()
    {
        return $this->start !== false || $this->end !== false;
    }

    /**
     * Get back all the data as a single associative array.
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the user's ID.
     * @return int
     */
    public function getUserId()
    {
        return $this->data['userId'];
    }

    /**
     * Get the number of deleted edits.
     * @return int
     */
    public function getDeletedEditCount()
    {
        return $this->data['deletedEditCount'];
    }

    /**
     * Get the number of live edits.
     * @return int
     */
    public function getLiveEditCount()
    {
        return $this->data['liveEditCount'];
    }

    /**
     * Get the total number of edits.
     * @return int
     */
    public function getTotalEditCount()
    {
        return $this->data['deletedEditCount'] + $this->data['liveEditCount'];
    }

    /**
     * Get the local user groups.
     * @return string[]
     */
    public function getUserGroups()
    {
        return $this->data['userGroups'];
    }

    /**
     * Get the global user groups.
     * @return string[]
     */
    public function getGlobalUserGroups()
    {
        return $this->data['globalUserGroups'];
    }
}
