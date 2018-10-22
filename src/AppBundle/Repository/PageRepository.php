<?php
/**
 * This file contains only the PageRepository class.
 */

declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Page;
use AppBundle\Model\Project;
use AppBundle\Model\User;
use DateTime;
use GuzzleHttp;
use Mediawiki\Api\SimpleRequest;

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
     * @return string[]|null Array with some of the following keys: pageid, title, missing, displaytitle, url.
     *   Returns null if page does not exist.
     */
    public function getPageInfo(Project $project, string $pageTitle): ?array
    {
        $info = $this->getPagesInfo($project, [$pageTitle]);
        return null !== $info ? array_shift($info) : null;
    }

    /**
     * Get metadata about a set of pages from the API.
     * @param Project $project The project to which the pages belong.
     * @param string[] $pageTitles Array of page titles.
     * @return string[]|null Array keyed by the page names, each element with some of the following keys: pageid,
     *   title, missing, displaytitle, url. Returns null if page does not exist.
     */
    public function getPagesInfo(Project $project, array $pageTitles): ?array
    {
        // @TODO: Also include 'extlinks' prop when we start checking for dead external links.
        $params = [
            'prop' => 'info|pageprops',
            'inprop' => 'protection|talkid|watched|watchers|notificationtimestamp|subjectid|url|readable|displaytitle',
            'converttitles' => '',
            // 'ellimit' => 20,
            // 'elexpandurl' => '',
            'titles' => join('|', $pageTitles),
            'formatversion' => 2,
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
        } else {
            return null;
        }
        return $result;
    }

    /**
     * Get the full page text of a set of pages.
     * @param Project $project The project to which the pages belong.
     * @param string[] $pageTitles Array of page titles.
     * @return string[] Array keyed by the page names, with the page text as the values.
     */
    public function getPagesWikitext(Project $project, array $pageTitles): array
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
    public function getRevisions(Page $page, ?User $user = null, $start = false, $end = false): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_revisions');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $stmt = $this->getRevisionsStmt($page, $user, null, null, $start, $end);
        $result = $stmt->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get the statement for a single revision, so that you can iterate row by row.
     * @param Page $page The page.
     * @param User|null $user Specify to get only revisions by the given user.
     * @param int $limit Max number of revisions to process.
     * @param int $numRevisions Number of revisions, if known. This is used solely to determine the
     *   OFFSET if we are given a $limit (see below). If $limit is set and $numRevisions is not set,
     *   a separate query is ran to get the number of revisions.
     * @param false|int $start
     * @param false|int $end
     * @return \Doctrine\DBAL\Driver\Statement
     */
    public function getRevisionsStmt(
        Page $page,
        ?User $user = null,
        ?int $limit = null,
        ?int $numRevisions = null,
        $start = false,
        $end = false
    ): \Doctrine\DBAL\Driver\Statement {
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

        $dateConditions = $this->getDateConditions($start, $end, 'revs.');

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
                WHERE $userClause revs.rev_page = :pageid $dateConditions
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
    public function getNumRevisions(Page $page, ?User $user = null, $start = false, $end = false): int
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_numrevisions');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // In this case revision is faster than revision_userindex if we're not querying by user.
        $revTable = $page->getProject()->getTableName(
            'revision',
            $user && $this->isLabs() ? '_userindex' : ''
        );
        $userClause = $user ? "rev_user_text = :username AND " : "";

        $dateConditions = $this->getDateConditions($start, $end);

        $sql = "SELECT COUNT(*)
                FROM $revTable
                WHERE $userClause rev_page = :pageid $dateConditions";
        $params = ['pageid' => $page->getId()];
        if ($user) {
            $params['username'] = $user->getUsername();
        }

        $result = (int)$this->executeProjectsQuery($sql, $params)->fetchColumn(0);

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get various basic info used in the API, including the number of revisions, unique authors, initial author
     * and edit count of the initial author. This is combined into one query for better performance. Caching is only
     * applied if it took considerable time to process, because using the gadget, this will get hit for a different page
     * constantly, where the likelihood of cache benefiting us is slim.
     * @param Page $page The page.
     * @return string[]|false false if the page was not found.
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
                        SELECT COUNT(rev_id) AS num_edits,
                            COUNT(DISTINCT(rev_user_text)) AS num_editors
                        FROM $revTable
                        WHERE rev_page = :pageid
                        AND rev_timestamp > 0 # Use rev_timestamp index
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
                        SELECT rev_timestamp AS modified_at,
                               rev_id AS modified_rev_id
                        FROM $revTable
                        JOIN $pageTable ON page_id = rev_page
                        WHERE rev_page = :pageid
                        AND rev_id = page_latest
                    ) c
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

        return $result ?? false;
    }

    /**
     * Get any CheckWiki errors of a single page
     * @param Page $page
     * @return array Results from query
     */
    public function getCheckWikiErrors(Page $page): array
    {
        // Only support mainspace on Labs installations
        if (0 !== $page->getNamespace() || !$this->isLabs()) {
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
    public function getWikidataInfo(Page $page): array
    {
        if (empty($page->getWikidataId())) {
            return [];
        }

        $wikidataId = 'Q'.ltrim($page->getWikidataId(), 'Q');
        $lang = $page->getProject()->getLang();

        $sql = "SELECT term_type AS term, term_text
                FROM wikidatawiki_p.wb_terms
                WHERE term_full_entity_id = :wikidataId
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
    public function getWikidataItems(Page $page, bool $count = false)
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
    public function countLinksAndRedirects(Page $page): array
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
            $data[$row['type'] . '_count'] = (int)$row['value'];
        }

        return $data;
    }

    /**
     * Count wikidata items for the given page, not just languages of sister projects
     * @param Page $page
     * @return int Number of records.
     */
    public function countWikidataItems(Page $page): int
    {
        return $this->getWikidataItems($page, true);
    }

    /**
     * Get page views for the given page and timeframe.
     * @fixme use Symfony Guzzle package.
     * @param Page $page
     * @param string|DateTime $start In the format YYYYMMDD
     * @param string|DateTime $end In the format YYYYMMDD
     * @return string[]
     */
    public function getPageviews(Page $page, $start, $end): array
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
     * @param Page $page
     * @param int $revId What revision to query for.
     * @return string
     */
    public function getHTMLContent(Page $page, ?int $revId = null): string
    {
        $client = new GuzzleHttp\Client();
        $url = $page->getUrl();
        if (null !== $revId) {
            $url .= "?oldid=$revId";
        }
        return $client->request('GET', $url)
            ->getBody()
            ->getContents();
    }

    /**
     * Get the ID of the revision of a page at the time of the given DateTime.
     * @param Page $page
     * @param DateTime $date
     * @return int
     */
    public function getRevisionIdAtDate(Page $page, DateTime $date): int
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

    /**
     * Get HTML display titles of a set of pages (or the normal title if there's no display title).
     * This will send t/50 API requests where t is the number of titles supplied.
     * @param Project $project The project.
     * @param string[] $pageTitles The titles to fetch.
     * @return string[] Keys are the original supplied title, and values are the display titles.
     * @static
     */
    public function displayTitles(Project $project, array $pageTitles): array
    {
        $client = $this->container->get('guzzle.client.xtools');

        $displayTitles = [];
        $numPages = count($pageTitles);

        for ($n = 0; $n < $numPages; $n += 50) {
            $titleSlice = array_slice($pageTitles, $n, 50);
            $res = $client->request('GET', $project->getApiUrl(), ['query' => [
                'action' => 'query',
                'prop' => 'info|pageprops',
                'inprop' => 'displaytitle',
                'titles' => join('|', $titleSlice),
                'format' => 'json',
            ]]);
            $result = json_decode($res->getBody()->getContents(), true);

            // Extract normalization info.
            $normalized = [];
            if (isset($result['query']['normalized'])) {
                array_map(
                    function ($e) use (&$normalized): void {
                        $normalized[$e['to']] = $e['from'];
                    },
                    $result['query']['normalized']
                );
            }

            // Match up the normalized titles with the display titles and the original titles.
            foreach ($result['query']['pages'] as $pageInfo) {
                $displayTitle = $pageInfo['pageprops']['displaytitle'] ?? $pageInfo['title'];
                $origTitle = $normalized[$pageInfo['title']] ?? $pageInfo['title'];
                $displayTitles[$origTitle] = $displayTitle;
            }
        }

        return $displayTitles;
    }
}
