<?php
/**
 * This file contains only the EditSummaryRepository class.
 */

declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Project;
use AppBundle\Model\User;
use Doctrine\DBAL\Driver\ResultStatement;

/**
 * An EditSummaryRepository is responsible for retrieving information from the
 * databases for the Edit Summary tool. It does not do any post-processing
 * of that data.
 * @codeCoverageIgnore
 */
class EditSummaryRepository extends UserRepository
{
    /**
     * Build and execute SQL to get edit summary usage.
     * @param Project $project The project we're working with.
     * @param User $user The user to process.
     * @param string|int $namespace Namespace ID or 'all' for all namespaces.
     * @param string $start Start date in a format accepted by strtotime().
     * @param string $end End date in a format accepted by strtotime().
     * @return ResultStatement
     */
    public function getRevisions(
        Project $project,
        User $user,
        $namespace,
        string $start = '',
        string $end = ''
    ): ResultStatement {
        $revisionTable = $project->getTableName('revision');
        $commentTable = $project->getTableName('comment');
        $pageTable = $project->getTableName('page');

        [$condBegin, $condEnd] = $this->getRevTimestampConditions($start, $end);
        $condNamespace = 'all' === $namespace ? '' : 'AND page_namespace = :namespace';
        $pageJoin = 'all' === $namespace ? '' : "JOIN $pageTable ON rev_page = page_id";

        $sql = "SELECT comment_text AS `comment`, rev_timestamp, rev_minor_edit
                FROM $revisionTable
                $pageJoin
                LEFT OUTER JOIN $commentTable ON comment_id = rev_comment_id
                WHERE rev_actor = :actorId
                $condNamespace
                $condBegin
                $condEnd
                ORDER BY rev_timestamp DESC";

        return $this->executeQuery($sql, $project, $user, $namespace, $start, $end);
    }

    /**
     * Loop through the revisions and tally up totals, based on callback that lives in the EditSummary model.
     * @param array $processRow [EditSummary instance, 'method name']
     * @param Project $project
     * @param User $user
     * @param int|string $namespace Namespace ID or 'all' for all namespaces.
     * @param string $start Start date in a format accepted by strtotime().
     * @param string $end End date in a format accepted by strtotime().
     * @return array The final results.
     */
    public function prepareData(
        array $processRow,
        Project $project,
        User $user,
        $namespace,
        string $start = '',
        string $end = ''
    ): array {
        $cacheKey = $this->getCacheKey([$project, $user, $namespace, $start, $end], 'edit_summary_usage');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $resultQuery = $this->getRevisions($project, $user, $namespace, $start, $end);
        $data = [];

        while ($row = $resultQuery->fetch()) {
            $data = call_user_func($processRow, $row);
        }

        // Cache and return.
        return $this->setCache($cacheKey, $data);
    }
}
