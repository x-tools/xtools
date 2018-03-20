<?php
/**
 * This file contains only the ArticleInfoRepository class.
 */

namespace Xtools;

use GuzzleHttp;

/**
 * ArticleInfoRepository is responsible for retrieving data about a single
 * article on a given wiki.
 */
class ArticleInfoRepository extends Repository
{
    /**
     * Get the number of edits made to the page by bots or former bots.
     * @param  Page $page
     * @param  false|int $start
     * @param  false|int $end
     * @return \Doctrine\DBAL\Driver\Statement resolving with keys 'count', 'username' and 'current'.
     */
    public function getBotData(Page $page, $start, $end)
    {
        $project = $page->getProject();
        $userGroupsTable = $project->getTableName('user_groups');
        $userFormerGroupsTable = $project->getTableName('user_former_groups');

        $datesConditions = $this->getDateConditions($start, $end);

        $sql = "SELECT COUNT(DISTINCT(rev_id)) AS count, rev_user_text AS username, ug_group AS current
                FROM " . $project->getTableName('revision') . "
                LEFT JOIN $userGroupsTable ON rev_user = ug_user
                LEFT JOIN $userFormerGroupsTable ON rev_user = ufg_user
                WHERE rev_page = :pageId AND (ug_group = 'bot' OR ufg_group = 'bot') $datesConditions
                GROUP BY rev_user_text";

        return $this->executeProjectsQuery($sql, ['pageId' => $page->getId()]);
    }

    /**
     * Get prior deletions, page moves, and protections to the page.
     * @param Page $page
     * @param false|int $start
     * @param false|int $end
     * @return string[] each entry with keys 'log_action', 'log_type' and 'timestamp'.
     */
    public function getLogEvents(Page $page, $start, $end)
    {
        $loggingTable = $page->getProject()->getTableName('logging', 'logindex');

        $datesConditions = $this->getDateConditions($start, $end, '', 'log_timestamp');

        $sql = "SELECT log_action, log_type, log_timestamp AS 'timestamp'
                FROM $loggingTable
                WHERE log_namespace = '" . $page->getNamespace() . "'
                AND log_title = :title AND log_timestamp > 1 $datesConditions
                AND log_type IN ('delete', 'move', 'protect', 'stable')";
        $title = str_replace(' ', '_', $page->getTitle());

        return $this->executeProjectsQuery($sql, ['title' => $title])->fetchAll();
    }

    /**
     * Query the WikiWho service to get authorship percentages.
     * @see https://api.wikiwho.net/
     * @param Page $page
     * @return array[] Response from WikiWho.
     */
    public function getTextshares(Page $page)
    {
        $title = rawurlencode(str_replace(' ', '_', $page->getTitle()));
        $client = new GuzzleHttp\Client();

        $projectLang = $page->getProject()->getLang();

        $url = "https://api.wikiwho.net/$projectLang/api/v1.0.0-beta/rev_content/" .
            "$title/?o_rev_id=false&editor=true&token_id=false&out=false&in=false";

        $res = $client->request('GET', $url, ['http_errors' => false]);
        return json_decode($res->getBody()->getContents(), true);
    }

    /**
     * Get a map of user IDs/usernames given the user IDs.
     * @param  Project $project
     * @param  int[]   $userIds
     * @return array
     */
    public function getUsernamesFromIds(Project $project, $userIds)
    {
        $userTable = $project->getTableName('user');
        $userIds = implode(',', array_filter($userIds));
        $sql = "SELECT user_id, user_name
                FROM $userTable
                WHERE user_id IN ($userIds)";
        return $this->executeProjectsQuery($sql)->fetchAll();
    }

    /**
     * Get the number of categories, templates, and files that are on the page.
     * @param  Page $page
     * @return array With keys 'categories', 'templates' and 'files'.
     */
    public function getTransclusionData(Page $page)
    {
        $categorylinksTable = $page->getProject()->getTableName('categorylinks');
        $templatelinksTable = $page->getProject()->getTableName('templatelinks');
        $imagelinksTable = $page->getProject()->getTableName('imagelinks');
        $sql = "(
                    SELECT 'categories' AS `key`, COUNT(*) AS val
                    FROM $categorylinksTable
                    WHERE cl_from = :pageId
                ) UNION (
                    SELECT 'templates' AS `key`, COUNT(*) AS val
                    FROM $templatelinksTable
                    WHERE tl_from = :pageId
                ) UNION (
                    SELECT 'files' AS `key`, COUNT(*) AS val
                    FROM $imagelinksTable
                    WHERE il_from = :pageId
                )";
        $resultQuery = $this->executeProjectsQuery($sql, ['pageId' => $page->getId()]);
        $transclusionCounts = [];
        while ($result = $resultQuery->fetch()) {
            $transclusionCounts[$result['key']] = $result['val'];
        }

        return $transclusionCounts;
    }
}
