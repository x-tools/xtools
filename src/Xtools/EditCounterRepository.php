<?php

namespace Xtools;

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
        $cacheKey = 'revisioncounts.' . $user->getId($project);
        if ($this->cache->hasItem($cacheKey)) {
            $msg = "Using logged revision counts";
            $this->log->debug($msg, [$project->getDatabaseName(), $user->getUsername()]);
            return $this->cache->getItem($cacheKey)->get();
        }

        // Prepare the queries and execute them.
        $start = microtime();
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
        ];
        $revisionCounts = [];
        foreach ($queries as $varName => $query) {
            $resultQuery = $this->getProjectsConnection()->prepare($query);
            $userId = $user->getId($project);
            $resultQuery->bindParam("userId", $userId);
            $resultQuery->execute();
            $val = $resultQuery->fetchColumn();
            $revisionCounts[$varName] = $val ?: 0;
        }
        $duration = microtime() - $start;
        $this->log->debug(
            "Retrieved revision counts in $duration",
            [$project->getDatabaseName(), $user->getUsername()]
        );

        // Cache for 10 minutes, and return.
        $cacheItem =
            $this->cache->getItem($cacheKey)
                ->set($revisionCounts)
                ->expiresAfter(new \DateInterval('PT10M'));
        $this->cache->save($cacheItem);

        return $revisionCounts;
    }

    /**
     * Get the first and last revision dates (in MySQL YYYYMMDDHHMMSS format).
     * @return string[] With keys 'first' and 'last'.
     */
    public function getRevisionDates(Project $project, User $user)
    {
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
        $out = [];
        foreach ($result as $res) {
            $out[$res['key']] = $res['date'];
        }

        return $out;
    }

    /**
     * Get page counts for the given user.
     * @param User $user
     * @return int[]
     */
    public function getPageCounts(Project $project, User $user)
    {
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $archiveTable = $this->getTableName($project->getDatabaseName(), 'archive');
        $loggingTable = $this->getTableName($project->getDatabaseName(), 'logging');
        $resultQuery = $this->getProjectsConnection()->prepare("
            (SELECT 'edited-total' as source, COUNT(rev_page) as value
                FROM $revisionTable where rev_user_text=:username)
            UNION
            (SELECT 'edited-unique' as source, COUNT(distinct rev_page) as value
                FROM $revisionTable where rev_user_text=:username)
            UNION
            (SELECT 'created-live' as source, COUNT(*) as value from $revisionTable
                WHERE rev_user_text=:username and rev_parent_id=0)
            UNION
            (SELECT 'created-deleted' as source, COUNT(*) as value from $archiveTable
                WHERE ar_user_text=:username and ar_parent_id=0)
            UNION
            (SELECT 'moved' as source, count(*) as value from $loggingTable
                WHERE log_type='move' and log_action='move' and log_user_text=:username)
            ");
        $username = $user->getUsername();
        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();
        $results = $resultQuery->fetchAll();

        $pageCounts = array_combine(array_map(function ($e) {
            return $e['source'];
        }, $results), array_map(function ($e) {
            return $e['value'];
        }, $results));

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

        return $logCounts;
    }

    /**
     * Get a user's total edit count on one or more project.
     * Requires the CentralAuth extension to be installed on the project.
     *
     * @param string $username The username.
     * @param Project $project The project.
     * @return mixed[]|boolean Array of total edit counts, or false if none could be found.
     */
    public function getRevisionCountsAllProjects($username, Project $project)
    {
        $api = $this->getMediawikiApi($project);
        $params = [
            'meta' => 'globaluserinfo',
            'guiprop' => 'editcount|merged',
            'guiuser' => $username,
        ];
        $query = new SimpleRequest('query', $params);
        $result = $api->getRequest($query);
        if (!isset($result['query']['globaluserinfo']['merged'])) {
            return false;
        }
        $out = [];
        foreach ($result['query']['globaluserinfo']['merged'] as $merged) {
            // The array structure here should match what's done in
            // EditCounterHelper::getTopProjectsEditCounts()
            $out[$merged['wiki']] = [
                'total' => $merged['editcount'],
            ];
        }

        return $out;
    }

    /**
     * Get total edit counts for the top 10 projects for this user.
     * @param string $username The username.
     * @return string[] Elements are arrays with 'dbName', 'url', 'name', and 'total'.
     */
    public function getTopProjectsEditCounts($projectUrl, $username, $numProjects = 10)
    {
        $this->debug("Getting top project edit counts for $username");
        $cacheKey = 'topprojectseditcounts.' . $username;
        if ($this->cacheHas($cacheKey)) {
            return $this->cacheGet($cacheKey);
        }

        // First try to get the edit count from the API (if CentralAuth is installed).
        /** @var ApiHelper */
        $api = $this->container->get('app.api_helper');
        $topEditCounts = $api->getEditCount($username, $projectUrl);
        if (false === $topEditCounts) {
            // If no CentralAuth, fall back to querying each database in turn.
            foreach ($this->labsHelper->getProjectsInfo() as $project) {
                $this->container->get('logger')->debug('Getting edit count for ' . $project['url']);
                $revisionTableName = $this->labsHelper->getTable('revision', $project['dbName']);
                $sql = "SELECT COUNT(rev_id) FROM $revisionTableName WHERE rev_user_text=:username";
                $stmt = $this->replicas->prepare($sql);
                $stmt->bindParam("username", $username);
                $stmt->execute();
                $total = (int)$stmt->fetchColumn();
                $topEditCounts[$project['dbName']] = array_merge($project, ['total' => $total]);
            }
        }
        uasort($topEditCounts, function ($a, $b) {
            return $b['total'] - $a['total'];
        });
        $out = array_slice($topEditCounts, 0, $numProjects);

        // Cache for ten minutes.
        $this->cacheSave($cacheKey, $out, 'PT10M');

        return $out;
    }

    /**
     * Get the given user's total edit counts per namespace.
     * @param string $username The username of the user.
     * @return integer[] Array keys are namespace IDs, values are the edit counts.
     */
    public function getNamespaceTotals($username)
    {
        $userId = $this->getUserId($username);
        $sql = "SELECT page_namespace, count(rev_id) AS total
            FROM " . $this->labsHelper->getTable('revision') . " r
                JOIN " . $this->labsHelper->getTable('page') . " p on r.rev_page = p.page_id
            WHERE r.rev_user = :id GROUP BY page_namespace";
        $resultQuery = $this->replicas->prepare($sql);
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
    public function getMonthCounts($username)
    {
        $cacheKey = "monthcounts.$username";
        if ($this->cacheHas($cacheKey)) {
            return $this->cacheGet($cacheKey);
        }

        $sql =
            "SELECT " . "     YEAR(rev_timestamp) AS `year`," .
            "     MONTH(rev_timestamp) AS `month`," . "     page_namespace," .
            "     COUNT(rev_id) AS `count` " . " FROM " . $this->labsHelper->getTable('revision') .
            "    JOIN " . $this->labsHelper->getTable('page') . " ON (rev_page = page_id)" .
            " WHERE rev_user_text = :username" .
            " GROUP BY YEAR(rev_timestamp), MONTH(rev_timestamp), page_namespace " .
            " ORDER BY rev_timestamp DESC";
        $resultQuery = $this->replicas->prepare($sql);
        $resultQuery->bindParam(":username", $username);
        $resultQuery->execute();
        $totals = $resultQuery->fetchAll();
        $out = [
            'years' => [],
            'namespaces' => [],
            'totals' => [],
        ];
        $out['max_year'] = 0;
        $out['min_year'] = date('Y');
        foreach ($totals as $total) {
            // Collect all applicable years and namespaces.
            $out['max_year'] = max($out['max_year'], $total['year']);
            $out['min_year'] = min($out['min_year'], $total['year']);
            // Collate the counts by namespace, and then year and month.
            $ns = $total['page_namespace'];
            if (!isset($out['totals'][$ns])) {
                $out['totals'][$ns] = [];
            }
            $out['totals'][$ns][$total['year'] . $total['month']] = $total['count'];
        }
        // Fill in the blanks (where no edits were made in a given month for a namespace).
        for ($y = $out['min_year']; $y <= $out['max_year']; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                foreach ($out['totals'] as $nsId => &$total) {
                    if (!isset($total[$y . $m])) {
                        $total[$y . $m] = 0;
                    }
                }
            }
        }
        $this->cacheSave($cacheKey, $out, 'PT10M');

        return $out;
    }

    /**
     * Get yearly edit totals for this user, grouped by namespace.
     * @param string $username
     * @return string[] ['<namespace>' => ['<year>' => 'total', ... ], ... ]
     */
    public function getYearCounts($username)
    {
        $cacheKey = "yearcounts.$username";
        if ($this->cacheHas($cacheKey)) {
            return $this->cacheGet($cacheKey);
        }

        $sql =
            "SELECT " . "     SUBSTR(CAST(rev_timestamp AS CHAR(4)), 1, 4) AS `year`," .
            "     page_namespace," . "     COUNT(rev_id) AS `count` " . " FROM " .
            $this->labsHelper->getTable('revision') . "    JOIN " .
            $this->labsHelper->getTable('page') . " ON (rev_page = page_id)" .
            " WHERE rev_user_text = :username" .
            " GROUP BY SUBSTR(CAST(rev_timestamp AS CHAR(4)), 1, 4), page_namespace " .
            " ORDER BY rev_timestamp DESC ";
        $resultQuery = $this->replicas->prepare($sql);
        $resultQuery->bindParam(":username", $username);
        $resultQuery->execute();
        $totals = $resultQuery->fetchAll();
        $out = [
            'years' => [],
            'namespaces' => [],
            'totals' => [],
        ];
        foreach ($totals as $total) {
            $out['years'][$total['year']] = $total['year'];
            $out['namespaces'][$total['page_namespace']] = $total['page_namespace'];
            if (!isset($out['totals'][$total['page_namespace']])) {
                $out['totals'][$total['page_namespace']] = [];
            }
            $out['totals'][$total['page_namespace']][$total['year']] = $total['count'];
        }
        $this->cacheSave($cacheKey, $out, 'PT10M');

        return $out;
    }

    /**
     * Get data for the timecard chart, with totals grouped by day and to the nearest two-hours.
     * @param string $username The user's username.
     * @return string[]
     */
    public function getTimeCard($username)
    {
        $cacheKey = "timecard.$username";
        if ($this->cacheHas($cacheKey)) {
            return $this->cacheGet($cacheKey);
        }

        $hourInterval = 2;
        $xCalc = "ROUND(HOUR(rev_timestamp)/$hourInterval)*$hourInterval";
        $sql =
            "SELECT " . "     DAYOFWEEK(rev_timestamp) AS `y`, " . "     $xCalc AS `x`, " .
            "     COUNT(rev_id) AS `r` " . " FROM " . $this->labsHelper->getTable('revision') .
            " WHERE rev_user_text = :username" . " GROUP BY DAYOFWEEK(rev_timestamp), $xCalc " .
            " ";
        $resultQuery = $this->replicas->prepare($sql);
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
        $this->cacheSave($cacheKey, $totals, 'PT10M');

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
