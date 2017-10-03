<?php
/**
 * This file contains only the PagesRepository class.
 */

namespace Xtools;

use DateInterval;
use Mediawiki\Api\SimpleRequest;
use GuzzleHttp;

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
     * Get the full page text of a set of pages.
     * @param Project $project The project to which the pages belong.
     * @param string[] $pageTitles Array of page titles.
     * @return string[] Array keyed by the page names, with the page text as the values.
     */
    public function getPagesWikitext(Project $project, $pageTitles)
    {
        $query = new SimpleRequest('query', [
            'prop' => 'revisions',
            'rvprop' => 'content',
            'titles' => join('|', $pageTitles),
            'formatversion' => 2,
        ]);
        $result = [];

        $api = $this->getMediawikiApi($project);
        $res = $api->getRequest($query);

        if (!isset($res['query']['pages'])) {
            return [];
        }

        foreach ($res['query']['pages'] as $page) {
            if (isset($page['revisions'][0]['content'])) {
                $result[$page['title']] = $page['revisions'][0]['content'];
            } else {
                $result[$page['title']] = '';
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
            $cacheKey .= '.'.$user->getCacheKey();
        }

        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $this->stopwatch->start($cacheKey, 'XTools');

        $stmt = $this->getRevisionsStmt($page, $user);
        $result = $stmt->fetchAll();

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($result)
            ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);
        $this->stopwatch->stop($cacheKey);

        return $result;
    }

    /**
     * Get the statement for a single revision, so that you can iterate row by row.
     * @param Page $page The page.
     * @param User|null $user Specify to get only revisions by the given user.
     * @param int $limit Max number of revisions to process.
     * @param int $numRevisions Number of revisions, if known. This is used solely to determine the
     *   OFFSET if we are given a $limit (see below). If $limit is set and $numRevisions is not set,
     *   a separate query is ran to get the nuber of revisions.
     * @return Doctrine\DBAL\Driver\PDOStatement
     */
    public function getRevisionsStmt(Page $page, User $user = null, $limit = null, $numRevisions = null)
    {
        $revTable = $this->getTableName($page->getProject()->getDatabaseName(), 'revision');
        $userClause = $user ? "revs.rev_user_text in (:username) AND " : "";

        // This sorts ascending by rev_timestamp because ArticleInfo must start with the oldest
        // revision and work its way forward for proper processing. Consequently, if we want to do
        // a LIMIT we want the most recent revisions, so we also need to know the total count to
        // supply as the OFFSET.
        $limitClause = '';
        if (intval($limit) > 0 && isset($numRevisions)) {
            $offset = $numRevisions - $limit;
            $limitClause = "LIMIT $offset, $limit";
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
                FROM $revTable AS revs
                LEFT JOIN $revTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                WHERE $userClause revs.rev_page = :pageid
                ORDER BY revs.rev_timestamp ASC
                $limitClause";

        $params = ['pageid' => $page->getId()];
        if ($user) {
            $params['username'] = $user->getUsername();
        }

        $conn = $this->getProjectsConnection();
        return $conn->executeQuery($sql, $params);
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

        $sql = "SELECT COUNT(*)
                FROM $revTable
                WHERE $userClause rev_page = :pageid";
        $params = ['pageid' => $page->getId()];
        if ($user) {
            $params['username'] = $user->getUsername();
        }
        $conn = $this->getProjectsConnection();
        return $conn->executeQuery($sql, $params)->fetchColumn(0);
    }

    /**
     * Get various basic info used in the API, including the
     *   number of revisions, unique authors, initial author
     *   and edit count of the initial author.
     * This is combined into one query for better performance.
     * Caching is intentionally disabled, because using the gadget,
     *   this will get hit for a different page constantly, where
     *   the likelihood of cache benefiting us is slim.
     * @param Page $page The page.
     * @return string[]
     */
    public function getBasicEditingInfo(Page $page)
    {
        $revTable = $this->getTableName($page->getProject()->getDatabaseName(), 'revision');
        $userTable = $this->getTableName($page->getProject()->getDatabaseName(), 'user');
        $pageTable = $this->getTableName($page->getProject()->getDatabaseName(), 'page');

        $sql = "SELECT *, (
                   SELECT user_editcount
                   FROM $userTable
                   WHERE user_name = author
                ) AS author_editcount
                FROM (
                    (
                        SELECT COUNT(*) AS num_edits,
                               COUNT(DISTINCT(rev_user_text)) AS num_editors
                        FROM $revTable
                        WHERE rev_page = :pageid
                    ) a,
                    (
                        # With really old pages, the rev_timestamp may need to be sorted ASC,
                        #   and the lowest rev_id may not be the first revision.
                        SELECT rev_user_text AS author,
                               rev_timestamp AS created_at,
                               rev_id AS created_rev_id
                        FROM $revTable
                        WHERE rev_page = :pageid
                        ORDER BY rev_timestamp ASC
                        LIMIT 1
                    ) b,
                    (
                        SELECT MAX(rev_timestamp) AS modified_at
                        FROM $revTable
                        WHERE rev_page = :pageid
                    ) c,
                    (
                        SELECT page_latest AS modified_rev_id
                        FROM $pageTable
                        WHERE page_id = :pageid
                    ) d
                );";
        $params = ['pageid' => $page->getId()];
        $conn = $this->getProjectsConnection();
        return $conn->executeQuery($sql, $params)->fetch();
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
        $paTable = $this->getTableName($project->getDatabaseName(), 'page_assessments');
        $papTable = $this->getTableName($project->getDatabaseName(), 'page_assessments_projects');
        $pageIds = implode($pageIds, ',');

        $query = "SELECT pap_project_title AS wikiproject, pa_class AS class, pa_importance AS importance
                  FROM $paTable
                  LEFT JOIN $papTable ON pa_project_id = pap_project_id
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
                        SELECT page_id FROM wikidatawiki_p.page
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
     * Get number of in and outgoing links and redirects to the given page.
     * @param Page $page
     * @return string[] Counts with the keys 'links_ext_count', 'links_out_count',
     *                  'links_in_count' and 'redirects_count'
     */
    public function countLinksAndRedirects(Page $page)
    {
        $externalLinksTable = $this->getTableName($page->getProject()->getDatabaseName(), 'externallinks');
        $pageLinksTable = $this->getTableName($page->getProject()->getDatabaseName(), 'pagelinks');
        $redirectTable = $this->getTableName($page->getProject()->getDatabaseName(), 'redirect');

        $sql = "SELECT COUNT(*) AS value, 'links_ext' AS type
                FROM $externalLinksTable WHERE el_from = :id
                UNION
                SELECT COUNT(*) AS value, 'links_out' AS type
                FROM $pageLinksTable WHERE pl_from = :id
                UNION
                SELECT COUNT(*) AS value, 'links_in' AS type
                FROM $pageLinksTable WHERE pl_namespace = :namespace AND pl_title = :title
                UNION
                SELECT COUNT(*) AS value, 'redirects' AS type
                FROM $redirectTable WHERE rd_namespace = :namespace AND rd_title = :title";

        $params = [
            'id' => $page->getId(),
            'title' => str_replace(' ', '_', $page->getTitleWithoutNamespace()),
            'namespace' => $page->getNamespace(),
        ];

        $conn = $this->getProjectsConnection();
        $res = $conn->executeQuery($sql, $params);

        $data = [];

        // Transform to associative array by 'type'
        foreach ($res as $row) {
            $data[$row['type'] . '_count'] = $row['value'];
        }

        return $data;
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

    /**
     * Get page views for the given page and timeframe.
     * @FIXME use Symfony Guzzle package.
     * @param Page $page
     * @param string|DateTime $start In the format YYYYMMDD
     * @param string|DateTime $end In the format YYYYMMDD
     * @return string[]
     */
    public function getPageviews(Page $page, $start, $end)
    {
        $title = rawurlencode(str_replace(' ', '_', $page->getTitle()));
        $client = new GuzzleHttp\Client();

        if ($start instanceof DateTime) {
            $start = $start->format('YYYYMMDD');
        }
        if ($end instanceof DateTime) {
            $end = $end->format('YYYYMMDD');
        }

        $project = $page->getProject()->getDomain();

        $url = 'https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/' .
            "$project/all-access/user/$title/daily/$start/$end";

        $res = $client->request('GET', $url);
        return json_decode($res->getBody()->getContents(), true);
    }
}
