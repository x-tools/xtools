<?php

namespace AppBundle\Helper;

use AppBundle\Twig\AppExtension;
use DateInterval;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\VarDumper\VarDumper;

class EditCounterHelper extends HelperBase
{

    /** @var Container */
    protected $container;

    /** @var Connection */
    protected $replicas;

    /** @var LabsHelper */
    protected $labsHelper;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->replicas = $container->get('doctrine')->getManager('replicas')->getConnection();
        $this->labsHelper = $container->get('app.labs_helper');
    }

    /**
     * Get the ID of a user.
     * @param string $usernameOrIp The username or IP address.
     * @return integer The user's ID.
     */
    public function getUserId($usernameOrIp)
    {
        $userTable = $this->labsHelper->getTable('user');
        $sql = "SELECT user_id FROM $userTable WHERE user_name = :username LIMIT 1";
        $resultQuery = $this->replicas->prepare($sql);
        $resultQuery->bindParam("username", $usernameOrIp);
        $resultQuery->execute();
        $userId = (int)$resultQuery->fetchColumn();
        return $userId;
    }

    /**
     * Get total edit counts for the top 10 projects for this user.
     * @param string $username The username.
     * @return string[] Elements are arrays with 'dbName', 'url', 'name', and 'total'.
     */
    public function getTopProjectsEditCounts($username, $numProjects = 10)
    {
        $topEditCounts = [];
        foreach ($this->labsHelper->allProjects() as $project) {
            // Get total edit count from DB otherwise.
            $revisionTableName = $this->labsHelper->getTable('revision', $project['dbName']);
            $sql = "SELECT COUNT(rev_id) FROM $revisionTableName WHERE rev_user_text=:username";
            $stmt = $this->replicas->prepare($sql);
            $stmt->bindParam("username", $username);
            $stmt->execute();
            $total = (int)$stmt->fetchColumn();
            $topEditCounts[$project['dbName']] = array_merge($project, ['total' => $total]);
        }
        uasort($topEditCounts, function ($a, $b) {
            return $b['total'] - $a['total'];
        });
        return array_slice($topEditCounts, 0, $numProjects);
    }

    /**
     * Get revision counts for the given user.
     * @param integer $userId The user's ID.
     * @returns string[] With keys: 'archived', 'total', 'first', 'last', '24h', '7d', '30d', and
     * '365d'.
     * @throws Exception
     */
    public function getRevisionCounts($userId)
    {
        // Set up cache.
        $cacheKey = 'revisioncounts.'.$userId;
        if ($this->cacheHas($cacheKey)) {
            return $this->cacheGet($cacheKey);
        }

        // Prepare the query and execute
        $archiveTable = $this->labsHelper->getTable('archive');
        $revisionTable = $this->labsHelper->getTable('revision');
        $resultQuery = $this->replicas->prepare("
            (SELECT 'deleted' as source, COUNT(ar_id) AS value FROM $archiveTable
                WHERE ar_user = :userId)
            UNION
            (SELECT 'live' as source, COUNT(rev_id) AS value FROM $revisionTable
                WHERE rev_user = :userId)
            UNION
            (SELECT 'first' as source, rev_timestamp FROM $revisionTable
                WHERE rev_user = :userId ORDER BY rev_timestamp ASC LIMIT 1)
            UNION
            (SELECT 'last' as source, rev_timestamp FROM $revisionTable
                WHERE rev_user = :userId ORDER BY rev_timestamp DESC LIMIT 1)
            UNION
            (SELECT '24h' as source, COUNT(rev_id) as value FROM $revisionTable
                WHERE rev_user = :userId AND rev_timestamp >= DATE_SUB(NOW(),INTERVAL 24 HOUR))
            UNION
            (SELECT '7d' as source, COUNT(rev_id) as value FROM $revisionTable
                WHERE rev_user = :userId AND rev_timestamp >= DATE_SUB(NOW(),INTERVAL 7 DAY))
            UNION
            (SELECT '30d' as source, COUNT(rev_id) as value FROM $revisionTable
                WHERE rev_user = :userId AND rev_timestamp >= DATE_SUB(NOW(),INTERVAL 30 DAY))
            UNION
            (SELECT '365d' as source, COUNT(rev_id) as value FROM $revisionTable
                WHERE rev_user = :userId AND rev_timestamp >= DATE_SUB(NOW(),INTERVAL 365 DAY))
            UNION
            (SELECT 'small' AS source, COUNT(rev_id) AS value FROM $revisionTable
                WHERE rev_user = :userId AND rev_len < 20)
            UNION
            (SELECT 'large' AS source, COUNT(rev_id) AS value FROM $revisionTable
                WHERE rev_user = :userId AND rev_len > 1000)
            UNION
            (SELECT 'with_comments' AS source, COUNT(rev_id) AS value FROM $revisionTable
                WHERE rev_user = :userId AND rev_comment = '')
            UNION
            (SELECT 'minor_edits' AS source, COUNT(rev_id) AS value FROM $revisionTable
                WHERE rev_user = :userId AND rev_minor_edit = 1)
            ");
        $resultQuery->bindParam("userId", $userId);
        $resultQuery->execute();
        $results = $resultQuery->fetchAll();

        // Unknown user - This is a dirty hack that should be fixed.
        if (count($results) < 8) {
            throw new Exception("Unable to get all revision counts for user $userId");
        }

        $revisionCounts = array_combine(
            array_map(function ($e) {
                return $e['source'];
            }, $results),
            array_map(function ($e) {
                return $e['value'];
            }, $results)
        );

        // Count the number of days, accounting for when there's zero or one edit.
        $revisionCounts['days'] = 0;
        if (isset($revisionCounts['first']) && isset($revisionCounts['last'])) {
            $editingTimeInSeconds = ceil($revisionCounts['last'] - $revisionCounts['first']);
            $revisionCounts['days'] = $editingTimeInSeconds ? $editingTimeInSeconds/(60*60*24) : 1;
        }

        // Format the first and last dates.
        $revisionCounts['first'] = isset($revisionCounts['first'])
            ? date('Y-m-d H:i', strtotime($revisionCounts['first']))
            : 0;
        $revisionCounts['last'] = isset($revisionCounts['last'])
            ? date('Y-m-d H:i', strtotime($revisionCounts['last']))
            : 0;

        // Sum deleted and live to make the total.
        $revisionCounts['total'] = $revisionCounts['deleted'] + $revisionCounts['live'];

        // Calculate the average number of live edits per day.
        $revisionCounts['avg_per_day'] = round(
            $revisionCounts['live'] / $revisionCounts['days'],
            3
        );

        // Cache for 10 minutes, and return.
        $this->cacheSave($cacheKey, $revisionCounts, 'PT10M');
        return $revisionCounts;
    }

    /**
     *
     * @param $username
     * @return integer
     */
    public function getPageCounts($username, $totalRevisions)
    {
        $resultQuery = $this->replicas->prepare("
            SELECT 'unique' as source, COUNT(distinct rev_page) as value
                FROM ".$this->labsHelper->getTable('revision')." where rev_user_text=:username
            UNION
            SELECT 'created-live' as source, COUNT(*) as value from ".$this->labsHelper->getTable('revision')."
                WHERE rev_user_text=:username and rev_parent_id=0
            UNION
            SELECT 'created-deleted' as source, COUNT(*) as value from "
                                                .$this->labsHelper->getTable('archive')."
                WHERE ar_user_text=:username and ar_parent_id=0
            UNION
            SELECT 'moved' as source, count(*) as value from ".$this->labsHelper->getTable('logging')."
                WHERE log_type='move' and log_action='move' and log_user_text=:username
            ");
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

        // Total created.
        $pageCounts['created'] = $pageCounts['created-live'] + $pageCounts['created-deleted'];

        // Calculate the average number of edits per page.
        $pageCounts['edits_per_page'] = 0;
        if ($pageCounts['unique'] && $totalRevisions) {
            $pageCounts['edits_per_page'] = round($totalRevisions / $pageCounts['unique'], 3);
        }

        return $pageCounts;
    }

    /**
     * Get log totals for a user.
     * @param integer $userId The user ID.
     * @return integer[] Keys are log-action string, values are counts.
     */
    public function getLogCounts($userId)
    {
        $sql = "SELECT CONCAT(log_type, '-', log_action) AS source, COUNT(log_id) AS value
            FROM ".$this->labsHelper->getTable('logging')."
            WHERE log_user = :userId
            GROUP BY log_type, log_action";
        $resultQuery = $this->replicas->prepare($sql);
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
            'thanks-thank', 'review-approve', 'patrol-patrol','block-block', 'block-unblock',
            'protect-protect', 'protect-unprotect', 'delete-delete', 'delete-revision',
            'delete-restore', 'import-import', 'upload-upload', 'upload-overwrite',
        ];
        foreach ($requiredCounts as $req) {
            if (!isset($logCounts[$req])) {
                $logCounts[$req] = 0;
            }
        }

        // Merge approvals together.
        $logCounts['review-approve'] = $logCounts['review-approve'] +
            (!empty($logCounts['review-approve-a']) ? $logCounts['review-approve-a'] : 0) +
            (!empty($logCounts['review-approve-i']) ? $logCounts['review-approve-i'] : 0) +
            (!empty($logCounts['review-approve-ia']) ? $logCounts['review-approve-ia'] : 0);

        // Add Commons upload count, if applicable.
        $logCounts['files_uploaded_commons'] = 0;
        if ($this->labsHelper->isLabs()) {
            $sql = "SELECT COUNT(log_id) FROM commonswiki_p.logging_userindex
                WHERE log_type = 'upload' AND log_action = 'upload' AND log_user = :userId";
            $resultQuery = $this->replicas->prepare($sql);
            $resultQuery->bindParam('userId', $userId);
            $resultQuery->execute();
            $logCounts['files_uploaded_commons'] = $resultQuery->fetchColumn();
        }

        return $logCounts;
    }

    /**
     * Get the given user's total edit counts per namespace.
     * @param integer $userId The ID of the user.
     * @return integer[] Array keys are namespace IDs, values are the edit counts.
     */
    public function getNamespaceTotals($userId)
    {
        $sql = "SELECT page_namespace, count(rev_id) AS total
            FROM ".$this->labsHelper->getTable('revision') ." r
                JOIN ".$this->labsHelper->getTable('page')." p on r.rev_page = p.page_id
            WHERE r.rev_user = :id GROUP BY page_namespace";
        $resultQuery = $this->replicas->prepare($sql);
        $resultQuery->bindParam(":id", $userId);
        $resultQuery->execute();
        $results = $resultQuery->fetchAll();
        $namespaceTotals = array_combine(
            array_map(function ($e) {
                return $e['page_namespace'];
            }, $results),
            array_map(function ($e) {
                return $e['total'];
            }, $results)
        );
        return $namespaceTotals;
    }

    /**
     * Get this user's most recent 10 edits across all projects.
     * @param string $username The username.
     * @param integer $contribCount The number of items to return.
     * @param integer $days The number of days to search from each wiki.
     * @return string[]
     */
    public function getRecentGlobalContribs($username, $contribCount = 10, $days = 30)
    {
        $allRevisions = [];
        foreach ($this->labsHelper->allProjects() as $project) {
            $cacheKey = "globalcontribs.{$project['dbName']}.$username";
            if ($this->cacheHas($cacheKey)) {
                $revisions = $this->cacheGet($cacheKey);
            } else {
                $sql =
                    "SELECT rev_id, rev_comment, rev_timestamp, rev_minor_edit, rev_deleted, "
                    . "     rev_len, rev_parent_id, page_title "
                    . " FROM " . $this->labsHelper->getTable('revision', $project['dbName'])
                    . "    JOIN " . $this->labsHelper->getTable('page', $project['dbName'])
                    . "    ON (rev_page = page_id)"
                    . " WHERE rev_timestamp > NOW() - INTERVAL $days DAY AND rev_user_text LIKE :username"
                    . " ORDER BY rev_timestamp DESC"
                    . " LIMIT 10";
                $resultQuery = $this->replicas->prepare($sql);
                $resultQuery->bindParam(":username", $username);
                $resultQuery->execute();
                $revisions = $resultQuery->fetchAll();
                $this->cacheSave($cacheKey, $revisions, 'PT15M');
            }
            if (count($revisions) === 0) {
                continue;
            }
            $revsWithProject = array_map(
                function (&$item) use ($project) {
                    $item['project_name'] = $project['name'];
                    $item['project_url'] = $project['url'];
                    $item['project_db_name'] = $project['dbName'];
                    return $item;
                },
                $revisions
            );
            $allRevisions = array_merge($allRevisions, $revsWithProject);
        }
        usort($allRevisions, function ($a, $b) {
            return $b['rev_timestamp'] - $a['rev_timestamp'];
        });
        return array_slice($allRevisions, 0, $contribCount);
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

        $sql = "SELECT "
            . "     YEAR(rev_timestamp) AS `year`,"
            . "     MONTH(rev_timestamp) AS `month`,"
            . "     page_namespace,"
            . "     COUNT(rev_id) AS `count` "
            . " FROM " . $this->labsHelper->getTable('revision')
            . "    JOIN " . $this->labsHelper->getTable('page') . " ON (rev_page = page_id)"
            . " WHERE rev_user_text = :username"
            . " GROUP BY YEAR(rev_timestamp), MONTH(rev_timestamp), page_namespace "
            . " ORDER BY rev_timestamp DESC";
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
            $out['totals'][$ns][$total['year'].$total['month']] = $total['count'];
        }
        // Fill in the blanks (where no edits were made in a given month for a namespace).
        for ($y = $out['min_year']; $y <= $out['max_year']; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                foreach ($out['totals'] as $nsId => &$total) {
                    if (!isset($total[$y.$m])) {
                        $total[$y.$m] = 0;
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
    public function getYearlyTotalsByNamespace($username)
    {
        $cacheKey = "yearcounts.$username";
        if ($this->cacheHas($cacheKey)) {
            return $this->cacheGet($cacheKey);
        }

        $sql = "SELECT "
            . "     SUBSTR(CAST(rev_timestamp AS CHAR(4)), 1, 4) AS `year`,"
            . "     page_namespace,"
            . "     COUNT(rev_id) AS `count` "
            . " FROM " . $this->labsHelper->getTable('revision')
            . "    JOIN " . $this->labsHelper->getTable('page') . " ON (rev_page = page_id)" .
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
        $sql = "SELECT "
            . "     DAYOFWEEK(rev_timestamp) AS `y`, "
            . "     $xCalc AS `x`, "
            . "     COUNT(rev_id) AS `r` "
            . " FROM " . $this->labsHelper->getTable('revision')
            . " WHERE rev_user_text = :username"
            . " GROUP BY DAYOFWEEK(rev_timestamp), $xCalc "
            . " ";
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
}
