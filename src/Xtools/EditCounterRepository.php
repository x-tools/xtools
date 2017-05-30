<?php

namespace Xtools;

use DateInterval;
use Mediawiki\Api\SimpleRequest;

class EditCounterRepository extends Repository
{

    /**
     * Get revision counts for the given user.
     * @param User $user The user.
     * @returns string[] With keys: 'deleted', 'live', 'total', 'first', 'last', '24h', '7d', '30d',
     * '365d', 'small', 'large', 'with_comments', and 'minor_edits'.
     */
    public function getRevisionCounts(Project $project, User $user)
    {
        // Set up cache.
        $cacheKey = 'revisioncounts.' . $project->getDatabaseName() . '.' . $user->getUsername();
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Prepare the queries and execute them.
        $archiveTable = $this->getTableName($project->getDatabaseName(), 'archive');
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $queries = [
            'deleted' => "SELECT COUNT(ar_id) FROM $archiveTable
                WHERE ar_user = :userId",
            'live' => "SELECT COUNT(rev_id) FROM $revisionTable
                WHERE rev_user = :userId",
            'day' => "SELECT COUNT(rev_id) FROM $revisionTable
                WHERE rev_user = :userId AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            'week' => "SELECT COUNT(rev_id) FROM $revisionTable
                WHERE rev_user = :userId AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
            'month' => "SELECT COUNT(rev_id) FROM $revisionTable
                WHERE rev_user = :userId AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            'year' => "SELECT COUNT(rev_id) FROM $revisionTable
                WHERE rev_user = :userId AND rev_timestamp >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
            'small' => "SELECT COUNT(rev_id) FROM $revisionTable
                WHERE rev_user = :userId AND rev_len < 20",
            'large' => "SELECT COUNT(rev_id) FROM $revisionTable
                WHERE rev_user = :userId AND rev_len > 1000",
            'with_comments' => "SELECT COUNT(rev_id) FROM $revisionTable
                WHERE rev_user = :userId AND rev_comment = ''",
            'minor' => "SELECT COUNT(rev_id) FROM $revisionTable
                WHERE rev_user = :userId AND rev_minor_edit = 1",
            'average_size' => "SELECT AVG(rev_len) FROM $revisionTable
                WHERE rev_user = :userId",
        ];
        $this->stopwatch->start($cacheKey);
        $revisionCounts = [];
        foreach ($queries as $varName => $query) {
            $resultQuery = $this->getProjectsConnection()->prepare($query);
            $userId = $user->getId($project);
            $resultQuery->bindParam("userId", $userId);
            $resultQuery->execute();
            $val = $resultQuery->fetchColumn();
            $revisionCounts[$varName] = $val ?: 0;
            $this->stopwatch->lap($cacheKey);
        }

        // Cache for 10 minutes, and return.
        $this->stopwatch->stop($cacheKey);
        $cacheItem = $this->cache->getItem($cacheKey)
                ->set($revisionCounts)
                ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);

        return $revisionCounts;
    }

    /**
     * Get the first and last revision dates (in MySQL YYYYMMDDHHMMSS format).
     * @return string[] With keys 'first' and 'last'.
     */
    public function getRevisionDates(Project $project, User $user)
    {
        // Set up cache.
        $cacheKey = 'revisiondates.' . $project->getDatabaseName() . '.' . $user->getUsername();
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $this->stopwatch->start($cacheKey);
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $query = "(SELECT 'first' AS `key`, rev_timestamp AS `date` FROM $revisionTable
            WHERE rev_user = :userId ORDER BY rev_timestamp ASC LIMIT 1)
            UNION
            (SELECT 'last' AS `key`, rev_timestamp AS `date` FROM $revisionTable
            WHERE rev_user = :userId ORDER BY rev_timestamp DESC LIMIT 1)";
        $resultQuery = $this->getProjectsConnection()->prepare($query);
        $userId = $user->getId($project);
        $resultQuery->bindParam("userId", $userId);
        $resultQuery->execute();
        $result = $resultQuery->fetchAll();
        $revisionDates = [];
        foreach ($result as $res) {
            $revisionDates[$res['key']] = $res['date'];
        }

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
                ->set($revisionDates)
                ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);
        $this->stopwatch->stop($cacheKey);
        return $revisionDates;
    }

    /**
     * Get page counts for the given user, both for live and deleted pages/revisions.
     * @param Project $project The project.
     * @param User $user The user.
     * @return int[] With keys: edited-live, edited-deleted, created-live, created-deleted.
     */
    public function getPageCounts(Project $project, User $user)
    {
        // Set up cache.
        $cacheKey = 'pagecounts.'.$project->getDatabaseName().'.'.$user->getUsername();
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $this->stopwatch->start($cacheKey, 'XTools');

        // Build and execute query.
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $archiveTable = $this->getTableName($project->getDatabaseName(), 'archive');
        $resultQuery = $this->getProjectsConnection()->prepare("
            (SELECT 'edited-live' AS source, COUNT(DISTINCT rev_page) AS value
                FROM $revisionTable
                WHERE rev_user_text=:username)
            UNION
            (SELECT 'edited-deleted' AS source, COUNT(DISTINCT ar_page_id) AS value
                FROM $archiveTable
                WHERE ar_user_text=:username)
            UNION
            (SELECT 'created-live' AS source, COUNT(DISTINCT rev_page) AS value
                FROM $revisionTable
                WHERE rev_user_text=:username AND rev_parent_id=0)
            UNION
            (SELECT 'created-deleted' AS source, COUNT(DISTINCT ar_page_id) AS value
                FROM $archiveTable
                WHERE ar_user_text=:username AND ar_parent_id=0)
            ");
        $username = $user->getUsername();
        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();
        $results = $resultQuery->fetchAll();

        $pageCounts = array_combine(
            array_map(function ($e) {
                return $e['source'];
            }, $results),
            array_map(function ($e) {
                return $e['value'];
            }, $results)
        );

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($pageCounts)
            ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);
        $this->stopwatch->stop($cacheKey);
        return $pageCounts;
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
        $sql = "SELECT CONCAT(log_type, '-', log_action) AS source, COUNT(log_id) AS value
            FROM " . $this->getTableName($project->getDatabaseName(), 'logging') . "
            WHERE log_user = :userId
            GROUP BY log_type, log_action";
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
            'block-unblock',
            'protect-protect',
            'protect-unprotect',
            'move-move',
            'delete-delete',
            'delete-revision',
            'delete-restore',
            'import-import',
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
     * Get a user's total edit count on one or more project.
     * Requires the CentralAuth extension to be installed on the project.
     *
     * @param string $username The username.
     * @param Project $project The project to start from.
     * @return mixed[]|boolean Array of total edit counts, or false if none could be found.
     */
    public function getRevisionCountsAllProjects(User $user, Project $project)
    {
        // Set up cache and stopwatch.
        $cacheKey = 'globalRevisionCounts.'.$user->getUsername();
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $this->stopwatch->start($cacheKey, 'XTools');

        $api = $this->getMediawikiApi($project);
        $params = [
            'meta' => 'globaluserinfo',
            'guiprop' => 'editcount|merged',
            'guiuser' => $user->getUsername(),
        ];
        $query = new SimpleRequest('query', $params);
        $result = $api->getRequest($query);
        $out = [];
        if (isset($result['query']['globaluserinfo']['merged'])) {
            foreach ($result['query']['globaluserinfo']['merged'] as $merged) {
                $proj = ProjectRepository::getProject($merged['wiki'], $this->container);
                $out[] = [
                    'project' => $proj,
                    'total' => $merged['editcount'],
                ];
            }
        }
        if (empty($out)) {
            $out = $this->getRevisionCountsAllProjectsNoCentralAuth($user, $project, $cacheKey);
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
     * Get total edit counts for the top 10 projects for this user.
     * @param string $username The username.
     * @return string[] Elements are arrays with 'dbName', 'url', 'name', and 'total'.
     */
    protected function getRevisionCountsAllProjectsNoCentralAuth(
        User $user,
        Project $project,
        $stopwatchName
    ) {
        $allProjects = $project->getRepository()->getAll();
        $topEditCounts = [];
        foreach ($allProjects as $projectMeta) {
            $revisionTableName = $this->getTableName($projectMeta['dbName'], 'revision');
            $sql = "SELECT COUNT(rev_id) FROM $revisionTableName WHERE rev_user_text=:username";
            $stmt = $this->getProjectsConnection()->prepare($sql);
            $stmt->bindParam("username", $user->getUsername());
            $stmt->execute();
            $total = (int)$stmt->fetchColumn();
            $project = ProjectRepository::getProject($projectMeta['dbName'], $this->container);
            $topEditCounts[] = [
                'project' => $project,
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
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $sql = "SELECT page_namespace, COUNT(rev_id) AS total
            FROM $revisionTable r JOIN $pageTable p on r.rev_page = p.page_id
            WHERE r.rev_user = :id
            GROUP BY page_namespace";
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $userId = $user->getId($project);
        $resultQuery->bindParam(":id", $userId);
        $resultQuery->execute();
        $results = $resultQuery->fetchAll();
        $namespaceTotals = array_combine(array_map(function ($e) {
            return $e['page_namespace'];
        }, $results), array_map(function ($e) {
            return $e['total'];
        }, $results));
        return $namespaceTotals;
    }

    /**
     * Get this user's most recent 10 edits across all projects.
     * @param string $username The username.
     * @param integer $topN The number of items to return.
     * @param integer $days The number of days to search from each wiki.
     * @return string[]
     */
    public function getRecentGlobalContribs($username, $projects = [], $topN = 10, $days = 30)
    {
        $allRevisions = [];
        foreach ($this->labsHelper->getProjectsInfo($projects) as $project) {
            $cacheKey = "globalcontribs.{$project['dbName']}.$username";
            if ($this->cacheHas($cacheKey)) {
                $revisions = $this->cacheGet($cacheKey);
            } else {
                $sql =
                    "SELECT rev_id, rev_timestamp, UNIX_TIMESTAMP(rev_timestamp) AS unix_timestamp, " .
                    " rev_minor_edit, rev_deleted, rev_len, rev_parent_id, rev_comment, " .
                    " page_title, page_namespace " . " FROM " .
                    $this->labsHelper->getTable('revision', $project['dbName']) . "    JOIN " .
                    $this->labsHelper->getTable('page', $project['dbName']) .
                    "    ON (rev_page = page_id)" .
                    " WHERE rev_timestamp > NOW() - INTERVAL $days DAY AND rev_user_text LIKE :username" .
                    " ORDER BY rev_timestamp DESC" . " LIMIT 10";
                $resultQuery = $this->replicas->prepare($sql);
                $resultQuery->bindParam(":username", $username);
                $resultQuery->execute();
                $revisions = $resultQuery->fetchAll();
                $this->cacheSave($cacheKey, $revisions, 'PT15M');
            }
            if (count($revisions) === 0) {
                continue;
            }
            $revsWithProject = array_map(function (&$item) use ($project) {
                $item['project_name'] = $project['wikiName'];
                $item['project_url'] = $project['url'];
                $item['project_db_name'] = $project['dbName'];
                $item['rev_time_formatted'] = date('Y-m-d H:i', $item['unix_timestamp']);

                return $item;
            }, $revisions);
            $allRevisions = array_merge($allRevisions, $revsWithProject);
        }
        usort($allRevisions, function ($a, $b) {
            return $b['rev_timestamp'] - $a['rev_timestamp'];
        });

        return array_slice($allRevisions, 0, $topN);
    }

    /**
     * Get data for a bar chart of monthly edit totals per namespace.
     * @param string $username The username.
     * @return string[]
     */
    public function getMonthCounts(Project $project, User $user)
    {
        $username = $user->getUsername();
        $cacheKey = "monthcounts.$username";
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $sql =
            "SELECT " . "     YEAR(rev_timestamp) AS `year`," .
            "     MONTH(rev_timestamp) AS `month`," . "     page_namespace," .
            "     COUNT(rev_id) AS `count` " 
            . " FROM $revisionTable    JOIN $pageTable ON (rev_page = page_id)" .
            " WHERE rev_user_text = :username" .
            " GROUP BY YEAR(rev_timestamp), MONTH(rev_timestamp), page_namespace " .
            " ORDER BY rev_timestamp DESC";
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam(":username", $username);
        $resultQuery->execute();
        $totals = $resultQuery->fetchAll();
        
        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->expiresAfter(new DateInterval('PT10M'));
        $cacheItem->set($totals);
        $this->cache->save($cacheItem);

        return $totals;
    }

    /**
     * Get yearly edit totals for this user, grouped by namespace.
     * @param string $username
     * @return string[] ['<namespace>' => ['<year>' => 'total', ... ], ... ]
     */
    public function getYearCounts(Project $project, User $user)
    {
        $username = $user->getUsername();
        $cacheKey = "yearcounts.$username";
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $sql = "SELECT "
            . "     SUBSTR(CAST(rev_timestamp AS CHAR(4)), 1, 4) AS `year`,"
            . "     page_namespace,"
            . "     COUNT(rev_id) AS `count` "
            . " FROM $revisionTable    JOIN $pageTable ON (rev_page = page_id)"
            . " WHERE rev_user_text = :username"
            . " GROUP BY SUBSTR(CAST(rev_timestamp AS CHAR(4)), 1, 4), page_namespace "
            . " ORDER BY rev_timestamp DESC ";
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam(":username", $username);
        $resultQuery->execute();
        $totals = $resultQuery->fetchAll();

        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set($totals);
        $cacheItem->expiresAfter(new DateInterval('P10M'));
        $this->cache->save($cacheItem);

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

        return $totals;
    }

    /**
     * Get a summary of automated edits made by the given user in their last 1000 edits.
     * Will cache the result for 10 minutes.
     * @param User $user The user.
     * @return integer[] Array of edit counts, keyed by all tool names from
     * app/config/semi_automated.yml
     * @TODO this is broke
     */
    public function countAutomatedRevisions(Project $project, User $user)
    {
        $userId = $user->getId($project);
        $cacheKey = "automatedEdits.".$project->getDatabaseName().'.'.$userId;
        if ($this->cache->hasItem($cacheKey)) {
            $this->log->debug("Using cache for $cacheKey");
            return $this->cache->getItem($cacheKey)->get();
        }

        // Get the most recent 1000 edit summaries.
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $sql = "SELECT rev_comment FROM $revisionTable
            WHERE rev_user=:userId ORDER BY rev_timestamp DESC LIMIT 1000";
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam("userId", $userId);
        $resultQuery->execute();
        $results = $resultQuery->fetchAll();
        $out = [];
        foreach ($results as $result) {
            $toolName = $this->getTool($result['rev_comment']);
            if ($toolName) {
                if (!isset($out[$toolName])) {
                    $out[$toolName] = 0;
                }
                $out[$toolName]++;
            }
        }
        arsort($out);

        // Cache for 10 minutes.
        $this->log->debug("Saving $cacheKey to cache", [$out]);
        $this->cacheSave($cacheKey, $out, 'PT10M');

        return $out;
    }
}
