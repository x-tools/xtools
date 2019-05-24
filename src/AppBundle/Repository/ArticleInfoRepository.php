<?php
/**
 * This file contains only the ArticleInfoRepository class.
 */

declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Page;
use AppBundle\Model\Project;
use Doctrine\DBAL\Driver\Statement;
use GuzzleHttp;

/**
 * ArticleInfoRepository is responsible for retrieving data about a single
 * article on a given wiki.
 * @codeCoverageIgnore
 */
class ArticleInfoRepository extends Repository
{
    /**
     * Get the number of edits made to the page by bots or former bots.
     * @param Page $page
     * @param false|int $start
     * @param false|int $end
     * @return Statement resolving with keys 'count', 'username' and 'current'.
     */
    public function getBotData(Page $page, $start, $end): Statement
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_botdata');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $project = $page->getProject();
        $revTable = $project->getTableName('revision');
        $userGroupsTable = $project->getTableName('user_groups');
        $userFormerGroupsTable = $project->getTableName('user_former_groups');
        $actorTable = $project->getTableName('actor');

        $datesConditions = $this->getDateConditions($start, $end);

        $sql = "SELECT COUNT(DISTINCT(rev_id)) AS count, actor_name AS username, '1' AS current
                FROM $revTable
                JOIN $actorTable ON actor_id = rev_actor
                LEFT JOIN $userGroupsTable ON actor_user = ug_user
                WHERE rev_page = :pageId AND ug_group = 'bot' $datesConditions
                GROUP BY actor_user
                UNION
                SELECT COUNT(DISTINCT(rev_id)) AS count, actor_name AS username, '0' AS current
                FROM $revTable
                JOIN $actorTable ON actor_id = rev_actor
                LEFT JOIN $userFormerGroupsTable ON actor_user = ufg_user
                WHERE rev_page = :pageId AND ufg_group = 'bot' $datesConditions
                GROUP BY actor_user";

        $result = $this->executeProjectsQuery($sql, ['pageId' => $page->getId()]);
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get prior deletions, page moves, and protections to the page.
     * @param Page $page
     * @param false|int $start
     * @param false|int $end
     * @return string[] each entry with keys 'log_action', 'log_type' and 'timestamp'.
     */
    public function getLogEvents(Page $page, $start, $end): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_logevents');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $loggingTable = $page->getProject()->getTableName('logging', 'logindex');

        $datesConditions = $this->getDateConditions($start, $end, '', 'log_timestamp');

        $sql = "SELECT log_action, log_type, log_timestamp AS 'timestamp'
                FROM $loggingTable
                WHERE log_namespace = '" . $page->getNamespace() . "'
                AND log_title = :title AND log_timestamp > 1 $datesConditions
                AND log_type IN ('delete', 'move', 'protect', 'stable')";
        $title = str_replace(' ', '_', $page->getTitle());

        $result = $this->executeProjectsQuery($sql, ['title' => $title])->fetchAll();
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Query the WikiWho service to get authorship percentages.
     * @see https://api.wikiwho.net/
     * @param Page $page
     * @return array[]|null Response from WikiWho. null if something went wrong.
     */
    public function getTextshares(Page $page): ?array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_authorship');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $title = rawurlencode(str_replace(' ', '_', $page->getTitle()));
        $projectLang = $page->getProject()->getLang();

        $url = "https://api.wikiwho.net/$projectLang/api/v1.0.0-beta/rev_content/" .
            "$title/?o_rev_id=false&editor=true&token_id=false&out=false&in=false";

        // Ignore HTTP errors to fail gracefully.
        $opts = ['http_errors' => false];

        // Use WikiWho API credentials, if present. They are not required.
        if ($this->container->hasParameter('app.wikiwho.username')) {
            $opts['auth'] = [
                $this->container->getParameter('app.wikiwho.username'),
                $this->container->getParameter('app.wikiwho.password'),
            ];
        }

        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $url, $opts);

        // Cache and return.
        return $this->setCache($cacheKey, json_decode($res->getBody()->getContents(), true));
    }

    /**
     * Get a map of user IDs/usernames given the user IDs.
     * @param Project $project
     * @param int[] $userIds
     * @return array
     */
    public function getUsernamesFromIds(Project $project, array $userIds): array
    {
        $userTable = $project->getTableName('user');
        $userIds = implode(',', array_unique(array_filter($userIds)));
        $sql = "SELECT user_id, user_name
                FROM $userTable
                WHERE user_id IN ($userIds)";
        return $this->executeProjectsQuery($sql)->fetchAll();
    }

    /**
     * Get the number of categories, templates, and files that are on the page.
     * @param Page $page
     * @return array With keys 'categories', 'templates' and 'files'.
     */
    public function getTransclusionData(Page $page): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_transclusions');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

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
            $transclusionCounts[$result['key']] = (int)$result['val'];
        }

        return $this->setCache($cacheKey, $transclusionCounts);
    }

    /**
     * Get the top editors to the page by edit count.
     * @param Page $page
     * @param false|int $start
     * @param false|int $end
     * @param int $limit
     * @param bool $noBots
     * @return array
     */
    public function getTopEditorsByEditCount(
        Page $page,
        $start = false,
        $end = false,
        int $limit = 20,
        bool $noBots = false
    ): array {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_topeditors');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $project = $page->getProject();
        // Faster to use revision instead of revision_userindex in this case.
        $revTable = $project->getTableName('revision', '');
        $actorTable = $project->getTableName('actor');

        $dateConditions = $this->getDateConditions($start, $end);

        $sql = "SELECT actor_name AS username,
                    COUNT(rev_id) AS count,
                    SUM(rev_minor_edit) AS minor,
                    MIN(rev_timestamp) AS first_timestamp,
                    MIN(rev_id) AS first_revid,
                    MAX(rev_timestamp) AS latest_timestamp,
                    MAX(rev_id) AS latest_revid
                FROM $revTable
                JOIN $actorTable ON rev_actor = actor_id
                WHERE rev_page = :pageId $dateConditions";

        if ($noBots) {
            $userGroupsTable = $project->getTableName('user_groups');
            $sql .= "AND NOT EXISTS (
                         SELECT 1
                         FROM $userGroupsTable
                         WHERE ug_user = actor_user
                         AND ug_group = 'bot'
                     )";
        }

        $sql .= "GROUP BY actor_id
                 ORDER BY count DESC
                 LIMIT $limit";

        $result = $this->executeProjectsQuery($sql, [
            'pageId' => $page->getId(),
        ])->fetchAll();

        return $this->setCache($cacheKey, $result);
    }
}
