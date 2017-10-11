<?php
/**
 * This file contains only the TopEditsRepository class.
 */

namespace Xtools;

use DateInterval;

/**
 * TopEditsRepository is responsible for retrieving data from the database
 * about the top-edited pages of a user. It doesn't do any post-processing
 * of that information. There's really only one query here, but for testing
 * purposes we want this in a separate file.
 */
class TopEditsRepository extends Repository
{
    /**
     * Get the top edits by a user in the given namespace.
     * @param Project $project
     * @param User $user
     * @param int $namespace Namespace ID.
     * @param int $limit Number of edits to fetch.
     * @return string[] page_namespace, page_title, page_is_redirect,
     *   count (number of edits), assessment (page assessment).
     */
    public function getTopEdits(Project $project, User $user, $namespace = 0, $limit = 100)
    {
        // Set up cache.
        $cacheKey = 'topedits.'.$project->getDatabaseName().'.'.$user->getCacheKey().
            $namespace.$limit;
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');

        $hasPageAssessments = $this->isLabs() && $project->hasPageAssessments() && $namespace === 0;
        $pageAssessmentsTable = $this->getTableName($project->getDatabaseName(), 'page_assessments');
        $paSelect = $hasPageAssessments
            ?  ", (
                    SELECT pa_class
                    FROM page_assessments
                    WHERE pa_page_id = page_id
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
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $username = $user->getUsername();
        $resultQuery->bindParam('username', $username);
        $resultQuery->bindParam('namespace', $namespace);
        $resultQuery->execute();
        $results = $resultQuery->fetchAll();

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($results)
            ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);

        return $results;
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
