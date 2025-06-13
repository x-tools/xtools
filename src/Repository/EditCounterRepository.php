<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Model\Project;
use App\Model\User;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Wikimedia\IPUtils;

/**
 * An EditCounterRepository is responsible for retrieving edit count information from the
 * databases and API. It doesn't do any post-processing of that information.
 * @codeCoverageIgnore
 */
class EditCounterRepository extends Repository
{
    protected AutoEditsRepository $autoEditsRepo;
    protected ProjectRepository $projectRepo;

    public function __construct(
        ManagerRegistry $managerRegistry,
        CacheItemPoolInterface $cache,
        Client $guzzle,
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
        bool $isWMF,
        int $queryTimeout,
        ProjectRepository $projectRepo,
        AutoEditsRepository $autoEditsRepo
    ) {
        $this->projectRepo = $projectRepo;
        $this->autoEditsRepo = $autoEditsRepo;
        parent::__construct($managerRegistry, $cache, $guzzle, $logger, $parameterBag, $isWMF, $queryTimeout);
    }

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
        $pageTable = $project->getTableName('page');

        // Always JOIN on page, see T355027
        $pageJoin = "JOIN $pageTable ON rev_page = page_id";

        if ($user->isIpRange()) {
            $ipcTable = $project->getTableName('ip_changes');
            $ipcJoin = "JOIN $ipcTable ON ipc_rev_id = rev_id";
            $whereClause = "ipc_hex BETWEEN :startIp AND :endIp";
            $archiveQueries = '';
            $params = [];
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
        } else {
            $ipcJoin = '';
            $whereClause = 'rev_actor = :actorId';
            $params = ['actorId' => $user->getActorId($project)];
            $archiveQueries = "
                SELECT 'deleted' AS `key`, COUNT(ar_id) AS val FROM $archiveTable
                    WHERE ar_actor = :actorId
                ) UNION (
                SELECT 'edited-deleted' AS `key`, COUNT(DISTINCT ar_page_id) AS `val` FROM $archiveTable
                    WHERE ar_actor = :actorId
                ) UNION (
                SELECT 'created-deleted' AS `key`, COUNT(DISTINCT ar_page_id) AS `val` FROM $archiveTable
                    WHERE ar_actor = :actorId AND ar_parent_id = 0
                ) UNION (";
        }

        $sql = "
            ($archiveQueries

            -- Revision counts.
            SELECT 'live' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                $pageJoin
                $ipcJoin
                WHERE $whereClause
            ) UNION (
            SELECT 'day' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                $pageJoin
                $ipcJoin
                WHERE $whereClause AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ) UNION (
            SELECT 'week' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                $pageJoin
                $ipcJoin
                WHERE $whereClause AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 WEEK)
            ) UNION (
            SELECT 'month' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                $pageJoin
                $ipcJoin
                WHERE $whereClause AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            ) UNION (
            SELECT 'year' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                $pageJoin
                $ipcJoin
                WHERE $whereClause AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
            ) UNION (
            SELECT 'minor' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                $pageJoin
                $ipcJoin
                WHERE $whereClause AND rev_minor_edit = 1

            -- Page counts.
            ) UNION (
            SELECT 'edited-live' AS `key`, COUNT(DISTINCT rev_page) AS `val`
                FROM $revisionTable
                $pageJoin
                $ipcJoin
                WHERE $whereClause
            ) UNION (
            SELECT 'created-live' AS `key`, COUNT(DISTINCT rev_page) AS `val`
                FROM $revisionTable
                $pageJoin
                $ipcJoin
                WHERE $whereClause AND rev_parent_id = 0
            )";

        $resultQuery = $this->executeProjectsQuery($project, $sql, $params);

        $revisionCounts = [];
        while ($result = $resultQuery->fetchAssociative()) {
            $revisionCounts[$result['key']] = (int)$result['val'];
        }

        // Cache and return.
        return $this->setCache($cacheKey, $revisionCounts);
    }

    /**
     * Get log totals for a user.
     * @param Project $project The project.
     * @param User $user The user.
     * @return int[] Keys are "<log>-<action>" strings, values are counts.
     */
    public function getLogCounts(Project $project, User $user): array
    {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_logcounts');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Query.
        $loggingTable = $project->getTableName('logging');
        $sql = "SELECT CONCAT(log_type, '-', log_action) AS source, COUNT(log_id) AS value
                FROM $loggingTable
                WHERE log_actor = :actorId
                GROUP BY log_type, log_action
                -- T363633
                HAVING source IS NOT NULL";
        $results = $this->executeProjectsQuery($project, $sql, [
            'actorId' => $user->getActorId($project),
        ])->fetchAllAssociative();

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
            'contentmodel-change',
            'contentmodel-new',
            'pagetriage-curation-reviewed',
            'pagetriage-curation-reviewed-redirect',
            'pagetriage-curation-reviewed-article',
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
        if ($user->isAnon($project)) {
            return [];
        }

        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_filecounts');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $loggingTable = $project->getTableName('logging');

        $sql = "SELECT 'files_moved' AS `key`, COUNT(log_id) AS `val`
                FROM $loggingTable
                WHERE log_actor = :actorId
                    AND log_type = 'move'
                    AND log_action = 'move'
                    AND log_namespace = 6";
        $results = $this->executeProjectsQuery($project, $sql, [
            'actorId' => $user->getActorId($project),
        ])->fetchAllAssociative();

        if ($this->isWMF && 'commons.wikimedia.org' !== $project->getDomain()) {
            $results = array_merge($results, $this->getFileCountsCommons($user));
        }

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
     * Get count of files moved and uploaded on Commons.
     * @param User $user
     * @return array
     */
    protected function getFileCountsCommons(User $user): array
    {
        $commonsProject = $this->projectRepo->getProject('commonswiki');
        $loggingTableCommons = $commonsProject->getTableName('logging');
        $sql = "(SELECT 'files_moved_commons' AS `key`, COUNT(log_id) AS `val`
                 FROM $loggingTableCommons
                 WHERE log_actor = :actorId AND log_type = 'move'
                 AND log_action = 'move' AND log_namespace = 6
                ) UNION (
                 SELECT 'files_uploaded_commons' AS `key`, COUNT(log_id) AS `val`
                 FROM $loggingTableCommons
                 WHERE log_actor = :actorId AND log_type = 'upload' AND log_action = 'upload')";
        return $this->executeProjectsQuery($commonsProject, $sql, [
            'actorId' => $user->getActorId($commonsProject),
        ])->fetchAllAssociative();
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
        if ($user->isIpRange()) {
            $fromTable = $project->getTableName('ip_changes');
            $idColumn = 'ipc_rev_id';
            $timestampColumn = 'ipc_rev_timestamp';
            $whereClause = "ipc_hex BETWEEN :startIp AND :endIp";
            $params = [];
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
            $logQuery = '';
        } else {
            $fromTable = $project->getTableName('revision');
            $idColumn = 'rev_id';
            $timestampColumn = 'rev_timestamp';
            $whereClause = 'rev_actor = :actorId';
            $params = ['actorId' => $user->getActorId($project)];
            $logQuery = "
                SELECT 'log_latest' AS `key`, log_id AS `id`,
                        log_timestamp AS `timestamp`, log_type AS `type`
                    FROM $loggingTable
                    WHERE log_actor = :actorId
                    ORDER BY -log_timestamp LIMIT 1
                ) UNION (";
        }

        $sql = "(
                $logQuery
                    SELECT 'rev_first' AS `key`, $idColumn AS `id`,
                        $timestampColumn AS `timestamp`, NULL as `type`
                    FROM $fromTable
                    WHERE $whereClause
                    ORDER BY $timestampColumn ASC LIMIT 1
                ) UNION (
                    SELECT 'rev_latest' AS `key`, $idColumn AS `id`,
                        $timestampColumn AS `timestamp`, NULL as `type`
                    FROM $fromTable
                    WHERE $whereClause
                    ORDER BY $timestampColumn DESC LIMIT 1
                )";

        $resultQuery = $this->executeProjectsQuery($project, $sql, $params);

        $actions = [];
        while ($result = $resultQuery->fetchAssociative()) {
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

        return $this->executeProjectsQuery($project, $sql, [
            'username' => $username,
        ])->fetchAllAssociative();
    }

    /**
     * Get the number of times the user was thanked.
     * @param Project $project
     * @param User $user
     * @return int
     */
    public function getThanksReceived(Project $project, User $user): int
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_thanksreceived');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $loggingTable = $project->getTableName('logging', 'logindex');
        $sql = "SELECT COUNT(log_id)
                FROM $loggingTable
                WHERE log_type = 'thanks'
                AND log_title = :username
                AND log_namespace = 2";
        $username = str_replace(' ', '_', $user->getUsername());

        return $this->setCache($cacheKey, (int)$this->executeProjectsQuery($project, $sql, [
            'username' => $username,
        ])->fetchColumn());
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
        $revisionTable = $project->getTableName('revision');
        $pageTable = $project->getTableName('page');
        $ipcJoin = '';
        $whereClause = 'r.rev_actor = :actorId';
        $params = ['actorId' => $user->getActorId($project)];

        if ($user->isIpRange()) {
            $ipcTable = $project->getTableName('ip_changes');
            $ipcJoin = "JOIN $ipcTable ON ipc_rev_id = rev_id";
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
            $whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
        }

        $sql = "SELECT page_namespace AS `namespace`, COUNT(rev_id) AS `total`
            FROM $pageTable p JOIN $revisionTable r ON (r.rev_page = p.page_id)
            $ipcJoin
            WHERE $whereClause
            GROUP BY `namespace`";

        $results = $this->executeProjectsQuery($project, $sql, $params)->fetchAll();

        $namespaceTotals = array_combine(array_map(function ($e) {
            return $e['namespace'];
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
     *                          'namespace' => <namespace>,
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
        $ipcJoin = '';
        $whereClause = 'rev_actor = :actorId';
        $params = ['actorId' => $user->getActorId($project)];

        if ($user->isIpRange()) {
            $ipcTable = $project->getTableName('ip_changes');
            $ipcJoin = "JOIN $ipcTable ON ipc_rev_id = rev_id";
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
            $whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
        }

        $sql = "
            SELECT YEAR(rev_timestamp) AS `year`,
                MONTH(rev_timestamp) AS `month`,
                page_namespace AS `namespace`,
                COUNT(rev_id) AS `count`
            FROM $revisionTable JOIN $pageTable ON (rev_page = page_id)
            $ipcJoin
            WHERE $whereClause
            GROUP BY YEAR(rev_timestamp), MONTH(rev_timestamp), `namespace`";

        $totals = $this->executeProjectsQuery($project, $sql, $params)->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $totals);
    }

    /**
     * Get data for the timecard chart, with totals grouped by day and to the nearest two-hours.
     * @param Project $project
     * @param User $user
     * @return string[][]
     */
    public function getTimeCard(Project $project, User $user): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_timecard');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $hourInterval = 1;
        $revisionTable = $project->getTableName('revision');
        // Always JOIN on page, see T325492
        $pageTable = $project->getTableName('page');

        if ($user->isIpRange()) {
            $column = 'ipc_rev_timestamp';
            $table = $project->getTableName('ip_changes');
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
            $whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
            $joinClause = "JOIN $revisionTable ON rev_id = ipc_rev_id
                JOIN $pageTable ON rev_page = page_id";
        } else {
            $column = 'rev_timestamp';
            $table = $revisionTable;
            $whereClause = 'rev_actor = :actorId';
            $params = ['actorId' => $user->getActorId($project)];
            $joinClause = "JOIN $pageTable ON rev_page = page_id";
        }

        $xCalc = "ROUND(HOUR($column)/$hourInterval) * $hourInterval";

        $sql = "
            SELECT DAYOFWEEK($column) AS `day_of_week`,
                $xCalc AS `hour`,
                COUNT($column) AS `value`
            FROM $table
            $joinClause
            WHERE $whereClause
            GROUP BY DAYOFWEEK($column), $xCalc";

        $totals = $this->executeProjectsQuery($project, $sql, $params)->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $totals);
    }

    /**
     * Get various data about edit sizes of the past 5,000 edits.
     * Will cache the result for 10 minutes.
     * @param Project $project The project.
     * @param User $user The user.
     * @return string[] With keys 'average_size', 'small_edits' and 'large_edits'
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
        $pageTable = $project->getTableName('page');
        $ctTable = $project->getTableName('change_tag');
        $ctdTable = $project->getTableName('change_tag_def');
        $ipcJoin = '';
        $whereClause = 'revs.rev_actor = :actorId';
        $params = ['actorId' => $user->getActorId($project)];

        if ($user->isIpRange()) {
            $ipcTable = $project->getTableName('ip_changes');
            $ipcJoin = "JOIN $ipcTable ON ipc_rev_id = revs.rev_id";
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
            $whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
        }

        $sql = "SELECT JSON_ARRAYAGG(data.size) as sizes,
                JSON_ARRAYAGG(data.tags) as tag_lists
                FROM (
                    SELECT CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0) AS size,
                    (
                        SELECT JSON_ARRAYAGG(ctd_name)
                        FROM $ctTable
                        JOIN $ctdTable
                        ON ct_tag_id = ctd_id
                        WHERE ct_rev_id = revs.rev_id
                    ) as tags
                    FROM $revisionTable AS revs
                    JOIN $pageTable ON revs.rev_page = page_id
                    $ipcJoin
                    LEFT JOIN $revisionTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                    WHERE $whereClause
                    ORDER BY revs.rev_timestamp DESC
                    LIMIT 5000
                ) data";
        $results = $this->executeProjectsQuery($project, $sql, $params)->fetchAssociative();
        $results['sizes'] = json_decode($results['sizes']);
        $results['average_size'] = count($results['sizes']) > 0 ? array_sum($results['sizes'])/count($results['sizes']) : 0;
        $isSmall = fn($n) => abs(intval($n)) < 20;
        $isLarge = fn($n) => abs(intval($n)) > 1000;
        $results['small_edits'] = count(array_filter($results['sizes'], $isSmall));
        $results['large_edits'] = count(array_filter($results['sizes'], $isLarge));

        // Cache and return.
        return $this->setCache($cacheKey, $results);
    }

    /**
     * Get the number of edits this user made using semi-automated tools.
     * @param Project $project
     * @param User $user
     * @return int Result of query, see below.
     * @deprecated Inject AutoEditsRepository and call the countAutomatedEdits directly.
     */
    public function countAutomatedEdits(Project $project, User $user): int
    {
        return $this->autoEditsRepo->countAutomatedEdits($project, $user);
    }
}
