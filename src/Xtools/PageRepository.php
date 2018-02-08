<?php
/**
 * This file contains only the PageRepository class.
 */

namespace Xtools;

use DateTime;
use Mediawiki\Api\SimpleRequest;
use GuzzleHttp;

/**
 * A PageRepository fetches data about Pages, either singularly or for multiple.
 * Despite the name, this does not have a direct correlation with the Pages tool.
 * @codeCoverageIgnore
 */
class PageRepository extends Repository
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
     * @param false|int $start
     * @param false|int $end
     * @return string[] Each member with keys: id, timestamp, length.
     */
    public function getRevisions(Page $page, User $user = null, $start = false, $end = false)
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_revisions');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $this->stopwatch->start($cacheKey, 'XTools');

        $stmt = $this->getRevisionsStmt($page, $user, null, null, $start, $end);
        $result = $stmt->fetchAll();

        // Cache and return.
        $this->stopwatch->stop($cacheKey);
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get the statement for a single revision, so that you can iterate row by row.
     * @param Page $page The page.
     * @param User|null $user Specify to get only revisions by the given user.
     * @param int $limit Max number of revisions to process.
     * @param int $numRevisions Number of revisions, if known. This is used solely to determine the
     *   OFFSET if we are given a $limit (see below). If $limit is set and $numRevisions is not set,
     *   a separate query is ran to get the nuber of revisions.
     * @param false|int $start
     * @param false|int $end
     * @return Doctrine\DBAL\Driver\PDOStatement
     */
    public function getRevisionsStmt(
        Page $page,
        User $user = null,
        $limit = null,
        $numRevisions = null,
        $start = false,
        $end = false
    ) {
        $revTable = $this->getTableName($page->getProject()->getDatabaseName(), 'revision');
        $userClause = $user ? "revs.rev_user_text = :username AND " : "";

        // This sorts ascending by rev_timestamp because ArticleInfo must start with the oldest
        // revision and work its way forward for proper processing. Consequently, if we want to do
        // a LIMIT we want the most recent revisions, so we also need to know the total count to
        // supply as the OFFSET.
        $limitClause = '';
        if (intval($limit) > 0 && isset($numRevisions)) {
            $offset = $numRevisions - $limit;
            $limitClause = "LIMIT $offset, $limit";
        }

        $datesConditions = $this->getDateConditions($start, $end, 'revs.');

        $sql = "SELECT
                    revs.rev_id AS id,
                    revs.rev_timestamp AS timestamp,
                    revs.rev_minor_edit AS minor,
                    revs.rev_len AS length,
                    (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                    revs.rev_user AS user_id,
                    revs.rev_user_text AS username,
                    revs.rev_comment AS comment,
                    revs.rev_sha1 AS sha
                FROM $revTable AS revs
                LEFT JOIN $revTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                WHERE $userClause revs.rev_page = :pageid $datesConditions
                ORDER BY revs.rev_timestamp ASC
                $limitClause";

        $params = ['pageid' => $page->getId()];
        if ($user) {
            $params['username'] = $user->getUsername();
        }

        return $this->executeProjectsQuery($sql, $params);
    }

    /**
     * Get a count of the number of revisions of a single page
     * @param Page $page The page.
     * @param User|null $user Specify to only count revisions by the given user.
     * @param false|int $start
     * @param false|int $end
     * @return int
     */
    public function getNumRevisions(Page $page, User $user = null, $start = false, $end = false)
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_numrevisions');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revTable = $page->getProject()->getTableName('revision');
        $userClause = $user ? "rev_user_text = :username AND " : "";

        $datesConditions = $this->getDateConditions($start, $end);

        $sql = "SELECT COUNT(*)
                FROM $revTable
                WHERE $userClause rev_page = :pageid $datesConditions";
        $params = ['pageid' => $page->getId()];
        if ($user) {
            $params['username'] = $user->getUsername();
        }

        $result = $this->executeProjectsQuery($sql, $params)->fetchColumn(0);

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get various basic info used in the API, including the
     *   number of revisions, unique authors, initial author
     *   and edit count of the initial author.
     * This is combined into one query for better performance.
     * Caching is only applied if it took considerable time to process,
     *   because using the gadget, this will get hit for a different page
     *   constantly, where the likelihood of cache benefiting us is slim.
     * @param Page $page The page.
     * @return string[]
     */
    public function getBasicEditingInfo(Page $page)
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_basicinfo');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

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

        // Get current time so we can compare timestamps
        // and decide whether or to cache the result.
        $time1 = time();

        /**
         * This query can sometimes take too long to run for pages with tens of thousands
         * of revisions. This query is used by the ArticleInfo gadget, which shows basic
         * data in real-time, so if it takes too long than the user probably didn't even
         * wait to see the result. We'll pass 60 as the last parameter to executeProjectsQuery,
         * which will set the max_statement_time to 60 seconds.
         */
        $result = $this->executeProjectsQuery($sql, $params, 60)->fetch();

        $time2 = time();

        // If it took over 5 seconds, cache the result for 20 minutes.
        if ($time2 - $time1 > 5) {
            $this->setCache($cacheKey, $result, 'PT20M');
        }

        return $result;
    }

    /**
     * Get assessment data for the given pages
     * @param Project   $project The project to which the pages belong.
     * @param  int[]    $pageIds Page IDs
     * @return string[] Assessment data as retrieved from the database.
     */
    public function getAssessments(Project $project, $pageIds)
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_assessments');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        if (!$project->hasPageAssessments()) {
            return [];
        }
        $paTable = $this->getTableName($project->getDatabaseName(), 'page_assessments');
        $papTable = $this->getTableName($project->getDatabaseName(), 'page_assessments_projects');
        $pageIds = implode($pageIds, ',');

        $sql = "SELECT pap_project_title AS wikiproject, pa_class AS class, pa_importance AS importance
                FROM $paTable
                LEFT JOIN $papTable ON pa_project_id = pap_project_id
                WHERE pa_page_id IN ($pageIds)";

        $result = $this->executeProjectsQuery($sql)->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
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

        $sql = "SELECT term_type AS term, term_text
                FROM wikidatawiki_p.wb_terms
                WHERE term_entity_id = :wikidataId
                AND term_type IN ('label', 'description')
                AND term_language = :lang";

        return $this->executeProjectsQuery($sql, [
            'lang' => $lang,
            'wikidataId' => $wikidataId,
        ])->fetchAll();
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

        $result = $this->executeProjectsQuery($sql, [
            'wikidataId' => $wikidataId,
        ])->fetchAll();

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

        $res = $this->executeProjectsQuery($sql, $params);
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
            $start = $start->format('Ymd');
        } else {
            $start = (new DateTime($start))->format('Ymd');
        }
        if ($end instanceof DateTime) {
            $end = $end->format('Ymd');
        } else {
            $end = (new DateTime($end))->format('Ymd');
        }

        $project = $page->getProject()->getDomain();

        $url = 'https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/' .
            "$project/all-access/user/$title/daily/$start/$end";

        $res = $client->request('GET', $url);
        return json_decode($res->getBody()->getContents(), true);
    }

    /**
     * Get the full HTML content of the the page.
     * @param  Page $page
     * @param  int $revId What revision to query for.
     * @return string
     */
    public function getHTMLContent(Page $page, $revId = null)
    {
        $client = new GuzzleHttp\Client();
        $url = $page->getUrl();
        if ($revId !== null) {
            $url .= "?oldid=$revId";
        }
        return $client->request('GET', $url)
            ->getBody()
            ->getContents();
    }

    /**
     * Get the ID of the revision of a page at the time of the given DateTime.
     * @param  Page     $page
     * @param  DateTime $date
     * @return int
     */
    public function getRevisionIdAtDate(Page $page, DateTime $date)
    {
        $revisionTable = $page->getProject()->getTableName('revision');
        $pageId = $page->getId();
        $datestamp = $date->format('YmdHis');
        $sql = "SELECT MAX(rev_id)
                FROM $revisionTable
                WHERE rev_timestamp <= $datestamp
                AND rev_page = $pageId LIMIT 1;";
        $resultQuery = $this->getProjectsConnection()->query($sql);
        return (int)$resultQuery->fetchColumn();
    }
}
