<?php
/**
 * This file contains only the AdminStatsRepository class.
 */

declare(strict_types = 1);

namespace App\Repository;

use App\Model\Project;
use PDO;

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
     * @param int $start UTC timestamp.
     * @param int $end UTC timestamp.
     * @param string $type Which 'type' we're querying for, as configured in admin_stats.yaml
     * @param string[] $actions Which log actions to query for.
     * @return string[][] with key for each action type (specified in admin_stats.yaml), including 'total'.
     */
    public function getStats(Project $project, int $start, int $end, string $type, array $actions = []): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'adminstats');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $actorTable = $project->getTableName('actor');
        $loggingTable = $project->getTableName('logging', 'logindex');
        [$countSql, $types, $actions] = $this->getLogSqlParts($project, $type, $actions);
        $dateConditions = $this->getDateConditions($start, $end, false, "logging_logindex.", 'log_timestamp');

        if (empty($types) || empty($actions)) {
            // Types/actions not applicable to this wiki.
            return [];
        }

        $sql = "SELECT actor_name AS `username`,
                    $countSql
                    SUM(IF(log_type != '' AND log_action != '', 1, 0)) AS `total`
                FROM $loggingTable
                JOIN $actorTable ON log_actor = actor_id
                WHERE log_type IN ($types)
                    AND log_action IN ($actions)
                    $dateConditions
                GROUP BY actor_name
                HAVING `total` > 0";

        $results = $this->executeProjectsQuery($project, $sql)->fetchAllAssociative();

        // Cache and return.
        return $this->setCache($cacheKey, $results);
    }

    /**
     * Get the SQL to query for the given type and actions.
     * @param Project $project
     * @param string $type
     * @param string[] $requestedActions
     * @return string[]
     */
    private function getLogSqlParts(Project $project, string $type, array $requestedActions = []): array
    {
        $config = $this->getConfig($project)[$type];
        $connection = $this->getProjectsConnection($project);

        $countSql = '';
        $logTypes = [];
        $logActions = [];

        foreach ($config['actions'] as $key => $logTypeActions) {
            if (is_array($requestedActions) && !in_array($key, $requestedActions)) {
                continue;
            }

            $keyTypes = [];
            $keyActions = [];

            /** @var string|array $entry String matching 'log_type/log_action' or a configuration array. */
            foreach ($logTypeActions as $entry) {
                // admin_stats.yaml gives us the log type and action as a string in the format of "type/action".
                [$logType, $logAction] = explode('/', $entry);

                $logTypes[] = $keyTypes[] = $connection->quote($logType, PDO::PARAM_STR);
                $logActions[] = $keyActions[] = $connection->quote($logAction, PDO::PARAM_STR);
            }

            $keyTypes = implode(',', array_unique($keyTypes));
            $keyActions = implode(',', array_unique($keyActions));

            $countSql .= "SUM(IF((log_type IN ($keyTypes) AND log_action IN ($keyActions)), 1, 0)) AS `$key`,\n";
        }

        return [$countSql, implode(',', array_unique($logTypes)), implode(',', array_unique($logActions))];
    }

    /**
     * Get the configuration for the given Project. This respects extensions installed on the wiki.
     * @param Project $project
     * @return array
     */
    public function getConfig(Project $project): array
    {
        $config = $this->container->getParameter('admin_stats');
        $extensions = $project->getInstalledExtensions();

        foreach ($config as $type => $values) {
            foreach ($values['actions'] as $permission => $actions) {
                $requiredExtension = $actions[0]['extension'] ?? '';
                if ('' !== $requiredExtension) {
                    unset($config[$type]['actions'][$permission][0]);

                    if (!in_array($requiredExtension, $extensions)) {
                        unset($config[$type]['actions'][$permission]);
                        continue;
                    }
                }
            }
        }

        return $config;
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
     * @param string $type Which 'type' we're querying for, as configured in admin_stats.yaml
     * @return array Keys are 'local' and 'global', each an array of user groups with keys 'name' and 'rights'.
     */
    public function getUserGroups(Project $project, string $type): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'admingroups');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $permissions = $this->container->getParameter('admin_stats')[$type]['permissions'];

        $res = $this->executeApiRequest($project, [
            'meta' => 'siteinfo',
            'siprop' => 'usergroups',
            'list' => 'globalgroups',
            'ggpprop' => 'rights',
        ])['query'];

        $userGroups = [
            'local' => array_unique(array_merge(
                $this->getUserGroupByLocality($res, $permissions),
                $this->container->getParameter('admin_stats')[$type]['extra_user_groups']
            )),
            'global' => array_unique(array_merge(
                $this->getUserGroupByLocality($res, $permissions, true),
                $this->container->getParameter('admin_stats')[$type]['extra_user_groups']
            )),
        ];

        // Cache for a week and return.
        return $this->setCache($cacheKey, $userGroups, 'P7D');
    }

    /**
     * Parse user groups API response, returning groups that have any of the given permissions.
     * @param array[] $res API response.
     * @param string[] $permissions Permissions to look for.
     * @param bool $global Return global user groups instead of local.
     * @return array[]
     */
    private function getUserGroupByLocality(array $res, array $permissions, bool $global = false): array
    {
        $userGroups = [];

        foreach (($global ? $res['globalgroups'] : $res['usergroups']) as $userGroup) {
            // If they are able to add and remove user groups, we'll treat them as having the 'userrights' permission.
            if (isset($userGroup['add']) || isset($userGroup['remove'])) {
                array_push($userGroup['rights'], 'userrights');
                $userGroup['rights'][] = 'userrights';
            }
            if (count(array_intersect($userGroup['rights'], $permissions)) > 0) {
                $userGroups[] = $userGroup['name'];
            }
        }

        return $userGroups;
    }
}
