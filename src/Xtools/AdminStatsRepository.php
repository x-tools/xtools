<?php
/**
 * This file contains only the AdminStatsRepository class.
 */

namespace Xtools;

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;

/**
 * AdminStatsRepository is responsible for retrieving data from the database
 * about users with administrative rights on a given wiki.
 * @codeCoverageIgnore
 */
class AdminStatsRepository extends Repository
{
    const ADMIN_PERMISSIONS = [
        'block',
        'delete',
        'deletedhistory',
        'deletedtext',
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
     * @param  Project $project
     * @param  string  $start SQL-ready format.
     * @param  string  $end
     * @return string[] with keys 'user_name', 'user_id', 'delete', 'restore', 'block',
     *   'unblock', 'protect', 'unprotect', 'rights', 'import', and 'total'.
     */
    public function getStats(Project $project, $start, $end)
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'adminstats');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $userTable = $project->getTableName('user');
        $loggingTable = $project->getTableName('logging', 'userindex');
        $userGroupsTable = $project->getTableName('user_groups');
        $ufgTable = $project->getTableName('user_former_groups');

        $adminGroups = join(array_map(function ($group) {
            return "'$group'";
        }, $this->getAdminGroups($project)), ',');

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
                GROUP BY user_name
                HAVING 'delete' > 0 OR user_id IN (
                    -- Make sure they were at some point were in a qualifying user group.
                    -- This also ensures we account for users who were inactive within the time period.
                    SELECT ug_user
                    FROM $userGroupsTable
                    WHERE ug_group IN ($adminGroups)
                    UNION
                    SELECT ufg_user
                    FROM $ufgTable
                    WHERE ufg_group IN ($adminGroups)
                )
                ORDER BY 'total' DESC";

        $results = $this->executeProjectsQuery($sql)->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $results);
    }

    /**
     * Get all user groups with admin-like permissions.
     * @param  Project $project
     * @return array Each entry contains 'name' (user group) and 'rights' (the permissions).
     */
    public function getAdminGroups(Project $project)
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
