<?php
/**
 * This file contains only the ArticleInfoController class.
 */

namespace AppBundle\Controller;

use AppBundle\Helper\AutomatedEditsHelper;
use AppBundle\Helper\LabsHelper;
use AppBundle\Helper\PageviewsHelper;
use Doctrine\DBAL\Connection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Xtools\ProjectRepository;
use Xtools\Page;
use Xtools\PagesRepository;
use Xtools\Edit;

/**
 * This controller serves the search form and results for the ArticleInfo tool
 */
class ArticleInfoController extends Controller
{
    /** @var LabsHelper The Labs helper object. */
    private $lh;
    /** @var mixed[] Information about the page in question. */
    private $pageInfo;
    /** @var Edit[] All edits of the page. */
    private $pageHistory;
    /** @var string The fully-qualified name of the revision table. */
    private $revisionTable;
    /** @var Connection The projects' database connection. */
    protected $conn;
    /** @var AutomatedEditsHelper The semi-automated edits helper. */
    protected $aeh;
    /** @var PageviewsHelper The page-views helper. */
    protected $ph;

    /**
     * Get the tool's shortname.
     * @return string
     */
    public function getToolShortname()
    {
        return 'articleinfo';
    }

    /**
     * Override method to call ArticleInfoController::containerInitialized() when container set.
     * @param ContainerInterface|null $container A ContainerInterface instance or null
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
        $this->conn = $this->getDoctrine()->getManager('replicas')->getConnection();
        $this->ph = $this->get('app.pageviews_helper');
        $this->aeh = $this->get('app.automated_edits_helper');
    }

    /**
     * The search form.
     * @Route("/articleinfo", name="articleinfo")
     * @Route("/articleinfo", name="articleInfo")
     * @Route("/articleinfo/", name="articleInfoSlash")
     * @Route("/articleinfo/index.php", name="articleInfoIndexPhp")
     * @Route("/articleinfo/{project}", name="ArticleInfoProject")
     * @param Request $request The HTTP request.
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $projectQuery = $request->query->get('project');
        $article = $request->query->get('article');

        if ($projectQuery != '' && $article != '') {
            return $this->redirectToRoute('ArticleInfoResult', [ 'project'=>$projectQuery, 'article' => $article ]);
        } elseif ($article != '') {
            return $this->redirectToRoute('ArticleInfoProject', [ 'project'=>$projectQuery ]);
        }

        if ($projectQuery == '') {
            $projectQuery = $this->container->getParameter('default_project');
        }

        $project = ProjectRepository::getProject($projectQuery, $this->container);

        return $this->render('articleInfo/index.html.twig', [
            'xtPage' => 'articleinfo',
            'xtPageTitle' => 'tool-articleinfo',
            'xtSubtitle' => 'tool-articleinfo-desc',
            'project' => $project,
        ]);
    }

    /**
     * Display the results.
     * @Route("/articleinfo/{project}/{article}", name="ArticleInfoResult", requirements={"article"=".+"})
     * @param Request $request The HTTP request.
     * @return Response
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

        if (!$page->exists()) {
            $this->addFlash('notice', ['no-exist', $pageQuery]);
            return $this->redirectToRoute('articleInfo');
        }

        $this->revisionTable = $project->getRepository()->getTableName(
            $project->getDatabaseName(),
            'revision'
        );

        // TODO: throw error if $basicInfo['missing'] is set

        $this->pageInfo = [
            'project' => $project,
            'projectUrl' => $projectUrl,
            'page' => $page,
            'dbName' => $dbName,
            'lang' => $project->getLang(),
        ];

        if ($page->getWikidataId()) {
            $this->pageInfo['numWikidataItems'] = $this->getNumWikidataItems();
        }

        // TODO: Adapted from legacy code; may be used to indicate how many dead ext links there are
        // if ( isset( $basicInfo->extlinks ) ){
        //     foreach ( $basicInfo->extlinks as $i => $link ){
        //         $this->extLinks[] = array("link" => $link->{'*'}, "status" => "unchecked" );
        //     }
        // }

        $this->pageHistory = $page->getRevisions();
        $this->pageInfo['firstEdit'] = new Edit($this->pageInfo['page'], $this->pageHistory[0]);
        $this->pageInfo['lastEdit'] = new Edit(
            $this->pageInfo['page'],
            $this->pageHistory[$page->getNumRevisions() - 1]
        );

        // NOTE: bots are fetched first in case we want to restrict some stats to humans editors only
        $this->pageInfo['bots'] = $this->getBotData();
        $this->pageInfo['general']['bot_count'] = count($this->pageInfo['bots']);

        $this->pageInfo = array_merge($this->pageInfo, $this->parseHistory());
        $this->pageInfo['general']['top_ten_count'] = $this->getTopTenCount();
        $this->pageInfo['general']['top_ten_percentage'] = round(
            ($this->pageInfo['general']['top_ten_count'] / $page->getNumRevisions()) * 100,
            1
        );
        $this->pageInfo = array_merge($this->pageInfo, $this->getLinksAndRedirects());
        $this->pageInfo['general']['pageviews_offset'] = 60;
        $this->pageInfo['general']['pageviews'] = $this->ph->sumLastDays(
            $this->pageInfo['project']->getDomain(),
            $this->pageInfo['page']->getTitle(),
            $this->pageInfo['general']['pageviews_offset']
        );
        $api = $this->get('app.api_helper');
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
        $this->pageInfo['xtTitle'] = $this->pageInfo['page']->getTitle();

        return $this->render("articleInfo/result.html.twig", $this->pageInfo);
    }

    /**
     * Get number of wikidata items (not just languages of sister projects)
     * @return integer Number of items
     */
    private function getNumWikidataItems()
    {
        $query = "SELECT COUNT(*) AS count
                  FROM wikidatawiki_p.wb_items_per_site
                  WHERE ips_item_id = ". ltrim($this->pageInfo['page']->getWikidataId(), 'Q');
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
                  WHERE rev_page = " . $this->pageInfo['page']->getId() . " AND (ug_group = 'bot' OR ufg_group = 'bot')
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
            ($sum / $this->pageInfo['page']->getNumRevisions()) * 100,
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
                        100 * ($info['all'] / $this->pageInfo['page']->getNumRevisions())
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
        $pageId = $this->pageInfo['page']->getId();
        $namespace = $this->pageInfo['page']->getNamespace();
        $title = str_replace(' ', '_', $this->pageInfo['page']->getTitle());
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
        $title = str_replace(' ', '_', $this->pageInfo['page']->getTitle());
        $query = "SELECT log_action, log_type, log_timestamp AS timestamp
                  FROM $loggingTable
                  WHERE log_namespace = '" . $this->pageInfo['page']->getNamespace() . "'
                  AND log_title = '$title' AND log_timestamp > 1
                  AND log_type IN ('delete', 'move', 'protect', 'stable')";
        $events = $this->conn->query($query)->fetchAll();

        foreach ($events as $event) {
            $time = strtotime($event['timestamp']);
            $year = date('Y', $time);
            if (isset($this->pageInfo['year_count'][$year])) {
                $yearEvents = $this->pageInfo['year_count'][$year]['events'];

                // Convert log type value to i18n key
                switch ($event['log_type']) {
                    case 'protect':
                        $action = 'protections';
                        break;
                    case 'delete':
                        $action = 'deletions';
                        break;
                    case 'move':
                        $action = 'moves';
                        break;
                    // count pending-changes protections along with normal protections
                    case 'stable':
                        $action = 'protections';
                        break;
                }

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
        if ($this->pageInfo['page']->getNamespace() !== 0 || !$this->container->getParameter('app.is_labs')) {
            return [];
        }
        $title = $this->pageInfo['page']->getTitle(); // no underscores
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
     * Get the size of the diff.
     * @param  int $revIndex The index of the revision within $this->pageHistory
     * @return int Size of the diff
     */
    private function getDiffSize($revIndex)
    {
        $rev = $this->pageHistory[$revIndex];

        if ($revIndex === 0) {
            return $rev['length'];
        }

        $lastRev = $this->pageHistory[$revIndex - 1];

        // TODO: Remove once T101631 is resolved
        // Treat as zero change in size if length of previous edit is missing
        if ($lastRev['length'] === null) {
            return 0;
        } else {
            return $rev['length'] - $lastRev['length'];
        }
    }

    /**
     * Parse the revision history, which should be at $this->pageHistory
     * @return array Associative "master" array of metadata about the page
     */
    private function parseHistory()
    {
        $revisionCount = $this->pageInfo['page']->getNumRevisions();
        if ($revisionCount == 0) {
            // $this->error = "no records";
            return;
        }

        $firstEdit = $this->pageInfo['firstEdit'];

        // Get UNIX timestamp of the first day of the month of the first edit
        // This is used as a comparison when building our array of per-month stats
        $firstEditMonth = mktime(0, 0, 0, (int) $firstEdit->getMonth(), 1, $firstEdit->getYear());

        $lastEdit = $this->pageInfo['lastEdit'];
        $secondLastEdit = $revisionCount === 1 ? $lastEdit : $this->pageHistory[ $revisionCount - 2 ];

        // Now we can start our master array. This one will be HUGE!
        $data = [
            'general' => [
                'max_add' => $firstEdit,
                'max_del' => $firstEdit,
                'editor_count' => 0,
                'anon_count' => 0,
                'minor_count' => 0,
                'count_history' => ['day' => 0, 'week' => 0, 'month' => 0, 'year' => 0],
                'current_size' => $this->pageHistory[$revisionCount-1]['length'],
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
            $edit = new Edit($this->pageInfo['page'], $rev);
            $diffSize = $this->getDiffSize($i);
            $username = htmlspecialchars($rev['username']);

            // Sometimes, with old revisions (2001 era), the revisions from 2002 come before 2001
            if ($edit->getTimestamp() < $firstEdit->getTimestamp()) {
                $firstEdit = $edit;
            }

            // Fill in the blank arrays for the year and 12 months
            if (!isset($data['year_count'][$edit->getYear()])) {
                $data['year_count'][$edit->getYear()] = [
                    'all' => 0,
                    'minor' => 0,
                    'anon' => 0,
                    'automated' => 0,
                    'size' => 0, // keep track of the size by the end of the year
                    'events' => [],
                    'months' => [],
                ];

                for ($i = 1; $i <= 12; $i++) {
                    $timeObj = mktime(0, 0, 0, $i, 1, $edit->getYear());

                    // don't show zeros for months before the first edit or after the current month
                    if ($timeObj < $firstEditMonth || $timeObj > strtotime('last day of this month')) {
                        continue;
                    }

                    $data['year_count'][$edit->getYear()]['months'][sprintf('%02d', $i)] = [
                        'all' => 0,
                        'minor' => 0,
                        'anon' => 0,
                        'automated' => 0,
                    ];
                }
            }

            // Increment year and month counts for all edits
            $data['year_count'][$edit->getYear()]['all']++;
            $data['year_count'][$edit->getYear()]['months'][$edit->getMonth()]['all']++;
            $data['year_count'][$edit->getYear()]['size'] = (int) $rev['length'];

            $editsThisMonth = $data['year_count'][$edit->getYear()]['months'][$edit->getMonth()]['all'];
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
                    'first' => date('Y-m-d, H:i', strtotime($rev['timestamp'])),
                    'first_id' => $rev['id'],
                    'last' => null,
                    'atbe' => null,
                    'added' => 0,
                    'sizes' => [],
                    'urlencoded' => rawurlencode($rev['username']),
                ];
            }

            // Increment user counts
            $data['editors'][$username]['all']++;
            $data['editors'][$username]['last'] = date('Y-m-d, H:i', strtotime($rev['timestamp']));
            $data['editors'][$username]['last_id'] = $rev['id'];

            // Store number of KB added with this edit
            $data['editors'][$username]['sizes'][] = $rev['length'] / 1024;

            // check if it was a revert
            if ($this->aeh->isRevert($rev['comment'])) {
                $data['general']['revert_count']++;
            } else {
                // edit was NOT a revert

                if ($edit->getSize() > 0) {
                    $data['general']['added'] += $edit->getSize();
                    $data['editors'][$username]['added'] += $edit->getSize();
                }

                // determine if the next revision was a revert
                $nextRevision = isset($this->pageHistory[$i + 1]) ? $this->pageHistory[$i + 1] : null;
                $nextRevisionIsRevert = $nextRevision &&
                    $this->getDiffSize($i + 1) === -$edit->getSize() &&
                    $this->aeh->isRevert($nextRevision['comment']);

                // don't count this edit as content removal if the next edit reverted it
                if (!$nextRevisionIsRevert && $edit->getSize() < $data['general']['max_del']->getSize()) {
                    $data['general']['max_del'] = $edit;
                }

                // FIXME: possibly remove this
                if ($edit->getLength() > 0) {
                    // keep track of added content
                    $data['general']['textshare_total'] += $edit->getLength();
                    if (!isset($data['textshares'][$username]['all'])) {
                        $data['textshares'][$username]['all'] = 0;
                    }
                    $data['textshares'][$username]['all'] += $edit->getLength();
                }

                if ($edit->getSize() > $data['general']['max_add']->getSize()) {
                    $data['general']['max_add'] = $edit;
                }
            }

            if ($edit->isAnon()) {
                if (!isset($rev['rev_user']['anons'][$username])) {
                    $data['general']['anon_count']++;
                }
                // Anonymous, increase counts
                $data['anons'][] = $username;
                $data['year_count'][$edit->getYear()]['anon']++;
                $data['year_count'][$edit->getYear()]['months'][$edit->getMonth()]['anon']++;
            }

            if ($edit->isMinor()) {
                // Logged in, increase counts
                $data['general']['minor_count']++;
                $data['year_count'][$edit->getYear()]['minor']++;
                $data['year_count'][$edit->getYear()]['months'][$edit->getMonth()]['minor']++;
                $data['editors'][$username]['minor']++;
            }

            $automatedTool = $this->aeh->getTool($rev['comment']);
            if ($automatedTool) {
                $data['general']['automated_count']++;
                $data['year_count'][$edit->getYear()]['automated']++;
                $data['year_count'][$edit->getYear()]['months'][$edit->getMonth()]['automated']++;

                if (!isset($data['tools'][$automatedTool])) {
                    $data['tools'][$automatedTool] = [
                        'count' => 1,
                        'link' => $this->aeh->getTools()[$automatedTool]['link'],
                    ];
                } else {
                    $data['tools'][$automatedTool]['count']++;
                }
            }

            // Increment "edits per <time>" counts
            if (strtotime($rev['timestamp']) > strtotime('-1 day')) {
                $data['general']['count_history']['day']++;
            }
            if (strtotime($rev['timestamp']) > strtotime('-1 week')) {
                $data['general']['count_history']['week']++;
            }
            if (strtotime($rev['timestamp']) > strtotime('-1 month')) {
                $data['general']['count_history']['month']++;
            }
            if (strtotime($rev['timestamp']) > strtotime('-1 year')) {
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
        $dateFirst = $firstEdit->getTimestamp();
        $dateLast = $lastEdit->getTimestamp();
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

        // If after processing max_del is positive, no edit actually removed text, so unset this value
        if ($data['general']['max_del']->getSize() > 0) {
            unset($data['general']['max_del']);
        }

        // Various sorts
        arsort($data['editors']);
        arsort($data['textshares']);
        arsort($data['tools']);
        ksort($data['year_count']);

        return $data;
    }
}
