<?php
/**
 * This file contains only the SimpleEditCounter class.
 */

namespace Xtools;

use Symfony\Component\DependencyInjection\Container;

/**
 * A SimpleEditCounter provides basic edit count stats about a user.
 * This class is too 'simple' to bother with tests, and we'd need to move
 * the single query to a repo class just so we could mock it.
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
     */
    public function __construct(Container $container, Project $project, User $user)
    {
        $this->container = $container;
        $this->project = $project;
        $this->user = $user;
    }

    /**
     * Fetch the data from the database and API,
     * then set class properties with the values.
     */
    public function prepareData()
    {
        $results = $this->fetchData();

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
     * Run the query against the database.
     * @return string[]
     */
    private function fetchData()
    {
        $userTable = $this->project->getTableName('user');
        $archiveTable = $this->project->getTableName('archive');
        $revisionTable = $this->project->getTableName('revision');
        $userGroupsTable = $this->project->getTableName('user_groups');

        /** @var Connection $conn */
        $conn = $this->container->get('doctrine')->getManager('replicas')->getConnection();

        // Prepare the query and execute
        $resultQuery = $conn->prepare("
            SELECT 'id' AS source, user_id as value
                FROM $userTable
                WHERE user_name = :username
            UNION
            SELECT 'arch' AS source, COUNT(*) AS value
                FROM $archiveTable
                WHERE ar_user_text = :username
            UNION
            SELECT 'rev' AS source, COUNT(*) AS value
                FROM $revisionTable
                WHERE rev_user_text = :username
            UNION
            SELECT 'groups' AS source, ug_group AS value
                FROM $userGroupsTable
                JOIN $userTable ON user_id = ug_user
                WHERE user_name = :username
        ");

        $username = $this->user->getUsername();
        $resultQuery->bindParam('username', $username);
        $resultQuery->execute();

        // Fetch the result data
        return $resultQuery->fetchAll();
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
