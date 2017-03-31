<?php

namespace AppBundle\Helper;

use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\DependencyInjection\Container;

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
     * Get revision counts for the given user.
     * @param integer $userId The user's ID.
     * @returns string[] With keys: 'archived', 'total', 'first', 'last', '24h', '7d', '30d', and
     * '365d'.
     * @throws Exception
     */
    public function getRevisionCounts($userId)
    {
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

        // Count the number of days, accounting for when there's only one edit.
        $editingTimeInSeconds = ceil($revisionCounts['last'] - $revisionCounts['first']);
        $revisionCounts['days'] = $editingTimeInSeconds ? $editingTimeInSeconds/(60*60*24) : 1;

        // Format the first and last dates.
        $revisionCounts['first'] = date('Y-m-d H:i', strtotime($revisionCounts['first']));
        $revisionCounts['last'] = date('Y-m-d H:i', strtotime($revisionCounts['last']));

        // Sum deleted and live to make the total.
        $revisionCounts['total'] = $revisionCounts['deleted'] + $revisionCounts['live'];
        
        // Calculate the average number of live edits per day.
        $revisionCounts['avg_per_day'] = round(
            $revisionCounts['live'] / $revisionCounts['days'],
            3
        );

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
}
