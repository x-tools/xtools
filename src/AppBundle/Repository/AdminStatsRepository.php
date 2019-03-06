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
    /**
     * Get the URLs of the icons for the user groups.
     * @return string[] System user group name as the key, URL to image as the value.
     */
    public function getUserGroupIcons(): array
    {
        return $this->container->getParameter('user_group_icons');
    }

    /**
     * Core function to get statistics about users who have admin/patroller/steward-like permissions.
     * @param Project $project
     * @param string $start SQL-ready format.
     * @param string $end
     * @param string $type Which 'type' we're querying for, as configured in admin_stats.yml
     * @param string[]|null $actions Which log actions to query for.
     * @return string[][] with key for each action type (specified in admin_stats.yml), including 'total'.
     */
    public function getStats(Project $project, string $start, string $end, string $type, ?array $actions = null): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'adminstats');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $userTable = $project->getTableName('user');
        $loggingTable = $project->getTableName('logging', 'logindex');
        [$countSql, $types, $actions] = $this->getLogSqlParts($type, $actions);

        $sql = "SELECT user_name AS `username`,
                    $countSql
                    SUM(IF(log_type != '' AND log_action != '', 1, 0)) AS `total`
                FROM $loggingTable
                JOIN $userTable ON user_id = log_user
                WHERE log_timestamp > '$start' AND log_timestamp <= '$end'
                  AND log_type IS NOT NULL
                  AND log_action IS NOT NULL
                  AND log_type IN ($types)
                  AND log_action IN ($actions)
                GROUP BY user_name
                HAVING `total` > 0
                ORDER BY 'total' DESC";

        $results = $this->executeProjectsQuery($sql)->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $results);
    }

    /**
     * Get the SQL to query for the given type and actions.
     * @param string $type
     * @param string[]|null $requestedActions
     * @return string[]
     */
    private function getLogSqlParts(string $type, ?array $requestedActions = null): array
    {
        $config = $this->container->getParameter('admin_stats')[$type];

        // 'permissions' and 'user_group' are only used for self::getAdminGroups()
        unset($config['permissions']);
        unset($config['user_group']);

        $countSql = '';
        $logTypes = [];
        $actions = [];

        foreach ($config as $key => $logTypeActions) {
            if (is_array($requestedActions) && !in_array($key, $requestedActions)) {
                continue;
            }

            $keyTypes = [];
            $keyActions = [];

            foreach ($logTypeActions as $entry) {
                [$logType, $logAction] = explode('/', $entry);
                $logTypes[] = $keyTypes[] = $this->getProjectsConnection()->quote($logType, \PDO::PARAM_STR);
                $actions[] = $keyActions[] = $this->getProjectsConnection()->quote($logAction, \PDO::PARAM_STR);
            }

            $keyTypes = implode(',', array_unique($keyTypes));
            $keyActions = implode(',', array_unique($keyActions));

            $countSql .= "SUM(IF((log_type IN ($keyTypes) AND log_action IN ($keyActions)), 1, 0)) AS `$key`,\n";
        }

        return [$countSql, implode(',', array_unique($logTypes)), implode(',', array_unique($actions))];
    }

    /**
     * Get the user_group from the config given the 'type'.
     * @param string $type
     * @return string
     */
    public function getRelevantUserGroup(string $type): string
    {
        return $this->container->getParameter('admin_stats')[$type]['user_group'];
    }

    /**
     * Get all user groups with permissions applicable to the given 'group'.
     * @param Project $project
     * @param string $type Which 'type' we're querying for, as configured in admin_stats.yml
     * @return array Each entry contains 'name' (user group) and 'rights' (the permissions).
     */
    public function getUserGroups(Project $project, string $type): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'admingroups');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $permissions = $this->container->getParameter('admin_stats')[$type]['permissions'];
        $userGroups = [];

        $api = $this->getMediawikiApi($project);
        $query = new SimpleRequest('query', [
            'meta' => 'siteinfo',
            'siprop' => 'usergroups',
        ]);
        $res = $api->getRequest($query);

        // If there isn't a user groups hash than let it error out... Something else must have gone horribly wrong.
        foreach ($res['query']['usergroups'] as $userGroup) {
            // If they are able to add and remove user groups,
            // we'll treat them as having the 'userrights' permission.
            if (isset($userGroup['add']) || isset($userGroup['remove'])) {
                array_push($userGroup['rights'], 'userrights');
            }

            if (count(array_intersect($userGroup['rights'], $permissions)) > 0) {
                $userGroups[] = $userGroup['name'];
            }
        }

        // Cache for a week and return.
        return $this->setCache($cacheKey, $userGroups, 'P7D');
    }
}
