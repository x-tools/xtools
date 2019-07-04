<?php
/**
 * This file contains only the EditCounterRepository class.
 */

declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Project;
use AppBundle\Model\User;

/**
 * An EditCounterRepository is responsible for retrieving edit count information from the
 * databases and API. It doesn't do any post-processing of that information.
 * @codeCoverageIgnore
 */
class EditCounterRepository extends UserRightsRepository
{
    /**
     * Get data about revisions, pages, etc.
     * @param Project $project The project.
     * @param User $user The user.
     * @return string[] With keys: 'deleted', 'live', 'total', '24h', '7d', '30d',
     * '365d', 'small', 'large', 'with_comments', and 'minor_edits', ...
     */
    public function getPairData(Project $project, User $user): array
    {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_pairdata');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Prepare the queries and execute them.
        $archiveTable = $project->getTableName('archive');
        $revisionTable = $project->getTableName('revision');

        $sql = "
            -- Revision counts.
            (SELECT 'deleted' AS `key`, COUNT(ar_id) AS val FROM $archiveTable
                WHERE ar_actor = :actorId
            ) UNION (
            SELECT 'live' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_actor = :actorId
            ) UNION (
            SELECT 'day' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_actor = :actorId AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ) UNION (
            SELECT 'week' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_actor = :actorId AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 WEEK)
            ) UNION (
            SELECT 'month' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_actor = :actorId AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            ) UNION (
            SELECT 'year' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_actor = :actorId AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
            ) UNION (
            SELECT 'minor' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_actor = :actorId AND rev_minor_edit = 1

            -- Page counts.
            ) UNION (
            SELECT 'edited-live' AS `key`, COUNT(DISTINCT rev_page) AS `val`
                FROM $revisionTable
                WHERE rev_actor = :actorId
            ) UNION (
            SELECT 'edited-deleted' AS `key`, COUNT(DISTINCT ar_page_id) AS `val`
                FROM $archiveTable
                WHERE ar_actor = :actorId
            ) UNION (
            SELECT 'created-live' AS `key`, COUNT(DISTINCT rev_page) AS `val`
                FROM $revisionTable
                WHERE rev_actor = :actorId AND rev_parent_id = 0
            ) UNION (
            SELECT 'created-deleted' AS `key`, COUNT(DISTINCT ar_page_id) AS `val`
                FROM $archiveTable
                WHERE ar_actor = :actorId AND ar_parent_id = 0
            )";

        $resultQuery = $this->executeProjectsQuery($sql, [
            'actorId' => $user->getActorId($project),
        ]);

        $revisionCounts = [];
        while ($result = $resultQuery->fetch()) {
            $revisionCounts[$result['key']] = (int)$result['val'];
        }

        // Cache and return.
        return $this->setCache($cacheKey, $revisionCounts);
    }

    /**
     * Get log totals for a user.
     * @param Project $project The project.
     * @param User $user The user.
     * @return integer[] Keys are "<log>-<action>" strings, values are counts.
     */
    public function getLogCounts(Project $project, User $user): array
    {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_logcounts');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Query.
        $loggingTable = $this->getTableName($project->getDatabaseName(), 'logging');
        $sql = "
        (SELECT CONCAT(log_type, '-', log_action) AS source, COUNT(log_id) AS value
            FROM $loggingTable
            WHERE log_actor = :actorId
            GROUP BY log_type, log_action
        )";

        $results = $this->executeProjectsQuery($sql, [
            'actorId' => $user->getActorId($project),
        ])->fetchAll();

        $logCounts = array_combine(
            array_map(function ($e) {
                return $e['source'];
            }, $results),
            array_map(function ($e) {
                return (int)$e['value'];
            }, $results)
        );

        // Make sure there is some value for each of the wanted counts.
        $requiredCounts = [
            'thanks-thank',
            'review-approve',
            'newusers-create2',
            'newusers-byemail',
            'patrol-patrol',
            'block-block',
            'block-reblock',
            'block-unblock',
            'protect-protect',
            'protect-modify',
            'protect-unprotect',
            'rights-rights',
            'move-move',
            'delete-delete',
            'delete-revision',
            'delete-restore',
            'import-import',
            'import-interwiki',
            'import-upload',
            'upload-upload',
            'upload-overwrite',
            'abusefilter-modify',
            'merge-merge',
        ];
        foreach ($requiredCounts as $req) {
            if (!isset($logCounts[$req])) {
                $logCounts[$req] = 0;
            }
        }

        // Cache and return.
        return $this->setCache($cacheKey, $logCounts);
    }

    /**
     * Get counts of files moved, and files moved/uploaded on Commons.
     * Local file uploads are counted in getLogCounts() since we're querying the same rows anyway.
     * @param Project $project
     * @param User $user
     * @return array
     */
    public function getFileCounts(Project $project, User $user): array
    {
        // Anons can't upload or move files.
        if ($user->isAnon()) {
            return [];
        }

        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_filecounts');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $loggingTable = $project->getTableName('logging');

        $sqlParts = [
            "SELECT 'files_moved' AS `key`, COUNT(log_id) AS `val`
             FROM $loggingTable
             WHERE log_actor = :actorId
               AND log_type = 'move'
               AND log_action = 'move'
               AND log_namespace = 6",
        ];

        $bindings = ['actorId' => $user->getActorId($project)];

        if ($this->isLabs() && 'commons.wikimedia.org' !== $project->getDomain()) {
            $commonsProject = ProjectRepository::getProject('commonswiki', $this->container);
            $loggingTableCommons = $commonsProject->getTableName('logging');
            $sqlParts[] = "SELECT 'files_moved_commons' AS `key`, COUNT(log_id) AS `val`
                           FROM $loggingTableCommons
                           WHERE log_actor = :actorId2 AND log_type = 'move'
                               AND log_action = 'move' AND log_namespace = 6";
            $sqlParts[] = "SELECT 'files_uploaded_commons' AS `key`, COUNT(log_id) AS `val`
                           FROM $loggingTableCommons
                           WHERE log_actor = :actorId2 AND log_type = 'upload' AND log_action = 'upload'";
            $bindings['actorId2'] = $user->getActorId($commonsProject);
        }

        $sql = '('.implode("\n) UNION (\n", $sqlParts).')';

        $results = $this->executeProjectsQuery($sql, $bindings)->fetchAll();

        $counts = array_combine(
            array_map(function ($e) {
                return $e['key'];
            }, $results),
            array_map(function ($e) {
                return (int)$e['val'];
            }, $results)
        );

        // Cache and return.
        return $this->setCache($cacheKey, $counts);
    }

    /**
     * Get the IDs and timestamps of the latest edit and logged action by the given user.
     * @param Project $project
     * @param User $user
     * @return string[] With keys 'rev_first', 'rev_latest', 'log_latest'.
     */
    public function getFirstAndLatestActions(Project $project, User $user): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_first_latest_actions');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $loggingTable = $project->getTableName('logging', 'userindex');
        $revisionTable = $project->getTableName('revision');

        $sql = "(
                    SELECT 'rev_first' AS `key`, rev_id AS `id`,
                        rev_timestamp AS `timestamp`, NULL as `type`
                    FROM $revisionTable
                    WHERE rev_actor = :actorId
                    LIMIT 1
                ) UNION (
                    SELECT 'rev_latest' AS `key`, rev_id AS `id`,
                        rev_timestamp AS `timestamp`, NULL as `type`
                    FROM $revisionTable
                    WHERE rev_actor = :actorId
                    ORDER BY rev_timestamp DESC LIMIT 1
                ) UNION (
                    SELECT 'log_latest' AS `key`, log_id AS `id`,
                        log_timestamp AS `timestamp`, log_type AS `type`
                    FROM $loggingTable
                    WHERE log_actor = :actorId
                    ORDER BY log_timestamp DESC LIMIT 1
                )";

        $resultQuery = $this->executeProjectsQuery($sql, [
            'actorId' => $user->getActorId($project),
        ]);

        $actions = [];
        while ($result = $resultQuery->fetch()) {
            $actions[$result['key']] = [
                'id' => $result['id'],
                'timestamp' => $result['timestamp'],
                'type' => $result['type'],
            ];
        }

        return $this->setCache($cacheKey, $actions);
    }

    /**
     * Get data for all blocks set on the given user.
     * @param Project $project
     * @param User $user
     * @return array
     */
    public function getBlocksReceived(Project $project, User $user): array
    {
        $loggingTable = $this->getTableName($project->getDatabaseName(), 'logging', 'logindex');
        $sql = "SELECT log_action, log_timestamp, log_params FROM $loggingTable
                WHERE log_type = 'block'
                AND log_action IN ('block', 'reblock', 'unblock')
                AND log_timestamp > 0
                AND log_title = :username
                AND log_namespace = 2
                ORDER BY log_timestamp ASC";
        $username = str_replace(' ', '_', $user->getUsername());

        return $this->executeProjectsQuery($sql, [
            'username' => $username,
        ])->fetchAll();
    }

    /**
     * Get the given user's total edit counts per namespace on the given project.
     * @param Project $project The project.
     * @param User $user The user.
     * @return array Array keys are namespace IDs, values are the edit counts.
     */
    public function getNamespaceTotals(Project $project, User $user): array
    {
        // Cache?
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_namespacetotals');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Query.
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $sql = "SELECT page_namespace, COUNT(rev_id) AS total
            FROM $pageTable p JOIN $revisionTable r ON (r.rev_page = p.page_id)
            WHERE r.rev_actor = :actorId
            GROUP BY page_namespace";

        $results = $this->executeProjectsQuery($sql, [
            'actorId' => $user->getActorId($project),
        ])->fetchAll();

        $namespaceTotals = array_combine(array_map(function ($e) {
            return $e['page_namespace'];
        }, $results), array_map(function ($e) {
            return (int)$e['total'];
        }, $results));

        // Cache and return.
        return $this->setCache($cacheKey, $namespaceTotals);
    }

    /**
     * Get data for a bar chart of monthly edit totals per namespace.
     * @param Project $project The project.
     * @param User $user The user.
     * @return string[] [
     *                      [
     *                          'year' => <year>,
     *                          'month' => <month>,
     *                          'page_namespace' => <namespace>,
     *                          'count' => <count>,
     *                      ],
     *                      ...
     *                  ]
     */
    public function getMonthCounts(Project $project, User $user): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_monthcounts');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revisionTable = $project->getTableName('revision');
        $pageTable = $project->getTableName('page');
        $sql =
            "SELECT "
            . "     YEAR(rev_timestamp) AS `year`,"
            . "     MONTH(rev_timestamp) AS `month`,"
            . "     page_namespace,"
            . "     COUNT(rev_id) AS `count` "
            .  " FROM $revisionTable JOIN $pageTable ON (rev_page = page_id)"
            . " WHERE rev_actor = :actorId"
            . " GROUP BY YEAR(rev_timestamp), MONTH(rev_timestamp), page_namespace";

        $totals = $this->executeProjectsQuery($sql, [
            'actorId' => $user->getActorId($project),
        ])->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $totals);
    }

    /**
     * Get data for the timecard chart, with totals grouped by day and to the nearest two-hours.
     * @param Project $project
     * @param User $user
     * @return string[]
     */
    public function getTimeCard(Project $project, User $user): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_timecard');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $hourInterval = 2;
        $xCalc = "ROUND(HOUR(rev_timestamp)/$hourInterval) * $hourInterval";
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $sql = "SELECT "
            . "     DAYOFWEEK(rev_timestamp) AS `day_of_week`, "
            . "     $xCalc AS `hour`, "
            . "     COUNT(rev_id) AS `value` "
            . " FROM $revisionTable"
            . " WHERE rev_actor = :actorId"
            . " GROUP BY DAYOFWEEK(rev_timestamp), $xCalc ";

        $totals = $this->executeProjectsQuery($sql, [
            'actorId' => $user->getActorId($project),
        ])->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $totals);
    }

    /**
     * Get various data about edit sizes of the past 5,000 edits.
     * Will cache the result for 10 minutes.
     * @param Project $project The project.
     * @param User $user The user.
     * @return string[] Values with for keys 'average_size',
     *                  'small_edits' and 'large_edits'
     */
    public function getEditSizeData(Project $project, User $user): array
    {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_editsizes');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Prepare the queries and execute them.
        $revisionTable = $project->getTableName('revision');
        $sql = "SELECT AVG(sizes.size) AS average_size,
                COUNT(CASE WHEN sizes.size < 20 THEN 1 END) AS small_edits,
                COUNT(CASE WHEN sizes.size > 1000 THEN 1 END) AS large_edits
                FROM (
                    SELECT (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS size
                    FROM $revisionTable AS revs
                    LEFT JOIN $revisionTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                    WHERE revs.rev_actor = :actorId
                    ORDER BY revs.rev_timestamp DESC
                    LIMIT 5000
                ) sizes";
        $results = $this->executeProjectsQuery($sql, [
            'actorId' => $user->getActorId($project),
        ])->fetch();

        // Cache and return.
        return $this->setCache($cacheKey, $results);
    }

    /**
     * Get the number of edits this user made using semi-automated tools.
     * @param Project $project
     * @param User $user
     * @return int Result of query, see below.
     */
    public function countAutomatedEdits(Project $project, User $user): int
    {
        $autoEditsRepo = new AutoEditsRepository();
        $autoEditsRepo->setContainer($this->container);
        return $autoEditsRepo->countAutomatedEdits($project, $user);
    }
}
