<?php
/**
 * This file contains only the AdminStatsRepository class.
 */

declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Project;
use Mediawiki\Api\SimpleRequest;

/**
 * AdminStatsRepository is responsible for retrieving data from the database
 * about users with administrative rights on a given wiki.
 * @codeCoverageIgnore
 */
class AdminStatsRepository extends Repository
{
    protected const ADMIN_PERMISSIONS = [
        'block',
        'delete',
        'deletelogentry',
        'deleterevision',
        'editinterface',
        'globalblock',
        'hideuser',
        'protect',
        'suppressionlog',
        'suppressrevision',
        'undelete',
        'userrights',
    ];

    /**
     * Core function to get statistics about users who have admin-like permissions.
     * @param Project $project
     * @param string $start SQL-ready format.
     * @param string $end
     * @return string[][] with keys 'user_name', 'user_id', 'delete', 'restore', 'block',
     *   'unblock', 'protect', 'unprotect', 'rights', 'import', and 'total'.
     */
    public function getStats(Project $project, string $start, string $end): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'adminstats');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $userTable = $project->getTableName('user');
        $loggingTable = $project->getTableName('logging', 'userindex');

        $sql = "SELECT user_name, user_id,
                    SUM(IF( (log_type = 'delete'  AND log_action != 'restore'),1,0)) AS 'delete',
                    SUM(IF( (log_type = 'delete'  AND log_action  = 'restore'),1,0)) AS 'restore',
                    SUM(IF( (log_type = 'block'   AND log_action != 'unblock'),1,0)) AS 'block',
                    SUM(IF( (log_type = 'block'   AND log_action  = 'unblock'),1,0)) AS 'unblock',
                    SUM(IF( (log_type = 'protect' AND log_action != 'unprotect'),1,0)) AS 'protect',
                    SUM(IF( (log_type = 'protect' AND log_action  = 'unprotect'),1,0)) AS 'unprotect',
                    SUM(IF( log_type  = 'rights',1,0)) AS 'rights',
                    SUM(IF( log_type  = 'import',1,0)) AS 'import',
                    SUM(IF( log_type != '',1,0)) AS 'total'
                FROM $loggingTable
                JOIN $userTable ON user_id = log_user
                WHERE log_timestamp > '$start' AND log_timestamp <= '$end'
                  AND log_type IS NOT NULL
                  AND log_action IS NOT NULL
                  AND log_type IN ('block', 'delete', 'protect', 'import', 'rights')
                  AND log_action NOT IN ('delete_redir', 'autopromote', 'move_prot')
                GROUP BY user_name
                HAVING `total` > 0
                ORDER BY 'total' DESC";

        $results = $this->executeProjectsQuery($sql)->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $results);
    }

    /**
     * Get all user groups with admin-like permissions.
     * @param Project $project
     * @return array Each entry contains 'name' (user group) and 'rights' (the permissions).
     */
    public function getAdminGroups(Project $project): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'admingroups');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $userGroups = [];

        $params = [
            'meta' => 'siteinfo',
            'siprop' => 'usergroups',
        ];
        $api = $this->getMediawikiApi($project);
        $query = new SimpleRequest('query', $params);
        $res = $api->getRequest($query);

        // If there isn't a usergroups hash than let it error out...
        // Something else must have gone horribly wrong.
        foreach ($res['query']['usergroups'] as $userGroup) {
            // If they are able to add and remove user groups,
            // we'll treat them as having the 'userrights' permission.
            if (isset($userGroup['add']) || isset($userGroup['remove'])) {
                array_push($userGroup['rights'], 'userrights');
            }

            if (count(array_intersect($userGroup['rights'], self::ADMIN_PERMISSIONS)) > 0) {
                $userGroups[] = $userGroup['name'];
            }
        }

        // Cache for a week and return.
        return $this->setCache($cacheKey, $userGroups, 'P7D');
    }
}
