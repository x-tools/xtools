<?php
/**
 * This file contains only the ArticleInfoRepository class.
 */

namespace Xtools;

/**
 * ArticleInfoRepository is responsible for retrieving data about a single
 * article on a given wiki.
 */
class ArticleInfoRepository extends Repository
{
    /**
     * Get the number of edits made to the page by bots or former bots.
     * @param  Page $page
     * @return PDOStatement resolving with keys 'count', 'username' and 'current'.
     */
    public function getBotData(Page $page)
    {
        $project = $page->getProject();
        $userGroupsTable = $project->getTableName('user_groups');
        $userFromerGroupsTable = $project->getTableName('user_former_groups');
        $sql = "SELECT COUNT(rev_user_text) AS count, rev_user_text AS username, ug_group AS current
                FROM " . $project->getTableName('revision') . "
                LEFT JOIN $userGroupsTable ON rev_user = ug_user
                LEFT JOIN $userFromerGroupsTable ON rev_user = ufg_user
                WHERE rev_page = :pageId AND (ug_group = 'bot' OR ufg_group = 'bot')
                GROUP BY rev_user_text";
        $pageId = $page->getId();
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam('pageId', $pageId);
        $resultQuery->execute();
        return $resultQuery;
    }

    /**
     * Get prior deletions, page moves, and protections to the page.
     * @param  Page page
     * @return string[] each entry with keys 'log_action', 'log_type' and 'timestamp'.
     */
    public function getLogEvents(Page $page)
    {
        $loggingTable = $page->getProject()->getTableName('logging', 'logindex');
        $sql = "SELECT log_action, log_type, log_timestamp AS 'timestamp'
                FROM $loggingTable
                WHERE log_namespace = '" . $page->getNamespace() . "'
                AND log_title = :title AND log_timestamp > 1
                AND log_type IN ('delete', 'move', 'protect', 'stable')";
        $title = str_replace(' ', '_', $page->getTitle());
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam(':title', $title);
        $resultQuery->execute();
        return $resultQuery->fetchAll();
    }
}
