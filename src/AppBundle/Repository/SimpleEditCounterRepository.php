<?php
/**
 * This file contains only the SimpleEditCounterRepository class.
 */

declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Project;
use AppBundle\Model\User;

/**
 * SimpleEditCounterRepository is responsible for retrieving data
 * from the database for the Simple Edit Counter tool.
 * @codeCoverageIgnore
 */
class SimpleEditCounterRepository extends Repository
{
    /**
     * Execute and return results of the query used for the Simple Edit Counter.
     * @param Project $project
     * @param User $user
     * @param int|string $namespace Namespace ID or 'all' for all namespaces.
     * @param int|false $start Unix timestamp.
     * @param int|false $end Unix timestamp.
     * @return string[] Counts, each row with keys 'source' and 'value'.
     */
    public function fetchData(Project $project, User $user, $namespace = 'all', $start = false, $end = false): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'simple_editcount');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $userTable = $project->getTableName('user');
        $pageTable = $project->getTableName('page');
        $archiveTable = $project->getTableName('archive');
        $revisionTable = $project->getTableName('revision');
        $userGroupsTable = $project->getTableName('user_groups');

        $arDateConditions = $this->getDateConditions($start, $end, null, 'ar_timestamp');
        $revDateConditions = $this->getDateConditions($start, $end);

        $revNamespaceJoinSql = 'all' === $namespace ? '' : "JOIN $pageTable ON rev_page = page_id";
        $revNamespaceWhereSql = 'all' === $namespace ? '' : "AND page_namespace = $namespace";
        $arNamespaceWhereSql = 'all' === $namespace ? '' : "AND ar_namespace = $namespace";

        $sql = "SELECT 'id' AS source, user_id as value
                    FROM $userTable
                    WHERE user_name = :username
                UNION
                SELECT 'arch' AS source, COUNT(*) AS value
                    FROM $archiveTable
                    WHERE ar_user_text = :username
                    $arNamespaceWhereSql
                    $arDateConditions
                UNION
                SELECT 'rev' AS source, COUNT(*) AS value
                    FROM $revisionTable
                    $revNamespaceJoinSql
                    WHERE rev_user_text = :username
                    $revNamespaceWhereSql
                    $revDateConditions
                UNION
                SELECT 'groups' AS source, ug_group AS value
                    FROM $userGroupsTable
                    JOIN $userTable ON user_id = ug_user
                    WHERE user_name = :username";

        $result = $this->executeProjectsQuery($sql, ['username' => $user->getUsername()])->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }
}
