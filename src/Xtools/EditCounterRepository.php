<?php
/**
 * This file contains only the EditCounterRepository class.
 */

namespace Xtools;

use AppBundle\Helper\AutomatedEditsHelper;
use DateInterval;
use DateTime;
use Mediawiki\Api\SimpleRequest;

/**
 * An EditCounterRepository is responsible for retrieving edit count information from the
 * databases and API. It doesn't do any post-processing of that information.
 */
class EditCounterRepository extends Repository
{

    /**
     * Get data about revisions, pages, etc.
     * @param Project $project The project.
     * @param User $user The user.
     * @returns string[] With keys: 'deleted', 'live', 'total', 'first', 'last', '24h', '7d', '30d',
     * '365d', 'small', 'large', 'with_comments', and 'minor_edits', ...
     */
    public function getPairData(Project $project, User $user)
    {
        // Set up cache.
        $cacheKey = 'pairdata.' . $project->getDatabaseName() . '.' . $user->getUsername();
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Prepare the queries and execute them.
        $archiveTable = $this->getTableName($project->getDatabaseName(), 'archive');
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $queries = "

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
            SELECT 'small' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_user = :userId AND rev_len < 20
            ) UNION (
            SELECT 'large' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_user = :userId AND rev_len > 1000
            ) UNION (
            SELECT 'with_comments' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_user = :userId AND rev_comment = ''
            ) UNION (
            SELECT 'minor' AS `key`, COUNT(rev_id) AS val FROM $revisionTable
                WHERE rev_user = :userId AND rev_minor_edit = 1
            ) UNION (
            SELECT 'average_size' AS `key`, AVG(rev_len) AS val FROM $revisionTable
                WHERE rev_user = :userId

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
                WHERE rev_user = :userId AND rev_parent_id=0
            ) UNION (
            SELECT 'created-deleted' AS `key`, COUNT(DISTINCT ar_page_id) AS `val`
                FROM $archiveTable
                WHERE ar_user = :userId AND ar_parent_id=0
            )
        ";
        $resultQuery = $this->getProjectsConnection()->prepare($queries);
        $userId = $user->getId($project);
        $resultQuery->bindParam("userId", $userId);
        $resultQuery->execute();
        $revisionCounts = [];
        while ($result = $resultQuery->fetch()) {
            $revisionCounts[$result['key']] = $result['val'];
        }

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
                ->set($revisionCounts)
                ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);

        return $revisionCounts;
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
        $cacheKey = 'logcounts.'.$project->getDatabaseName().'.'.$user->getUsername();
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $this->stopwatch->start($cacheKey, 'XTools');

        // Query.
        $userNamespaceId = 2;
        $loggingTable = $this->getTableName($project->getDatabaseName(), 'logging');
        $sql = "
        (SELECT CONCAT(log_type, '-', log_action) AS source, COUNT(log_id) AS value
            FROM $loggingTable
            WHERE log_user = :userId
            GROUP BY log_type, log_action
        ) UNION
        (SELECT 'users-unblocked' AS source, COUNT(DISTINCT log_title) AS value
            FROM $loggingTable
            WHERE log_user = :userId
                AND log_type = 'block'
                AND log_action = 'unblock'
                AND log_namespace = $userNamespaceId
        )";
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $userId = $user->getId($project);
        $resultQuery->bindParam('userId', $userId);
        $resultQuery->execute();
        $results = $resultQuery->fetchAll();
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
            'patrol-patrol',
            'block-block',
            'block-reblock',
            'block-unblock',
            'users-unblocked', // Second query above.
            'protect-protect',
            'protect-unprotect',
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
            $sql = "SELECT COUNT(log_id) FROM commonswiki_p.logging_userindex
                WHERE log_type = 'upload' AND log_action = 'upload' AND log_user = :userId";
            $resultQuery = $this->getProjectsConnection()->prepare($sql);
            $resultQuery->bindParam('userId', $userId);
            $resultQuery->execute();
            $logCounts['files_uploaded_commons'] = $resultQuery->fetchColumn();
        }

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($logCounts)
            ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);
        $this->stopwatch->stop($cacheKey);

        return $logCounts;
    }

    /**
     * Get data for all blocks set by the given user.
     * @param Project $project
     * @param User $user
     * @return array
     */
    public function getBlocksSet(Project $project, User $user)
    {
        $ipblocksTable = $this->getTableName($project->getDatabaseName(), 'ipblocks');
        $sql = "SELECT * FROM $ipblocksTable WHERE ipb_by = :userId";
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $userId = $user->getId($project);
        $resultQuery->bindParam('userId', $userId);
        $resultQuery->execute();
        return $resultQuery->fetchAll();
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
        $sql = "SELECT log_timestamp, log_params FROM $loggingTable
                WHERE log_type = 'block'
                AND log_action = 'block'
                AND log_timestamp > 0
                AND log_title = :username";
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $username = str_replace(' ', '_', $user->getUsername());
        $resultQuery->bindParam('username', $username);
        $resultQuery->execute();
        return $resultQuery->fetchAll();
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
        $this->log->debug(__METHOD__." Getting global edit counts for ".$user->getUsername());
        // Set up cache and stopwatch.
        $cacheKey = 'globalRevisionCounts.'.$user->getUsername();
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $this->stopwatch->start($cacheKey, 'XTools');

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

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($out)
            ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);
        $this->stopwatch->stop($cacheKey);

        return $out;
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
        $stopwatchName = 'globalRevisionCounts.'.$user->getUsername();
        $allProjects = $project->getRepository()->getAll();
        $topEditCounts = [];
        foreach ($allProjects as $projectMeta) {
            $revisionTableName = $this->getTableName($projectMeta['dbName'], 'revision');
            $sql = "SELECT COUNT(rev_id) FROM $revisionTableName WHERE rev_user_text=:username";
            $stmt = $this->getProjectsConnection()->prepare($sql);
            $stmt->bindParam('username', $user->getUsername());
            $stmt->execute();
            $total = (int)$stmt->fetchColumn();
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
        $userId = $user->getId($project);
        $cacheKey = "ec.namespacetotals.{$project->getDatabaseName()}.$userId";
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
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam(":id", $userId);
        $resultQuery->execute();
        $results = $resultQuery->fetchAll();
        $namespaceTotals = array_combine(array_map(function ($e) {
            return $e['page_namespace'];
        }, $results), array_map(function ($e) {
            return $e['total'];
        }, $results));

        // Cache and return.
        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set($namespaceTotals);
        $cacheItem->expiresAfter(new DateInterval('PT15M'));
        $this->cache->save($cacheItem);
        $this->stopwatch->stop($cacheKey);
        return $namespaceTotals;
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
        $username = $user->getUsername();
        $cacheKey = "globalcontribs.$username";
        $this->stopwatch->start($cacheKey, 'XTools');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Assemble queries.
        $queries = [];
        foreach ($projects as $project) {
            $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
            $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
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
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam(":username", $username);
        $resultQuery->execute();
        $revisions = $resultQuery->fetchAll();

        // Cache this.
        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set($revisions);
        $cacheItem->expiresAfter(new DateInterval('PT15M'));
        $this->cache->save($cacheItem);

        $this->stopwatch->stop($cacheKey);
        return $revisions;
    }

    /**
     * Get data for a bar chart of monthly edit totals per namespace.
     * @param Project $project The project.
     * @param User $user The user.
     * @return string[]
     */
    public function getMonthCounts(Project $project, User $user)
    {
        $username = $user->getUsername();
        $cacheKey = "monthcounts.$username";
        $this->stopwatch->start($cacheKey, 'XTools');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $sql =
            "SELECT "
            . "     YEAR(rev_timestamp) AS `year`,"
            . "     MONTH(rev_timestamp) AS `month`,"
            . "     page_namespace,"
            . "     COUNT(rev_id) AS `count` "
            .  " FROM $revisionTable JOIN $pageTable ON (rev_page = page_id)"
            . " WHERE rev_user_text = :username"
            . " GROUP BY YEAR(rev_timestamp), MONTH(rev_timestamp), page_namespace "
            . " ORDER BY rev_timestamp DESC";
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam(":username", $username);
        $resultQuery->execute();
        $totals = $resultQuery->fetchAll();

        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->expiresAfter(new DateInterval('PT10M'));
        $cacheItem->set($totals);
        $this->cache->save($cacheItem);

        $this->stopwatch->stop($cacheKey);
        return $totals;
    }

    /**
     * Get yearly edit totals for this user, grouped by namespace.
     * @param Project $project The project.
     * @param User $user The user.
     * @return string[] ['<namespace>' => ['<year>' => 'total', ... ], ... ]
     */
    public function getYearCounts(Project $project, User $user)
    {
        $username = $user->getUsername();
        $cacheKey = "yearcounts.$username";
        $this->stopwatch->start($cacheKey, 'XTools');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $sql = "SELECT "
            . "     YEAR(rev_timestamp) AS `year`,"
            . "     page_namespace,"
            . "     COUNT(rev_id) AS `count` "
            . " FROM $revisionTable JOIN $pageTable ON (rev_page = page_id)"
            . " WHERE rev_user_text = :username"
            . " GROUP BY YEAR(rev_timestamp), page_namespace "
            . " ORDER BY rev_timestamp DESC ";
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam(":username", $username);
        $resultQuery->execute();
        $totals = $resultQuery->fetchAll();

        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set($totals);
        $cacheItem->expiresAfter(new DateInterval('P10M'));
        $this->cache->save($cacheItem);

        $this->stopwatch->stop($cacheKey);
        return $totals;
    }

    /**
     * Get data for the timecard chart, with totals grouped by day and to the nearest two-hours.
     * @param Project $project
     * @param User $user
     * @return string[]
     */
    public function getTimeCard(Project $project, User $user)
    {
        $username = $user->getUsername();
        $cacheKey = "timecard.".$username;
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
            . "     COUNT(rev_id) AS `r` "
            . " FROM $revisionTable"
            . " WHERE rev_user_text = :username"
            . " GROUP BY DAYOFWEEK(rev_timestamp), $xCalc ";
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam(":username", $username);
        $resultQuery->execute();
        $totals = $resultQuery->fetchAll();
        // Scale the radii: get the max, then scale each radius.
        // This looks inefficient, but there's a max of 72 elements in this array.
        $max = 0;
        foreach ($totals as $total) {
            $max = max($max, $total['r']);
        }
        foreach ($totals as &$total) {
            $total['r'] = round($total['r'] / $max * 100);
        }
        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->expiresAfter(new DateInterval('PT10M'));
        $cacheItem->set($totals);
        $this->cache->save($cacheItem);

        $this->stopwatch->stop($cacheKey);
        return $totals;
    }

    /**
     * Get a summary of automated edits made by the given user in their last 1000 edits.
     * Will cache the result for 10 minutes.
     * @param Project $project The project.
     * @param User $user The user.
     * @return integer[] Array of edit counts, keyed by all tool names from
     * app/config/semi_automated.yml
     * @TODO This currently uses AutomatedEditsHelper but that could probably be refactored.
     */
    public function countAutomatedRevisions(Project $project, User $user)
    {
        $userId = $user->getId($project);
        $cacheKey = "automatedEdits.".$project->getDatabaseName().'.'.$userId;
        $this->stopwatch->start($cacheKey, 'XTools');
        if ($this->cache->hasItem($cacheKey)) {
            $this->log->debug("Using cache for $cacheKey");
            return $this->cache->getItem($cacheKey)->get();
        }

        /** @var AutomatedEditsHelper $automatedEditsHelper */
        $automatedEditsHelper = $this->container->get("app.automated_edits_helper");

        // Get the most recent 1000 edit summaries.
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $sql = "SELECT rev_comment FROM $revisionTable
            WHERE rev_user=:userId ORDER BY rev_timestamp DESC LIMIT 1000";
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam("userId", $userId);
        $resultQuery->execute();
        $results = $resultQuery->fetchAll();
        $editCounts = [];
        foreach ($results as $result) {
            $toolName = $automatedEditsHelper->getTool($result['rev_comment']);
            if ($toolName) {
                if (!isset($editCounts[$toolName])) {
                    $editCounts[$toolName] = 0;
                }
                $editCounts[$toolName]++;
            }
        }
        arsort($editCounts);

        // Cache for 10 minutes.
        $this->log->debug("Saving $cacheKey to cache", [$editCounts]);
        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set($editCounts);
        $cacheItem->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);

        $this->stopwatch->stop($cacheKey);
        return $editCounts;
    }
}
