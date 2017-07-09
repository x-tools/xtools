<?php
/**
 * This file contains only the PagesRepository class.
 */

namespace Xtools;

use DateInterval;
use Mediawiki\Api\SimpleRequest;

/**
 * A PagesRepository fetches data about Pages, either singularly or for multiple.
 */
class PagesRepository extends Repository
{

    /**
     * Get metadata about a single page from the API.
     * @param Project $project The project to which the page belongs.
     * @param string $pageTitle Page title.
     * @return string[] Array with some of the following keys: pageid, title, missing, displaytitle,
     * url.
     */
    public function getPageInfo(Project $project, $pageTitle)
    {
        $info = $this->getPagesInfo($project, [$pageTitle]);
        return array_shift($info);
    }

    /**
     * Get metadata about a set of pages from the API.
     * @param Project $project The project to which the pages belong.
     * @param string[] $pageTitles Array of page titles.
     * @return string[] Array keyed by the page names, each element with some of the
     * following keys: pageid, title, missing, displaytitle, url.
     */
    public function getPagesInfo(Project $project, $pageTitles)
    {
        // @TODO: Also include 'extlinks' prop when we start checking for dead external links.
        $params = [
            'prop' => 'info|pageprops',
            'inprop' => 'protection|talkid|watched|watchers|notificationtimestamp|subjectid|url|readable|displaytitle',
            'converttitles' => '',
            // 'ellimit' => 20,
            // 'elexpandurl' => '',
            'titles' => join('|', $pageTitles),
            'formatversion' => 2
            // 'pageids' => $pageIds // FIXME: allow page IDs
        ];

        $query = new SimpleRequest('query', $params);
        $api = $this->getMediawikiApi($project);
        $res = $api->getRequest($query);
        $result = [];
        if (isset($res['query']['pages'])) {
            foreach ($res['query']['pages'] as $pageInfo) {
                $result[$pageInfo['title']] = $pageInfo;
            }
        }
        return $result;
    }

    /**
     * Get revisions of a single page.
     * @param Page $page The page.
     * @param User|null $user Specify to get only revisions by the given user.
     * @return string[] Each member with keys: id, timestamp, length-
     */
    public function getRevisions(Page $page, User $user = null)
    {
        $cacheKey = 'revisions.'.$page->getId();
        if ($user) {
            $cacheKey .= '.'.$user->getUsername();
        }

        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $this->stopwatch->start($cacheKey, 'XTools');

        $revTable = $this->getTableName($page->getProject()->getDatabaseName(), 'revision');
        $userClause = $user ? "revs.rev_user_text in (:username) AND " : "";

        $query = "SELECT
                    revs.rev_id AS id,
                    revs.rev_timestamp AS timestamp,
                    revs.rev_minor_edit AS minor,
                    revs.rev_len AS length,
                    (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                    revs.rev_user AS user_id,
                    revs.rev_user_text AS username,
                    revs.rev_comment AS comment
                FROM $revTable AS revs
                LEFT JOIN $revTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                WHERE $userClause revs.rev_page = :pageid
                ORDER BY revs.rev_timestamp ASC
            ";
        $params = ['pageid' => $page->getId()];
        if ($user) {
            $params['username'] = $user->getUsername();
        }
        $conn = $this->getProjectsConnection();
        $result = $conn->executeQuery($query, $params)->fetchAll();

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($result)
            ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);
        $this->stopwatch->stop($cacheKey);
    }

    /**
     * Get a count of the number of revisions of a single page
     * @param Page $page The page.
     * @param User|null $user Specify to only count revisions by the given user.
     * @return int
     */
    public function getNumRevisions(Page $page, User $user = null)
    {
        $revTable = $this->getTableName($page->getProject()->getDatabaseName(), 'revision');
        $userClause = $user ? "rev_user_text in (:username) AND " : "";

        $query = "SELECT COUNT(*)
                FROM $revTable
                WHERE $userClause rev_page = :pageid
            ";
        $params = ['pageid' => $page->getId()];
        if ($user) {
            $params['username'] = $user->getUsername();
        }
        $conn = $this->getProjectsConnection();
        return $conn->executeQuery($query, $params)->fetchColumn(0);
    }

    /**
     * Get assessment data for the given pages
     * @param Project   $project The project to which the pages belong.
     * @param  int[]    $pageIds Page IDs
     * @return string[] Assessment data as retrieved from the database.
     */
    public function getAssessments(Project $project, $pageIds)
    {
        if (!$project->hasPageAssessments()) {
            return [];
        }
        $pageAssessmentsTable = $this->getTableName($project->getDatabaseName(), 'page_assessments');
        $pageIds = implode($pageIds, ',');

        $query = "SELECT pap_project_title AS wikiproject, pa_class AS class, pa_importance AS importance
                  FROM page_assessments
                  LEFT JOIN page_assessments_projects ON pa_project_id = pap_project_id
                  WHERE pa_page_id IN ($pageIds)";

        $conn = $this->getProjectsConnection();
        return $conn->executeQuery($query)->fetchAll();
    }

    /**
     * Get any CheckWiki errors of a single page
     * @param Page $page
     * @return array Results from query
     */
    public function getCheckWikiErrors(Page $page)
    {
        // Only support mainspace on Labs installations
        if ($page->getNamespace() !== 0 || !$this->isLabs()) {
            return [];
        }

        $sql = "SELECT error, notice, found, name_trans AS name, prio, text_trans AS explanation
                FROM s51080__checkwiki_p.cw_error a
                JOIN s51080__checkwiki_p.cw_overview_errors b
                WHERE a.project = b.project
                AND a.project = :dbName
                AND a.title = :title
                AND a.error = b.id
                AND a.ok = 0";

        // remove _p if present
        $dbName = preg_replace('/_p$/', '', $page->getProject()->getDatabaseName());

        // Page title without underscores (str_replace just to be sure)
        $pageTitle = str_replace('_', ' ', $page->getTitle());

        $resultQuery = $this->getToolsConnection()->prepare($sql);
        $resultQuery->bindParam(':dbName', $dbName);
        $resultQuery->bindParam(':title', $pageTitle);
        $resultQuery->execute();

        return $resultQuery->fetchAll();
    }

    /**
     * Get basic wikidata on the page: label and description.
     * @param Page $page
     * @return string[] In the format:
     *    [[
     *         'term' => string such as 'label',
     *         'term_text' => string (value for 'label'),
     *     ], ... ]
     */
    public function getWikidataInfo(Page $page)
    {
        if (empty($page->getWikidataId())) {
            return [];
        }

        $wikidataId = ltrim($page->getWikidataId(), 'Q');
        $lang = $page->getProject()->getLang();

        $sql = "SELECT IF(term_type = 'label', 'label', 'description') AS term, term_text
                FROM wikidatawiki_p.wb_entity_per_page
                JOIN wikidatawiki_p.page ON epp_page_id = page_id
                JOIN wikidatawiki_p.wb_terms ON term_entity_id = epp_entity_id
                    AND term_language = :lang
                    AND term_type IN ('label', 'description')
                WHERE epp_entity_id = :wikidataId

                UNION

                SELECT pl_title AS term, wb_terms.term_text
                FROM wikidatawiki_p.pagelinks
                JOIN wikidatawiki_p.wb_terms ON term_entity_id = SUBSTRING(pl_title, 2)
                    AND term_entity_type = (IF(SUBSTRING(pl_title, 1, 1) = 'Q', 'item', 'property'))
                    AND term_language = :lang
                    AND term_type = 'label'
                WHERE pl_namespace IN (0, 120)
                    AND pl_from = (
                        SELECT page_id FROM page
                        WHERE page_namespace = 0
                            AND page_title = 'Q:wikidataId'
                    )";

        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam(':lang', $lang);
        $resultQuery->bindParam(':wikidataId', $wikidataId);
        $resultQuery->execute();

        return $resultQuery->fetchAll();
    }

    /**
     * Get or count all wikidata items for the given page,
     *     not just languages of sister projects
     * @param Page $page
     * @param bool $count Set to true to get only a COUNT
     * @return string[]|int Records as returend by the DB,
     *                      or raw COUNT of the records.
     */
    public function getWikidataItems(Page $page, $count = false)
    {
        if (!$page->getWikidataId()) {
            return $count ? 0 : [];
        }

        $wikidataId = ltrim($page->getWikidataId(), 'Q');

        $sql = "SELECT " . ($count ? 'COUNT(*) AS count' : '*') . "
                FROM wikidatawiki_p.wb_items_per_site
                WHERE ips_item_id = :wikidataId";

        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam(':wikidataId', $wikidataId);
        $resultQuery->execute();

        $result = $resultQuery->fetchAll();

        return $count ? (int) $result[0]['count'] : $result;
    }

    /**
     * Count wikidata items for the given page, not just languages of sister projects
     * @param Page $page
     * @return int Number of records.
     */
    public function countWikidataItems(Page $page)
    {
        return $this->getWikidataItems($page, true);
    }
}
