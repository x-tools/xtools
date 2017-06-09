<?php

namespace AppBundle\Controller;

use DateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use AppBundle\Helper\Apihelper;
use AppBundle\Helper\PageviewsHelper;
use AppBundle\Helper\AutomatedEditsHelper;
use Xtools\ProjectRepository;
use Xtools\Page;
use Xtools\PagesRepository;
use Xtools\Edit;

class ArticleInfoController extends Controller
{
    private $lh;
    private $pageInfo;
    private $pageHistory;
    private $revisionTable;

    /**
     * Override method to call #containerInitialized method when container set.
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);
        $this->containerInitialized();
    }

    /**
     * Perform some operations after controller initialized and container set.
     */
    private function containerInitialized()
    {

        $this->lh = $this->get('app.labs_helper');
        $this->lh->checkEnabled('articleinfo');
        $this->conn = $this->getDoctrine()->getManager('replicas')->getConnection();
        $this->ph = $this->get('app.pageviews_helper');
        $this->aeh = $this->get('app.automated_edits_helper');
    }

    /**
     * @Route("/articleinfo", name="articleinfo")
     * @Route("/articleinfo", name="articleInfo")
     * @Route("/articleinfo/", name="articleInfoSlash")
     * @Route("/articleinfo/index.php", name="articleInfoIndexPhp")
     * @Route("/articleinfo/{project}", name="ArticleInfoProject")
     */
    public function indexAction(Request $request, $project = null)
    {
        $projectQuery = $request->query->get('project');
        $article = $request->query->get('article');

        if ($projectQuery != '' && $article != '') {
            return $this->redirectToRoute('ArticleInfoResult', [ 'project'=>$projectQuery, 'article' => $article ]);
        } elseif ($article != '') {
            return $this->redirectToRoute('ArticleInfoProject', [ 'project'=>$projectQuery ]);
        }

        return $this->render('articleInfo/index.html.twig', [
            'xtPage' => 'articleinfo',
            'xtPageTitle' => 'tool-articleinfo',
            'xtSubtitle' => 'tool-articleinfo-desc',
            'project' => $project,
        ]);
    }

    /**
     * @Route("/articleinfo/{project}/{article}", name="ArticleInfoResult", requirements={"article"=".+"})
     */
    public function resultAction(Request $request)
    {
        $projectQuery = $request->attributes->get('project');
        $project = ProjectRepository::getProject($projectQuery, $this->container);
        if (!$project->exists()) {
            $this->addFlash('notice', ['invalid-project', $projectQuery]);
            return $this->redirectToRoute('articleInfo');
        }
        $projectUrl = $project->getUrl();
        $dbName = $project->getDatabaseName();

        $pageQuery = $request->attributes->get('article');
        $page = new Page($project, $pageQuery);
        $pageRepo = new PagesRepository();
        $pageRepo->setContainer($this->container);
        $page->setRepository($pageRepo);

        $edit = new Edit($page, [
            'id' => 123,
            'timestamp' => '20170123235959',
            'minor' => '1',
            'length' => 1353,
            'length_change' => -523,
            'username' => 'MusikAnimal',
            'comment' => 'BLAH',
        ]);

        if (!$page->exists()) {
            $this->addFlash('notice', ['no-exist', $pageQuery]);
            return $this->redirectToRoute('articleInfo');
        }

        $this->revisionTable = $this->lh->getTable('revision', $dbName);

        $api = $this->get('app.api_helper');
        $basicInfo = $api->getBasicPageInfo($projectQuery, $pageQuery, !$request->query->get('nofollowredir'));

        // TODO: throw error if $basicInfo['missing'] is set

        $this->pageInfo = [
            'project' => $project,
            'projectUrl' => $projectUrl,
            'page' => $page,
            'dbName' => $dbName,
            'lang' => $project->getLang(),
            'id' => $basicInfo['pageid'],
            'namespace' => $basicInfo['ns'],
            'title' => $basicInfo['title'],
            'lastrevid' => $basicInfo['lastrevid'],
            'length' => $basicInfo['length'],
            'protection' => $basicInfo['protection'],
            'url' => $basicInfo['fullurl']
        ];

        $this->pageInfo['watchers'] = ( isset($basicInfo['watchers']) ) ? $basicInfo['watchers'] : "< 30";

        $pageProps = isset($basicInfo['pageprops']) ? $basicInfo['pageprops'] : [];

        if (isset($pageProps['wikibase_item'])) {
            $this->pageInfo['wikidataId'] = $pageProps['wikibase_item'];
            $this->pageInfo['numWikidataItems'] = $this->getNumWikidataItems();
        }
        if (isset($pageProps['disambiguation'])) {
            $this->pageInfo['isDisamb'] = true;
        }

        // TODO: Adapted from legacy code; may be used to indicate how many dead ext links there are
        // if ( isset( $basicInfo->extlinks ) ){
        //     foreach ( $basicInfo->extlinks as $i => $link ){
        //         $this->extLinks[] = array("link" => $link->{'*'}, "status" => "unchecked" );
        //     }
        // }

        $this->pageHistory = $this->getHistory();
        $this->pageInfo['general']['revision_count'] = count($this->pageHistory);

        // NOTE: bots are fetched first in case we want to restrict some stats to humans editors only
        $this->pageInfo['bots'] = $this->getBotData();
        $this->pageInfo['general']['bot_count'] = count($this->pageInfo['bots']);

        $this->pageInfo = array_merge($this->pageInfo, $this->parseHistory());
        $this->pageInfo['general']['top_ten_count'] = $this->getTopTenCount();
        $this->pageInfo['general']['top_ten_percentage'] = round(
            ($this->pageInfo['general']['top_ten_count'] / $this->pageInfo['general']['revision_count']) * 100,
            1
        );
        $this->pageInfo = array_merge($this->pageInfo, $this->getLinksAndRedirects());
        $this->pageInfo['general']['pageviews_offset'] = 60;
        $this->pageInfo['general']['pageviews'] = $this->ph->sumLastDays(
            $this->pageInfo['project']->getDomain(),
            $this->pageInfo['title'],
            $this->pageInfo['general']['pageviews_offset']
        );
        $assessments = $api->getPageAssessments($projectQuery, $pageQuery);
        if ($assessments) {
            $this->pageInfo['assessments'] = $assessments;
        }
        $this->setLogsEvents();

        $bugs = array_merge($this->getCheckWikiErrors(), $this->getWikidataErrors());
        if (!empty($bugs)) {
            $this->pageInfo['bugs'] = $bugs;
        }

        $this->pageInfo['xtPage'] = 'articleinfo';
        $this->pageInfo['xtTitle'] = $this->pageInfo['title'];

        return $this->render("articleInfo/result.html.twig", $this->pageInfo);
    }

    /**
     * Quickly get number of revisions of page with ID $this->pageInfo['id']
     * May be used to bypass expensive processing if the page has a extremely large number of revision
     * @return integer Revision count
     */
    private function getRevCount()
    {
        $query = "SELECT COUNT(*) AS count FROM " . $this->revisionTable
                 . " WHERE rev_page = '" . $this->pageInfo['id'] . "'";
        $res = $this->conn->query($query)->fetchAll();
        return $res[0]['count'];
    }

    /**
     * Get number of wikidata items (not just languages of sister projects)
     * @return integer Number of items
     */
    private function getNumWikidataItems()
    {
        $query = "SELECT COUNT(*) AS count
                  FROM wikidatawiki_p.wb_items_per_site
                  WHERE ips_item_id = ". ltrim($this->pageInfo['wikidataId'], 'Q');
        $res = $this->conn->query($query)->fetchAll();
        return $res[0]['count'];
    }

    /**
     * Get info about bots that edited the page
     * This also sets $this->pageInfo['bot_revision_count'] and $this->pageInfo['bot_percentage']
     * @return array Associative array containing the bot's username, edit count to the page
     *               and whether or not they are currently a bot
     */
    private function getBotData()
    {
        $userGroupsTable = $this->lh->getTable('user_groups', $this->pageInfo['dbName']);
        $userFromerGroupsTable = $this->lh->getTable('user_former_groups', $this->pageInfo['dbName']);
        $query = "SELECT COUNT(rev_user_text) AS count, rev_user_text AS username, ug_group AS current
                  FROM $this->revisionTable
                  LEFT JOIN $userGroupsTable ON rev_user = ug_user
                  LEFT JOIN $userFromerGroupsTable ON rev_user = ufg_user
                  WHERE rev_page = " . $this->pageInfo['id'] . " AND (ug_group = 'bot' OR ufg_group = 'bot')
                  GROUP BY rev_user_text";
        $res = $this->conn->query($query)->fetchAll();

        // Parse the botedits
        $bots = [];
        $sum = 0;
        foreach ($res as $bot) {
            $bots[$bot['username']] = [
                'count' => (int) $bot['count'],
                'current' => $bot['current'] === 'bot'
            ];
            $sum += $bot['count'];
        }

        uasort($bots, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        $this->pageInfo['general']['bot_revision_count'] = $sum;
        $this->pageInfo['general']['bot_percentage'] = round(
            ($sum / $this->pageInfo['general']['revision_count']) * 100,
            1
        );

        return $bots;
    }

    /**
     * Get the number of edits made to the page by the top 10% of editors
     * This is ran *after* parseHistory() since we need the grand totals first.
     * Various stats are also set for each editor in $this->pageInfo['editors']
     *   and top ten editors are stored in $this->pageInfo['general']['top_ten']
     *   to be used in the charts
     * @return integer Number of edits
     */
    private function getTopTenCount()
    {
        $topTenCount = $counter = 0;
        $topTenEditors = [];

        foreach ($this->pageInfo['editors'] as $editor => $info) {
            // Count how many users are in the top 10% by number of edits
            if ($counter < 10) {
                $topTenCount += $info['all'];
                $counter++;

                // To be used in the Top Ten charts
                $topTenEditors[] = [
                    'label' => $editor,
                    'value' => $info['all'],
                    'percentage' => (
                        100 * ($info['all'] / $this->pageInfo['general']['revision_count'])
                    )
                ];
            }

            // Compute the percentage of minor edits the user made
            $this->pageInfo['editors'][$editor]['minor_percentage'] = $info['all']
                ? ($info['minor'] / $info['all']) * 100
                : 0;

            if ($info['all'] > 1) {
                // Number of seconds between first and last edit
                $secs = intval(strtotime($info['last']) - strtotime($info['first']) / $info['all']);

                // Average time between edits (in days)
                $this->pageInfo['editors'][$editor]['atbe'] = $secs / ( 60 * 60 * 24 );
            }

            if (count($info['sizes'])) {
                // Average Total KB divided by number of stored sizes (user's edit count to this page)
                $this->pageInfo['editors'][$editor]['size'] = array_sum($info['sizes']) / count($info['sizes']);
            } else {
                $this->pageInfo['editors'][$editor]['size'] = 0;
            }
        }

        $this->pageInfo['topTenEditors'] = $topTenEditors;

        // First sort editors array by the amount of text they added
        $topTenEditorsByAdded = $this->pageInfo['editors'];
        uasort($topTenEditorsByAdded, function ($a, $b) {
            if ($a['added'] === $b['added']) {
                return 0;
            }
            return $a['added'] > $b['added'] ? -1 : 1;
        });

        // Then build a new array of top 10 editors by added text,
        //   in the data structure needed for the chart
        $this->pageInfo['topTenEditorsByAdded'] = array_map(function ($editor) {
            $added = $this->pageInfo['editors'][$editor]['added'];
            return [
                'label' => $editor,
                'value' => $added,
                'percentage' => (
                    100 * ($added / $this->pageInfo['general']['added'])
                )
            ];
        }, array_keys(array_slice($topTenEditorsByAdded, 0, 10)));

        return $topTenCount;
    }

    /**
     * Get number of in and outgoing links and redirects to the page
     * @return array Associative array containing counts
     */
    private function getLinksAndRedirects()
    {
        $pageId = $this->pageInfo['id'];
        $namespace = $this->pageInfo['namespace'];
        $title = str_replace(' ', '_', $this->pageInfo['title']);
        $externalLinksTable = $this->lh->getTable('externallinks', $this->pageInfo['dbName']);
        $pageLinksTable = $this->lh->getTable('pagelinks', $this->pageInfo['dbName']);
        $redirectTable = $this->lh->getTable('redirect', $this->pageInfo['dbName']);

        // FIXME: Probably need to make the $title mysql-safe or whatever
        $query = "SELECT COUNT(*) AS value, 'links_ext' AS type
                  FROM $externalLinksTable WHERE el_from = $pageId
                  UNION
                  SELECT COUNT(*) AS value, 'links_out' AS type
                  FROM $pageLinksTable WHERE pl_from = $pageId
                  UNION
                  SELECT COUNT(*) AS value, 'links_in' AS type
                  FROM $pageLinksTable WHERE pl_namespace = $namespace AND pl_title = \"$title\"
                  UNION
                  SELECT COUNT(*) AS value, 'redirects' AS type
                  FROM $redirectTable WHERE rd_namespace = $namespace AND rd_title = \"$title\"";

        $res = $this->conn->query($query)->fetchAll();

        $data = [];

        // Transform to associative array by 'type'
        foreach ($res as $row) {
            $data[$row['type'] . '_count'] = $row['value'];
        }

        return $data;
    }

    /**
     * Query for log events during each year of the article's history,
     *   and set the results in $this->pageInfo['year_count']
     */
    private function setLogsEvents()
    {
        $loggingTable = $this->lh->getTable('logging', $this->pageInfo['dbName'], 'logindex');
        $title = str_replace(' ', '_', $this->pageInfo['title']);
        $query = "SELECT log_action, log_type, log_timestamp AS timestamp
                  FROM $loggingTable
                  WHERE log_namespace = '" . $this->pageInfo['namespace'] . "'
                  AND log_title = '$title' AND log_timestamp > 1
                  AND log_type IN ('delete', 'move', 'protect', 'stable')";
        $events = $this->conn->query($query)->fetchAll();

        foreach ($events as $event) {
            $time = strtotime($event['timestamp']);
            $year = date('Y', $time);
            if (isset($this->pageInfo['year_count'][$year])) {
                $yearEvents = $this->pageInfo['year_count'][$year]['events'];

                // count pending-changes protections along with normal protections
                $action = $event['log_type'] === 'stable' ? 'protect' : $event['log_type'];

                if (empty($yearEvents[$action])) {
                    $yearEvents[$action] = 1;
                } else {
                    $yearEvents[$action]++;
                }

                $this->pageInfo['year_count'][$year]['events'] = $yearEvents;
            }
        }
    }

    /**
     * Get any CheckWiki errors
     * @return array Results from query
     */
    private function getCheckWikiErrors()
    {
        if ($this->pageInfo['namespace'] !== 0 || !$this->container->getParameter('app.is_labs')) {
            return [];
        }
        $title = $this->pageInfo['title']; // no underscores
        $dbName = preg_replace('/_p$/', '', $this->pageInfo['dbName']); // remove _p if present

        $query = "SELECT error, notice, found, name_trans AS name, prio, text_trans AS explanation
                  FROM s51080__checkwiki_p.cw_error a
                  JOIN s51080__checkwiki_p.cw_overview_errors b
                  WHERE a.project = b.project AND a.project = '$dbName'
                  AND a.title = '$title' AND a.error = b.id
                  AND b.done IS NULL";

        $conn = $this->container->get('doctrine')->getManager('toolsdb')->getConnection();
        $res = $conn->query($query)->fetchAll();
        return $res;
    }

    /**
     * Get basic wikidata on the page: label and description.
     * Reported as "bugs" if they are missing.
     * @return array Label and description, if present
     */
    private function getWikidataErrors()
    {
        if (empty($this->pageInfo['wikidataId'])) {
            return [];
        }

        $wikidataId = ltrim($this->pageInfo['wikidataId'], 'Q');
        $lang = $this->pageInfo['lang'];

        $query = "SELECT IF(term_type = 'label', 'label', 'description') AS term, term_text
                  FROM wikidatawiki_p.wb_entity_per_page
                  JOIN wikidatawiki_p.page ON epp_page_id = page_id
                  JOIN wikidatawiki_p.wb_terms ON term_entity_id = epp_entity_id
                    AND term_language = '$lang' AND term_type IN ('label', 'description')
                  WHERE epp_entity_id = $wikidataId
                  UNION
                  SELECT pl_title AS term, wb_terms.term_text
                  FROM wikidatawiki_p.pagelinks
                  JOIN wikidatawiki_p.wb_terms ON term_entity_id = SUBSTRING(pl_title, 2)
                    AND term_entity_type = (IF(SUBSTRING(pl_title, 1, 1) = 'Q', 'item', 'property'))
                    AND term_language = '$lang'
                    AND term_type = 'label'
                  WHERE pl_namespace IN (0,120 )
                  AND pl_from = (
                    SELECT page_id FROM page
                    WHERE page_namespace = 0 AND page_title = 'Q$wikidataId'
                  )";

        $conn = $this->container->get('doctrine')->getManager('replicas')->getConnection();
        $res = $conn->query($query)->fetchAll();

        $terms = array_map(function ($entry) {
            return $entry['term'];
        }, $res);

        $errors = [];

        if (!in_array('label', $terms)) {
            $errors[] = [
                'prio' => 2,
                'name' => 'Wikidata',
                'notice' => "Label for language <em>$lang</em> is missing", // FIXME: i18n
                'explanation' => "See: <a target='_blank' " .
                    "href='//www.wikidata.org/wiki/Help:Label'>Help:Label</a>",
            ];
        }
        if (!in_array('description', $terms)) {
            $errors[] = [
                'prio' => 3,
                'name' => 'Wikidata',
                'notice' => "Description for language <em>$lang</em> is missing", // FIXME: i18n
                'explanation' => "See: <a target='_blank' " .
                    "href='//www.wikidata.org/wiki/Help:Description'>Help:Description</a>",
            ];
        }

        return $errors;
    }

    /**
     * Get every revision of the page
     * @return array The data
     */
    private function getHistory()
    {
        $query = "SELECT rev_id, rev_parent_id, rev_user_text, rev_user, rev_timestamp,
                  rev_minor_edit, rev_len, rev_comment
                  FROM $this->revisionTable
                  WHERE rev_page = '" . $this->pageInfo['id'] . "' AND rev_timestamp > 1
                  ORDER BY rev_timestamp";

        $res = $this->conn->query($query)->fetchAll();
        return $res;
    }

    /**
     * Get the size of the diff
     * @param  int $rev The index of the revision within $this->pageHistory
     * @return int Size of the diff
     */
    private function getDiffSize($revIndex)
    {
        $rev = $this->pageHistory[$revIndex];

        if ($revIndex === 0) {
            return $rev['rev_len'];
        }

        $lastRev = $this->pageHistory[$revIndex - 1];

        // TODO: Remove once T101631 is resolved
        // Treat as zero change in size if rev_len of previous edit is missing
        if ($lastRev['rev_len'] === null) {
            return 0;
        } else {
            return $rev['rev_len'] - $lastRev['rev_len'];
        }
    }

    /**
     * Parse the revision history, which should be at $this->pageHistory
     * @return array Associative "master" array of metadata about the page
     */
    private function parseHistory()
    {
        $revisionCount = $this->pageInfo['general']['revision_count'];
        if ($revisionCount == 0) {
            // $this->error = "no records";
            return;
        }

        $firstEdit = $this->pageHistory[0];

        // The month of the first edit. Used as a comparison when building the per-month data
        $firstEditMonth = strtotime(date('Y-m-01, 00:00', strtotime($firstEdit['rev_timestamp'])));

        $lastEdit = $this->pageHistory[ $revisionCount - 1 ];
        $secondLastEdit = $revisionCount === 1 ? $lastEdit : $this->pageHistory[ $revisionCount - 2 ];

        // Now we can start our master array. This one will be HUGE!
        $lastEditSize = ($revisionCount > 1)
            ? $lastEdit['rev_len'] - $secondLastEdit['rev_len']
            : $lastEdit['rev_len'];
        $data = [
            'general' => [
                'first_edit' => [
                    'timestamp' => $firstEdit['rev_timestamp'],
                    'revid' => $firstEdit['rev_id'],
                    'user' => $firstEdit['rev_user_text'],
                    'size' => $firstEdit['rev_len']
                ],
                'last_edit' => [
                    'timestamp' => $lastEdit['rev_timestamp'],
                    'revid' => $lastEdit['rev_id'],
                    'user' => $lastEdit['rev_user_text'],
                    'size' => $lastEditSize,
                ],
                'max_add' => [
                    'timestamp' =>  null,
                    'revid' => null,
                    'user' => null,
                    'size' => -1000000,
                ],
                'max_del' => [
                    'timestamp' =>  null,
                    'revid' => null,
                    'user' => null,
                    'size' => 1000000,
                ],
                'editor_count' => 0,
                'anon_count' => 0,
                'minor_count' => 0,
                'count_history' => ['day' => 0, 'week' => 0, 'month' => 0, 'year' => 0],
                'current_size' => $this->pageHistory[$revisionCount-1]['rev_len'],
                'textshares' => [],
                'textshare_total' => 0,
                'automated_count' => 0,
                'revert_count' => 0,
                'added' => 0,
            ],
            'max_edits_per_month' => 0, // for bar chart in "Month counts" section
            'editors' => [],
            'anons' => [],
            'year_count' => [],
            'tools' => [],
        ];

        // restore existing general data
        $data['general'] = array_merge($data['general'], $this->pageInfo['general']);

        // And now comes the logic for filling said master array
        foreach ($this->pageHistory as $i => $rev) {
            $newSize = $rev['rev_len'];
            $diffSize = $this->getDiffSize($i);
            $timestamp = date_parse($rev['rev_timestamp']);
            $username = htmlspecialchars($rev['rev_user_text']);

            // Sometimes, with old revisions (2001 era), the revisions from 2002 come before 2001
            if (strtotime($rev['rev_timestamp']) < strtotime($data['general']['first_edit']['timestamp'])) {
                $data['general']['first_edit'] = [
                    'timestamp' => $rev['rev_timestamp'],
                    'user' => htmlspecialchars($rev['rev_user_text']),
                    'size' => $rev['rev_len']
                ];
            }

            // Fill in the blank arrays for the year and 12 months
            if (!isset($data['year_count'][$timestamp['year']])) {
                $data['year_count'][$timestamp['year']] = [
                    'all' => 0,
                    'minor' => 0,
                    'anon' => 0,
                    'automated' => 0,
                    'size' => 0, // keep track of the size by the end of the year
                    'events' => [],
                    'months' => [],
                ];

                for ($i = 1; $i <= 12; $i++) {
                    $timeObj = mktime(0, 0, 0, $i, 1, $timestamp['year']);

                    // don't show zeros for months before the first edit or after the current month
                    if ($timeObj < $firstEditMonth || $timeObj > strtotime('last day of this month')) {
                        continue;
                    }

                    $data['year_count'][$timestamp['year']]['months'][$i] = [
                        'all' => 0,
                        'minor' => 0,
                        'anon' => 0,
                        'automated' => 0,
                    ];
                }
            }

            // Increment year and month counts for all edits
            $data['year_count'][$timestamp['year']]['all']++;
            $data['year_count'][$timestamp['year']]['months'][$timestamp['month']]['all']++;
            $data['year_count'][$timestamp['year']]['size'] = (int) $rev['rev_len'];

            $editsThisMonth = $data['year_count'][$timestamp['year']]['months'][$timestamp['month']]['all'];
            if ($editsThisMonth > $data['max_edits_per_month']) {
                $data['max_edits_per_month'] = $editsThisMonth;
            }

            // Fill in various user stats
            if (!isset($data['editors'][$username])) {
                $data['general']['editor_count']++;
                $data['editors'][$username] = [
                    'all' => 0,
                    'minor' => 0,
                    'minor_percentage' => 0,
                    'first' => date('Y-m-d, H:i', strtotime($rev['rev_timestamp'])),
                    'first_id' => $rev['rev_id'],
                    'last' => null,
                    'atbe' => null,
                    'added' => 0,
                    'sizes' => [],
                    'urlencoded' => rawurlencode($rev['rev_user_text']),
                ];
            }

            // Increment user counts
            $data['editors'][$username]['all']++;
            $data['editors'][$username]['last'] = date('Y-m-d, H:i', strtotime($rev['rev_timestamp']));
            $data['editors'][$username]['last_id'] = $rev['rev_id'];

            // Store number of KB added with this edit
            $data['editors'][$username]['sizes'][] = $rev['rev_len'] / 1024;

            // check if it was a revert
            if ($this->aeh->isRevert($rev['rev_comment'])) {
                $data['general']['revert_count']++;
            } else {
                // edit was NOT a revert

                if ($diffSize > 0) {
                    $data['general']['added'] += $diffSize;
                    $data['editors'][$username]['added'] += $diffSize;
                }

                // determine if the next revision was a revert
                $nextRevision = isset($this->pageHistory[$i + 1]) ? $this->pageHistory[$i + 1] : null;
                $nextRevisionIsRevert = $nextRevision &&
                    $this->getDiffSize($i + 1) === -$diffSize &&
                    $this->aeh->isRevert($nextRevision['rev_comment']);

                // don't count this edit as content removal if the next edit reverted it
                if (!$nextRevisionIsRevert && $diffSize < $data['general']['max_del']['size']) {
                    $data['general']['max_del']['timestamp'] = DateTime::createFromFormat(
                        'YmdHis',
                        $rev['rev_timestamp']
                    );
                    $data['general']['max_del']['revid'] = $rev['rev_id'];
                    $data['general']['max_del']['user'] = $rev['rev_user_text'];
                    $data['general']['max_del']['size'] = $diffSize;
                }

                // FIXME: possibly remove this
                if ($newSize > 0) {
                    // keep track of added content
                    $data['general']['textshare_total'] += $newSize;
                    if (!isset($data['textshares'][$username]['all'])) {
                        $data['textshares'][$username]['all'] = 0;
                    }
                    $data['textshares'][$username]['all'] += $newSize;
                }

                if ($diffSize > $data['general']['max_add']['size']) {
                    $data['general']['max_add']['timestamp'] = DateTime::createFromFormat(
                        'YmdHis',
                        $rev['rev_timestamp']
                    );
                    $data['general']['max_add']['revid'] = $rev['rev_id'];
                    $data['general']['max_add']['user'] = $rev['rev_user_text'];
                    $data['general']['max_add']['size'] = $diffSize;
                }
            }

            if (!$rev['rev_user']) {
                if (!isset($rev['rev_user']['anons'][$username])) {
                    $data['general']['anon_count']++;
                }
                // Anonymous, increase counts
                $data['anons'][] = $username;
                $data['year_count'][$timestamp['year']]['anon']++;
                $data['year_count'][$timestamp['year']]['months'][$timestamp['month']]['anon']++;
            }

            if ($rev['rev_minor_edit']) {
                // Logged in, increase counts
                $data['general']['minor_count']++;
                $data['year_count'][$timestamp['year']]['minor']++;
                $data['year_count'][$timestamp['year']]['months'][$timestamp['month']]['minor']++;
                $data['editors'][$username]['minor']++;
            }

            $automatedTool = $this->aeh->getTool($rev['rev_comment']);
            if ($automatedTool) {
                $data['general']['automated_count']++;
                $data['year_count'][$timestamp['year']]['automated']++;
                $data['year_count'][$timestamp['year']]['months'][$timestamp['month']]['automated']++;

                if (!isset($data['tools'][$automatedTool])) {
                    $data['tools'][$automatedTool] = 1;
                } else {
                    $data['tools'][$automatedTool]++;
                }
            }

            // Increment "edits per <time>" counts
            if (strtotime($rev['rev_timestamp']) > strtotime('-1 day')) {
                $data['general']['count_history']['day']++;
            }
            if (strtotime($rev['rev_timestamp']) > strtotime('-1 week')) {
                $data['general']['count_history']['week']++;
            }
            if (strtotime($rev['rev_timestamp']) > strtotime('-1 month')) {
                $data['general']['count_history']['month']++;
            }
            if (strtotime($rev['rev_timestamp']) > strtotime('-1 year')) {
                $data['general']['count_history']['year']++;
            }
        }

        // add percentages
        $data['general']['minor_percentage'] = round(
            ($data['general']['minor_count'] / $revisionCount) * 100,
            1
        );
        $data['general']['anon_percentage'] = round(
            ($data['general']['anon_count'] / $revisionCount) * 100,
            1
        );

        // other general statistics
        $dateFirst = DateTime::createFromFormat('YmdHis', $data['general']['first_edit']['timestamp']);
        $dateLast = DateTime::createFromFormat('YmdHis', $data['general']['last_edit']['timestamp']);
        $data['general']['datetime_first_edit'] = $dateFirst;
        $data['general']['datetime_last_edit'] = $dateLast;
        $interval = date_diff($dateLast, $dateFirst, true);

        $data['totaldays'] = $interval->format('%a');
        $data['general']['average_days_per_edit'] = round($data['totaldays'] / $revisionCount, 1);
        $editsPerDay = $data['totaldays']
            ? $revisionCount / ($data['totaldays'] / (365 / 12 / 24))
            : 0;
        $data['general']['edits_per_day'] = round($editsPerDay, 1);
        $editsPerMonth = $data['totaldays']
            ? $revisionCount / ($data['totaldays'] / (365 / 12))
            : 0;
        $data['general']['edits_per_month'] = round($editsPerMonth, 1);
        $editsPerYear = $data['totaldays']
            ? $revisionCount / ($data['totaldays'] / 365)
            : 0;
        $data['general']['edits_per_year'] = round($editsPerYear, 1);
        $data['general']['edits_per_editor'] = round($revisionCount / count($data['editors']), 1);

        // Various sorts
        arsort($data['editors']);
        arsort($data['textshares']);
        arsort($data['tools']);
        ksort($data['year_count']);

        return $data;
    }
}
