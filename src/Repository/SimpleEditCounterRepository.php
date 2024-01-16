<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Model\Project;
use App\Model\User;
use Wikimedia\IPUtils;

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

        if ($user->isIpRange()) {
            $result = $this->fetchDataIpRange($project, $user, $namespace, $start, $end);
        } else {
            $result = $this->fetchDataNormal($project, $user, $namespace, $start, $end);
        }

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * @param Project $project
     * @param User $user
     * @param int|string $namespace
     * @param int|false $start
     * @param int|false $end
     * @return string[] Counts, each row with keys 'source' and 'value'.
     */
    private function fetchDataNormal(
        Project $project,
        User $user,
        $namespace = 'all',
        $start = false,
        $end = false
    ): array {
        $userTable = $project->getTableName('user');
        $pageTable = $project->getTableName('page');
        $archiveTable = $project->getTableName('archive');
        $revisionTable = $project->getTableName('revision');
        $userGroupsTable = $project->getTableName('user_groups');

        $arDateConditions = $this->getDateConditions($start, $end, false, '', 'ar_timestamp');
        $revDateConditions = $this->getDateConditions($start, $end);

        // Always JOIN on page, see T325492
        $revNamespaceJoinSql = "JOIN $pageTable ON rev_page = page_id";
        $revNamespaceWhereSql = 'all' === $namespace ? '' : "AND page_namespace = $namespace";
        $arNamespaceWhereSql = 'all' === $namespace ? '' : "AND ar_namespace = $namespace";

        $sql = "SELECT 'id' AS source, user_id as value
                    FROM $userTable
                    WHERE user_name = :username
                UNION
                SELECT 'arch' AS source, COUNT(*) AS value
                    FROM $archiveTable
                    WHERE ar_actor = :actorId
                    $arNamespaceWhereSql
                    $arDateConditions
                UNION
                SELECT 'rev' AS source, COUNT(*) AS value
                    FROM $revisionTable
                    $revNamespaceJoinSql
                    WHERE rev_actor = :actorId
                    $revNamespaceWhereSql
                    $revDateConditions
                UNION
                SELECT 'groups' AS source, ug_group AS value
                    FROM $userGroupsTable
                    JOIN $userTable ON user_id = ug_user
                    WHERE user_name = :username";

        return $this->executeProjectsQuery($project, $sql, [
            'username' => $user->getUsername(),
            'actorId' => $user->getActorId($project),
        ])->fetchAllAssociative();
    }

    /**
     * @param Project $project
     * @param User $user
     * @param int|string $namespace
     * @param int|false $start
     * @param int|false $end
     * @return string[] Counts, each row with keys 'source' and 'value'.
     */
    private function fetchDataIpRange(
        Project $project,
        User $user,
        $namespace = 'all',
        $start = false,
        $end = false
    ): array {
        $ipcTable = $project->getTableName('ip_changes');
        $revTable = $project->getTableName('revision', '');
        $pageTable = $project->getTableName('page');

        $revDateConditions = $this->getDateConditions($start, $end, false, "$ipcTable.", 'ipc_rev_timestamp');
        [$startHex, $endHex] = IPUtils::parseRange($user->getUsername());

        $revNamespaceJoinSql = 'all' === $namespace ? '' : "JOIN $revTable ON rev_id = ipc_rev_id " .
            "JOIN $pageTable ON rev_page = page_id";
        $revNamespaceWhereSql = 'all' === $namespace ? '' : "AND page_namespace = $namespace";

        $sql = "SELECT 'rev' AS source, COUNT(*) AS value
                FROM $ipcTable
                $revNamespaceJoinSql
                WHERE ipc_hex BETWEEN :start AND :end
                $revDateConditions
                $revNamespaceWhereSql";

        return $this->executeProjectsQuery($project, $sql, [
            'start' => $startHex,
            'end' => $endHex,
        ])->fetchAllAssociative();
    }
}
