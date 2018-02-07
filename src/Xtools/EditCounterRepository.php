<?php
/**
 * This file contains only the EditCounterRepository class.
 */

namespace Xtools;

use Mediawiki\Api\SimpleRequest;
use Xtools\AutoEditsRepository;

/**
 * An EditCounterRepository is responsible for retrieving edit count information from the
 * databases and API. It doesn't do any post-processing of that information.
 * @codeCoverageIgnore
 */
class EditCounterRepository extends Repository
{
    /**
     * Get data about revisions, pages, etc.
     * @param Project $project The project.
     * @param User $user The user.
     * @return string[] With keys: 'deleted', 'live', 'total', 'first', 'last', '24h', '7d', '30d',
     * '365d', 'small', 'large', 'with_comments', and 'minor_edits', ...
     */
    public function getPairData(Project $project, User $user)
    {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_pairdata');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Prepare the queries and execute them.
        $archiveTable = $this->getTableName($project->getDatabaseName(), 'archive');
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $sql = "
            -- Revision counts.
            (SELECT 'deleted' AS `key`, COUNT(ar_id) AS val FROM $archiveTable
                WHERE ar_user = :userId
            ) UNION (
            SELECT 'live' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_user = :userId
            ) UNION (
            SELECT 'day' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_user = :userId AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ) UNION (
            SELECT 'week' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_user = :userId AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 WEEK)
            ) UNION (
            SELECT 'month' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_user = :userId AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            ) UNION (
            SELECT 'year' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_user = :userId AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
            ) UNION (
            SELECT 'with_comments' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_user = :userId AND rev_comment != ''
            ) UNION (
            SELECT 'minor' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_user = :userId AND rev_minor_edit = 1

            -- Dates.
            ) UNION (
            SELECT 'first' AS `key`, rev_timestamp AS `val` FROM $revisionTable
                WHERE rev_user = :userId ORDER BY rev_timestamp ASC LIMIT 1
            ) UNION (
            SELECT 'last' AS `key`, rev_timestamp AS `date` FROM $revisionTable
                WHERE rev_user = :userId ORDER BY rev_timestamp DESC LIMIT 1

            -- Page counts.
            ) UNION (
            SELECT 'edited-live' AS `key`, COUNT(DISTINCT rev_page) AS `val`
                FROM $revisionTable
                WHERE rev_user = :userId
            ) UNION (
            SELECT 'edited-deleted' AS `key`, COUNT(DISTINCT ar_page_id) AS `val`
                FROM $archiveTable
                WHERE ar_user = :userId
            ) UNION (
            SELECT 'created-live' AS `key`, COUNT(DISTINCT rev_page) AS `val`
                FROM $revisionTable
                WHERE rev_user = :userId AND rev_parent_id = 0
            ) UNION (
            SELECT 'created-deleted' AS `key`, COUNT(DISTINCT ar_page_id) AS `val`
                FROM $archiveTable
                WHERE ar_user = :userId AND ar_parent_id = 0
            )
        ";

        $resultQuery = $this->executeProjectsQuery($sql, ['userId' => $user->getId($project)]);
        $revisionCounts = [];
        while ($result = $resultQuery->fetch()) {
            $revisionCounts[$result['key']] = $result['val'];
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
    public function getLogCounts(Project $project, User $user)
    {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_logcounts');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $this->stopwatch->start($cacheKey, 'XTools');

        // Query.
        $loggingTable = $this->getTableName($project->getDatabaseName(), 'logging');
        $sql = "
        (SELECT CONCAT(log_type, '-', log_action) AS source, COUNT(log_id) AS value
            FROM $loggingTable
            WHERE log_user = :userId
            GROUP BY log_type, log_action
        )";

        $results = $this->executeProjectsQuery($sql, [
            'userId' => $user->getId($project)
        ])->fetchAll();

        $logCounts = array_combine(
            array_map(function ($e) {
                return $e['source'];
            }, $results),
            array_map(function ($e) {
                return $e['value'];
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
        ];
        foreach ($requiredCounts as $req) {
            if (!isset($logCounts[$req])) {
                $logCounts[$req] = 0;
            }
        }

        // Add Commons upload count, if applicable.
        $logCounts['files_uploaded_commons'] = 0;
        if ($this->isLabs()) {
            $commons = ProjectRepository::getProject('commonswiki', $this->container);
            $userId = $user->getId($commons);
            if ($userId) {
                $sql = "SELECT COUNT(log_id) FROM commonswiki_p.logging_userindex
                    WHERE log_type = 'upload' AND log_action = 'upload' AND log_user = :userId";
                $resultQuery = $this->executeProjectsQuery($sql, ['userId' => $userId]);
                $logCounts['files_uploaded_commons'] = $resultQuery->fetchColumn();
            }
        }

        // Cache and return.
        $this->stopwatch->stop($cacheKey);
        return $this->setCache($cacheKey, $logCounts);
    }

    /**
     * Get data for all blocks set on the given user.
     * @param Project $project
     * @param User $user
     * @return array
     */
    public function getBlocksReceived(Project $project, User $user)
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
            'username' => $username
        ])->fetchAll();
    }

    /**
     * Get user rights changes of the given user.
     * @param Project $project
     * @param User $user
     * @return array
     */
    public function getRightsChanges(Project $project, User $user)
    {
        $loggingTable = $this->getTableName($project->getDatabaseName(), 'logging', 'logindex');
        $userTable = $this->getTableName($project->getDatabaseName(), 'user');
        $sql = "SELECT log_id, log_timestamp, log_comment, log_params, log_action,
                    IF(log_user_text != '', log_user_text, (
                        SELECT user_name
                        FROM $userTable
                        WHERE user_id = log_user
                    )) AS log_user_text
                FROM $loggingTable
                WHERE log_type = 'rights'
                AND log_namespace = 2
                AND log_title = :username
                ORDER BY log_timestamp DESC";
        $username = str_replace(' ', '_', $user->getUsername());

        return $this->executeProjectsQuery($sql, [
            'username' => $username
        ])->fetchAll();
    }

    /**
     * Get a user's total edit count on all projects.
     * @see EditCounterRepository::globalEditCountsFromCentralAuth()
     * @see EditCounterRepository::globalEditCountsFromDatabases()
     * @param User $user The user.
     * @param Project $project The project to start from.
     * @return mixed[] Elements are arrays with 'project' (Project), and 'total' (int).
     */
    public function globalEditCounts(User $user, Project $project)
    {
        // Get the edit counts from CentralAuth or database.
        $editCounts = $this->globalEditCountsFromCentralAuth($user, $project);
        if ($editCounts === false) {
            $editCounts = $this->globalEditCountsFromDatabases($user, $project);
        }

        // Pre-populate all projects' metadata, to prevent each project call from fetching it.
        $project->getRepository()->getAll();

        // Compile the output.
        $out = [];
        foreach ($editCounts as $editCount) {
            $out[] = [
                'project' => ProjectRepository::getProject($editCount['dbName'], $this->container),
                'total' => $editCount['total'],
            ];
        }
        return $out;
    }

    /**
     * Get a user's total edit count on one or more project.
     * Requires the CentralAuth extension to be installed on the project.
     *
     * @param User $user The user.
     * @param Project $project The project to start from.
     * @return mixed[] Elements are arrays with 'dbName' (string), and 'total' (int).
     */
    protected function globalEditCountsFromCentralAuth(User $user, Project $project)
    {
        // Set up cache and stopwatch.
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_globaleditcounts');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $this->stopwatch->start($cacheKey, 'XTools');

        $this->log->debug(__METHOD__." Getting global edit counts from for ".$user->getUsername());

        // Load all projects, so it doesn't have to request metadata about each one as it goes.
        $project->getRepository()->getAll();

        $api = $this->getMediawikiApi($project);
        $params = [
            'meta' => 'globaluserinfo',
            'guiprop' => 'editcount|merged',
            'guiuser' => $user->getUsername(),
        ];
        $query = new SimpleRequest('query', $params);
        $result = $api->getRequest($query);
        if (!isset($result['query']['globaluserinfo']['merged'])) {
            return [];
        }
        $out = [];
        foreach ($result['query']['globaluserinfo']['merged'] as $result) {
            $out[] = [
                'dbName' => $result['wiki'],
                'total' => $result['editcount'],
            ];
        }

        // Cache and return.
        $this->stopwatch->stop($cacheKey);
        return $this->setCache($cacheKey, $out);
    }

    /**
     * Get total edit counts from all projects for this user.
     * @see EditCounterRepository::globalEditCountsFromCentralAuth()
     * @param User $user The user.
     * @param Project $project The project to start from.
     * @return mixed[] Elements are arrays with 'dbName' (string), and 'total' (int).
     */
    protected function globalEditCountsFromDatabases(User $user, Project $project)
    {
        $this->log->debug(__METHOD__." Getting global edit counts for ".$user->getUsername());
        $stopwatchName = 'globalRevisionCounts.'.$user->getUsername();
        $allProjects = $project->getRepository()->getAll();
        $topEditCounts = [];
        foreach ($allProjects as $projectMeta) {
            $revisionTableName = $this->getTableName($projectMeta['dbName'], 'revision');
            $sql = "SELECT COUNT(rev_id) FROM $revisionTableName WHERE rev_user_text = :username";

            $resultQuery = $this->executeProjectsQuery($sql, [
                'username' => $user->getUsername()
            ]);
            $total = (int)$resultQuery->fetchColumn();
            $topEditCounts[] = [
                'dbName' => $projectMeta['dbName'],
                'total' => $total,
            ];
            $this->stopwatch->lap($stopwatchName);
        }
        return $topEditCounts;
    }

    /**
     * Get the given user's total edit counts per namespace on the given project.
     * @param Project $project The project.
     * @param User $user The user.
     * @return integer[] Array keys are namespace IDs, values are the edit counts.
     */
    public function getNamespaceTotals(Project $project, User $user)
    {
        // Cache?
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_namespacetotals');
        $this->stopwatch->start($cacheKey, 'XTools');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Query.
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $sql = "SELECT page_namespace, COUNT(rev_id) AS total
            FROM $pageTable p JOIN $revisionTable r ON (r.rev_page = p.page_id)
            WHERE r.rev_user = :id
            GROUP BY page_namespace";

        $results = $this->executeProjectsQuery($sql, [
            'id' => $user->getId($project),
        ])->fetchAll();

        $namespaceTotals = array_combine(array_map(function ($e) {
            return $e['page_namespace'];
        }, $results), array_map(function ($e) {
            return $e['total'];
        }, $results));

        // Cache and return.
        $this->stopwatch->stop($cacheKey);
        return $this->setCache($cacheKey, $namespaceTotals);
    }

    /**
     * Get revisions by this user.
     * @param Project[] $projects The projects.
     * @param User $user The user.
     * @param int $lim The maximum number of revisions to fetch from each project.
     * @return array|mixed
     */
    public function getRevisions($projects, User $user, $lim = 40)
    {
        // Check cache.
        $cacheKey = $this->getCacheKey('ec_globalcontribs.'.$user->getCacheKey().'.'.$lim);
        $this->stopwatch->start($cacheKey, 'XTools');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Assemble queries.
        $queries = [];
        foreach ($projects as $project) {
            $revisionTable = $project->getTableName('revision');
            $pageTable = $project->getTableName('page');
            $sql = "SELECT
                    '".$project->getDatabaseName()."' AS project_name,
                    revs.rev_id AS id,
                    revs.rev_timestamp AS timestamp,
                    UNIX_TIMESTAMP(revs.rev_timestamp) AS unix_timestamp,
                    revs.rev_minor_edit AS minor,
                    revs.rev_deleted AS deleted,
                    revs.rev_len AS length,
                    (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                    revs.rev_parent_id AS parent_id,
                    revs.rev_comment AS comment,
                    revs.rev_user_text AS username,
                    page.page_title,
                    page.page_namespace
                FROM $revisionTable AS revs
                    JOIN $pageTable AS page ON (rev_page = page_id)
                    LEFT JOIN $revisionTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                WHERE revs.rev_user_text = :username
                ORDER BY revs.rev_timestamp DESC";
            if (is_numeric($lim)) {
                $sql .= " LIMIT $lim";
            }
            $queries[] = $sql;
        }
        $sql = "(\n" . join("\n) UNION (\n", $queries) . ")\n";

        $revisions = $this->executeProjectsQuery($sql, [
            'username' => $user->getUsername(),
        ])->fetchAll();

        // Cache and return.
        $this->stopwatch->stop($cacheKey);
        return $this->setCache($cacheKey, $revisions);
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
    public function getMonthCounts(Project $project, User $user)
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_monthcounts');
        $this->stopwatch->start($cacheKey, 'XTools');
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
            . " WHERE rev_user_text = :username"
            . " GROUP BY YEAR(rev_timestamp), MONTH(rev_timestamp), page_namespace";

        $totals = $this->executeProjectsQuery($sql, [
            'username' => $user->getUsername(),
        ])->fetchAll();

        // Cache and return.
        $this->stopwatch->stop($cacheKey);
        return $this->setCache($cacheKey, $totals);
    }

    /**
     * Get data for the timecard chart, with totals grouped by day and to the nearest two-hours.
     * @param Project $project
     * @param User $user
     * @return string[]
     */
    public function getTimeCard(Project $project, User $user)
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_timecard');
        $this->stopwatch->start($cacheKey, 'XTools');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $hourInterval = 2;
        $xCalc = "ROUND(HOUR(rev_timestamp)/$hourInterval) * $hourInterval";
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $sql = "SELECT "
            . "     DAYOFWEEK(rev_timestamp) AS `y`, "
            . "     $xCalc AS `x`, "
            . "     COUNT(rev_id) AS `value` "
            . " FROM $revisionTable"
            . " WHERE rev_user_text = :username"
            . " GROUP BY DAYOFWEEK(rev_timestamp), $xCalc ";

        $totals = $this->executeProjectsQuery($sql, [
            'username' => $user->getUsername(),
        ])->fetchAll();

        // Cache and return.
        $this->stopwatch->stop($cacheKey);
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
    public function getEditSizeData(Project $project, User $user)
    {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_editsizes');
        $this->stopwatch->start($cacheKey, 'XTools');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Prepare the queries and execute them.
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $sql = "SELECT AVG(sizes.size) AS average_size,
                COUNT(CASE WHEN sizes.size < 20 THEN 1 END) AS small_edits,
                COUNT(CASE WHEN sizes.size > 1000 THEN 1 END) AS large_edits
                FROM (
                    SELECT (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS size
                    FROM $revisionTable AS revs
                    LEFT JOIN $revisionTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                    WHERE revs.rev_user = :userId
                    ORDER BY revs.rev_timestamp DESC
                    LIMIT 5000
                ) sizes";

        $results = $this->executeProjectsQuery($sql, [
            'userId' => $user->getId($project),
        ])->fetch();

        // Cache and return.
        $this->stopwatch->stop($cacheKey);
        return $this->setCache($cacheKey, $results);
    }

    /**
     * Get the number of edits this user made using semi-automated tools.
     * @param Project $project
     * @param User $user
     * @return int Result of query, see below.
     */
    public function countAutomatedEdits(Project $project, User $user)
    {
        $autoEditsRepo = new AutoEditsRepository();
        $autoEditsRepo->setContainer($this->container);
        return $autoEditsRepo->countAutomatedEdits($project, $user);
    }
}
