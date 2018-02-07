<?php
/**
 * This file contains only the EditSummaryRepository class.
 */

namespace Xtools;

/**
 * An EditSummaryRepository is responsible for retrieving information from the
 * databases for the Edit Summary tool. It does not do any post-processing
 * of that data.
 * @codeCoverageIgnore
 */
class EditSummaryRepository extends Repository
{
    /**
     * Build and execute SQL to get edit summary usage.
     * @param Project $project The project we're working with.
     * @param User $user The user to process.
     * @param string|int $namespace Namespace ID or 'all' for all namespaces.
     * @return \Doctrine\DBAL\Statement
     */
    public function getRevisions(Project $project, User $user, $namespace)
    {
        $revisionTable = $project->getTableName('revision');
        $pageTable = $project->getTableName('page');

        $condNamespace = $namespace === 'all' ? '' : 'AND page_namespace = :namespace';
        $pageJoin = $namespace === 'all' ? '' : "JOIN $pageTable ON rev_page = page_id";

        $sql = "SELECT rev_comment, rev_timestamp, rev_minor_edit
                FROM  $revisionTable
    ​            $pageJoin
                WHERE rev_user_text = :username
                $condNamespace
                ORDER BY rev_timestamp DESC";

        $params = ['username' => $user->getUsername()];
        if ($namespace !== 'all') {
            $params['namespace'] = $namespace;
        }

        return $this->executeProjectsQuery($sql, $params);
    }

    /**
     * Loop through the revisions and tally up totals, based on callback
     * that lives in the EditSummary model.
     * @param Project $project
     * @param User $user
     * @param int $namespace
     * @param array $processRow [EditSummary instance, 'method name']
     * @return array The final results.
     */
    public function prepareData(Project $project, User $user, $namespace, array $processRow)
    {
        $cacheKey = $this->getCacheKey([$project, $user, $namespace], 'edit_summary_usage');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $resultQuery = $this->getRevisions($project, $user, $namespace);
        $data = [];

        while ($row = $resultQuery->fetch()) {
            $data = call_user_func($processRow, $row);
        }

        // Cache and return.
        return $this->setCache($cacheKey, $data);
    }
}
