<?php
/**
 * This file contains only the TopEditsRepository class.
 */

namespace Xtools;

use Symfony\Component\DependencyInjection\Container;

/**
 * TopEditsRepository is responsible for retrieving data from the database
 * about the top-edited pages of a user. It doesn't do any post-processing
 * of that information.
 * @codeCoverageIgnore
 */
class TopEditsRepository extends Repository
{
    /**
     * Expose the container to the TopEdits class.
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Get the top edits by a user in a single namespace.
     * @param Project $project
     * @param User $user
     * @param int $namespace Namespace ID.
     * @param int $limit Number of edits to fetch.
     * @return string[] page_namespace, page_title, page_is_redirect,
     *   count (number of edits), assessment (page assessment).
     */
    public function getTopEditsNamespace(Project $project, User $user, $namespace = 0, $limit = 100)
    {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'topedits_ns');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');

        $hasPageAssessments = $this->isLabs() && $project->hasPageAssessments() && $namespace === 0;
        $paTable = $this->getTableName($project->getDatabaseName(), 'page_assessments');
        $paSelect = $hasPageAssessments
            ?  ", (
                    SELECT pa_class
                    FROM $paTable
                    WHERE pa_page_id = page_id
                    AND pa_class != 'Unknown'
                    LIMIT 1
                ) AS pa_class"
            : '';

        $sql = "SELECT page_namespace, page_title, page_is_redirect, COUNT(page_title) AS count
                    $paSelect
                FROM $pageTable JOIN $revisionTable ON page_id = rev_page
                WHERE rev_user_text = :username
                AND page_namespace = :namespace
                GROUP BY page_namespace, page_title
                ORDER BY count DESC
                LIMIT $limit";
        $result = $this->executeProjectsQuery($sql, [
            'username' => $user->getUsername(),
            'namespace' => $namespace,
        ])->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get the top edits by a user across all namespaces.
     * @param Project $project
     * @param User $user
     * @param int $limit Number of edits to fetch.
     * @return string[] page_namespace, page_title, page_is_redirect,
     *   count (number of edits), assessment (page assessment).
     */
    public function getTopEditsAllNamespaces(Project $project, User $user, $limit = 10)
    {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'topedits_all');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $hasPageAssessments = $this->isLabs() && $project->hasPageAssessments();
        $pageAssessmentsTable = $this->getTableName($project->getDatabaseName(), 'page_assessments');
        $paSelect = $hasPageAssessments
            ?  ", (
                    SELECT pa_class
                    FROM $pageAssessmentsTable
                    WHERE pa_page_id = e.page_id
                    LIMIT 1
                ) AS pa_class"
            : ', NULL as pa_class';

        $sql = "SELECT c.page_namespace, e.page_title, c.page_is_redirect, c.count $paSelect
                FROM
                (
                    SELECT b.page_namespace, b.page_is_redirect, b.rev_page, b.count
                        ,@rn := if(@ns = b.page_namespace, @rn + 1, 1) AS row_number
                        ,@ns := b.page_namespace AS dummy
                    FROM
                    (
                        SELECT page_namespace, page_is_redirect, rev_page, count(rev_page) AS count
                        FROM $revisionTable
                        JOIN $pageTable ON page_id = rev_page
                        WHERE rev_user_text = :username
                        GROUP BY page_namespace, rev_page
                    ) AS b
                    JOIN (SELECT @ns := NULL, @rn := 0) AS vars
                    ORDER BY b.page_namespace ASC, b.count DESC
                ) AS c
                JOIN $pageTable e ON e.page_id = c.rev_page
                WHERE c.row_number < $limit";
        $result = $this->executeProjectsQuery($sql, [
            'username' => $user->getUsername(),
        ])->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get the top edits by a user to a single page.
     * @param Page $page
     * @param User $user
     * @return string[] Each row with keys 'id', 'timestamp', 'minor', 'length',
     *   'length_change', 'reverted', 'user_id', 'username', 'comment', 'parent_comment'
     */
    public function getTopEditsPage(Page $page, User $user)
    {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'topedits_page');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $results = $this->queryTopEditsPage($page, $user, true);

        // Now we need to get the most recent revision, since the childrevs stuff excludes it.
        $lastRev = $this->queryTopEditsPage($page, $user, false);
        if (empty($results) || $lastRev[0]['id'] !== $results[0]['id']) {
            $results = array_merge($lastRev, $results);
        }

        // Cache and return.
        return $this->setCache($cacheKey, $results);
    }

    /**
     * The actual query to get the top edits by the user to the page.
     * Because of the way the main query works, we aren't given the most recent revision,
     * so we have to call this twice, once with $childRevs set to true and once with false.
     * @param  Page    $page
     * @param  User    $user
     * @param  boolean $childRevs Whether to include child revisions.
     * @return string[] Each row with keys 'id', 'timestamp', 'minor', 'length',
     *   'length_change', 'reverted', 'user_id', 'username', 'comment', 'parent_comment'
     */
    private function queryTopEditsPage(Page $page, User $user, $childRevs = false)
    {
        $revTable = $this->getTableName($page->getProject()->getDatabaseName(), 'revision');

        if ($childRevs) {
            $childSelect = ', (CASE WHEN childrevs.rev_sha1 = parentrevs.rev_sha1 THEN 1 ELSE 0 END) AS reverted,
                    childrevs.rev_comment AS parent_comment';
            $childJoin = "LEFT JOIN $revTable AS childrevs ON (revs.rev_id = childrevs.rev_parent_id)";
            $childWhere = 'AND childrevs.rev_page = :pageid';
            $childLimit = '';
        } else {
            $childSelect = ', "" AS parent_comment, 0 AS reverted';
            $childJoin = '';
            $childWhere = '';
            $childLimit = 'LIMIT 1';
        }

        $sql = "SELECT
                    revs.rev_id AS id,
                    revs.rev_timestamp AS timestamp,
                    revs.rev_minor_edit AS minor,
                    revs.rev_len AS length,
                    (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                    revs.rev_user AS user_id,
                    revs.rev_user_text AS username,
                    revs.rev_comment AS comment
                    $childSelect
                FROM $revTable AS revs
                LEFT JOIN $revTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                $childJoin
                WHERE revs.rev_user_text = :username
                AND revs.rev_page = :pageid
                $childWhere
                ORDER BY revs.rev_timestamp DESC
                $childLimit";

        return $this->executeProjectsQuery($sql, [
            'pageid' => $page->getId(),
            'username' => $user->getUsername(),
        ])->fetchAll();
    }

    /**
     * Get the display titles of the given pages.
     * @param Project $project
     * @param  string[] $titles List of page titles.
     * @return string[] Keys are the original supplied titles, and values are the display titles.
     */
    public function getDisplayTitles(Project $project, $titles)
    {
        /** @var ApiHelper $apiHelper */
        $apiHelper = $this->container->get('app.api_helper');

        return $apiHelper->displayTitles($project->getDomain(), $titles);
    }
}
