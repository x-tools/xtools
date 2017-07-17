<?php
/**
 * This file contains only the ArticleInfoController class.
 */

namespace AppBundle\Controller;

use AppBundle\Helper\AutomatedEditsHelper;
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
use DateTime;

/**
 * This controller serves the search form and results for the ArticleInfo tool
 */
class ArticleInfoController extends Controller
{
    /** @var mixed[] Information about the page in question. */
    private $pageInfo;
    /** @var Edit[] All edits of the page. */
    private $pageHistory;
    /** @var ProjectRepository Shared Project repository for use of getting table names, etc. */
    private $projectRepo;
    /** @var string Database name, for us of getting table names, etc. */
    private $dbName;
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
        $this->projectRepo = $project->getRepository();
        if (!$project->exists()) {
            $this->addFlash('notice', ['invalid-project', $projectQuery]);
            return $this->redirectToRoute('articleInfo');
        }
        $this->dbName = $project->getDatabaseName();

        $pageQuery = $request->attributes->get('article');
        $page = new Page($project, $pageQuery);
        $pageRepo = new PagesRepository();
        $pageRepo->setContainer($this->container);
        $page->setRepository($pageRepo);

        if (!$page->exists()) {
            $this->addFlash('notice', ['no-exist', $pageQuery]);
            return $this->redirectToRoute('articleInfo');
        }

        $this->pageInfo = [
            'project' => $project,
            'page' => $page,
            'lang' => $project->getLang(),
        ];

        // TODO: Adapted from legacy code; may be used to indicate how many dead ext links there are
        // if ( isset( $basicInfo->extlinks ) ){
        //     foreach ( $basicInfo->extlinks as $i => $link ){
        //         $this->extLinks[] = array("link" => $link->{'*'}, "status" => "unchecked" );
        //     }
        // }

        $this->pageInfo = array_merge($this->pageInfo, $this->parseHistory($page));
        $this->pageInfo['bots'] = $this->getBotData();
        $this->pageInfo['general']['bot_count'] = count($this->pageInfo['bots']);
        $this->pageInfo['general']['top_ten_count'] = $this->getTopTenCount();
        $this->pageInfo['general']['top_ten_percentage'] = round(
            ($this->pageInfo['general']['top_ten_count'] / $page->getNumRevisions()) * 100,
            1
        );
        $this->pageInfo = array_merge($this->pageInfo, $page->countLinksAndRedirects());
        $this->pageInfo['general']['pageviews_offset'] = 60;
        $this->pageInfo['general']['pageviews'] = $this->ph->sumLastDays(
            $this->pageInfo['project']->getDomain(),
            $this->pageInfo['page']->getTitle(),
            $this->pageInfo['general']['pageviews_offset']
        );

        $assessments = $page->getAssessments();
        if ($assessments) {
            $this->pageInfo['assessments'] = $assessments;
        }
        $this->setLogsEvents($page);

        $bugs = $page->getErrors();
        if (!empty($bugs)) {
            $this->pageInfo['bugs'] = $bugs;
        }

        $this->pageInfo['xtPage'] = 'articleinfo';
        $this->pageInfo['xtTitle'] = $page->getTitle();
        $this->pageInfo['editorlimit'] = $request->query->get('editorlimit', 20);

        // Output the relevant format template.
        $format = $request->query->get('format', 'html');
        if ($format == '') {
            // The default above doesn't work when the 'format' parameter is blank.
            $format = 'html';
        }
        $response = $this->render("articleInfo/result.$format.twig", $this->pageInfo);
        if ($format == 'wikitext') {
            $response->headers->set('Content-Type', 'text/plain');
        }
        return $response;
    }

    /**
     * Get info about bots that edited the page
     * This also sets $this->pageInfo['bot_revision_count'] and $this->pageInfo['bot_percentage']
     * @return array Associative array containing the bot's username, edit count to the page
     *               and whether or not they are currently a bot
     */
    private function getBotData()
    {
        $userGroupsTable = $this->projectRepo->getTableName($this->dbName, 'user_groups');
        $userFromerGroupsTable = $this->projectRepo->getTableName($this->dbName, 'user_former_groups');
        $query = "SELECT COUNT(rev_user_text) AS count, rev_user_text AS username, ug_group AS current
                  FROM " . $this->projectRepo->getTableName($this->dbName, 'revision') . "
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
                $secs = $info['last']->getTimestamp() - $info['first']->getTimestamp();

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
     * Query for log events during each year of the article's history,
     *   and set the results in $this->pageInfo['year_count']
     * @param Page $page
     */
    private function setLogsEvents($page)
    {
        $loggingTable = $this->projectRepo->getTableName($this->dbName, 'logging', 'logindex');
        $title = str_replace(' ', '_', $page->getTitle());
        $sql = "SELECT log_action, log_type, log_timestamp AS timestamp
                FROM $loggingTable
                WHERE log_namespace = '" . $page->getNamespace() . "'
                AND log_title = :title AND log_timestamp > 1
                AND log_type IN ('delete', 'move', 'protect', 'stable')";
        $resultQuery = $this->conn->prepare($sql);
        $resultQuery->bindParam(':title', $title);
        $resultQuery->execute();
        $events = $resultQuery->fetchAll();

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
     * Parse the revision history. This also sets some $this->pageInfo vars
     *   like 'firstEdit' and 'lastEdit'
     * @param Page $page Page to parse
     * @return array Associative "master" array of metadata about the page
     */
    private function parseHistory(Page $page)
    {
        $revStmt = $page->getRevisionsStmt();
        $revCount = 0;

        /** @var string[] Master array containing all the data we need */
        $data = [
            'general' => [
                'max_add' => null, // Edit
                'max_del' => null, // Edit
                'editor_count' => 0,
                'anon_count' => 0,
                'minor_count' => 0,
                'count_history' => ['day' => 0, 'week' => 0, 'month' => 0, 'year' => 0],
                'current_size' => null,
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

        /** @var Edit|null */
        $firstEdit = null;

        /** @var Edit|null The previous edit, used to discount content that was reverted */
        $prevEdit = null;

        /**
         * The edit previously deemed as having the maximum amount of content added.
         * This is used to discount content that was reverted.
         * @var Edit|null
        */
        $prevMaxAddEdit = null;

        /**
         * The edit previously deemed as having the maximum amount of content deleted.
         * This is used to discount content that was reverted
         * @var Edit|null
         */
        $prevMaxDelEdit = null;

        /** @var Time|null Time of first revision, used as a comparison for month counts */
        $firstEditMonth = null;

        while ($rev = $revStmt->fetch()) {
            $edit = new Edit($this->pageInfo['page'], $rev);

            // Some shorthands
            $editYear = $edit->getYear();
            $editMonth = $edit->getMonth();
            $editTimestamp = $edit->getTimestamp();

            // Don't return actual edit size if last revision had a length of null.
            // This happens when the edit follows other edits that were revision-deleted.
            // See T148857 for more information.
            // @TODO: Remove once T101631 is resolved
            if ($prevEdit && $prevEdit->getLength() === null) {
                $editSize = 0;
            } else {
                $editSize = $edit->getSize();
            }

            if ($revCount === 0) {
                $firstEdit = $edit;
                $firstEditMonth = mktime(0, 0, 0, (int) $firstEdit->getMonth(), 1, $firstEdit->getYear());
            }

            $username = $edit->getUser()->getUsername();

            // Sometimes, with old revisions (2001 era), the revisions from 2002 come before 2001
            if ($editTimestamp < $firstEdit->getTimestamp()) {
                $firstEdit = $edit;
            }

            // Fill in the blank arrays for the year and 12 months
            if (!isset($data['year_count'][$editYear])) {
                $data['year_count'][$editYear] = [
                    'all' => 0,
                    'minor' => 0,
                    'anon' => 0,
                    'automated' => 0,
                    'size' => 0, // keep track of the size by the end of the year
                    'events' => [],
                    'months' => [],
                ];

                for ($i = 1; $i <= 12; $i++) {
                    $timeObj = mktime(0, 0, 0, $i, 1, $editYear);

                    // don't show zeros for months before the first edit or after the current month
                    if ($timeObj < $firstEditMonth || $timeObj > strtotime('last day of this month')) {
                        continue;
                    }

                    $data['year_count'][$editYear]['months'][sprintf('%02d', $i)] = [
                        'all' => 0,
                        'minor' => 0,
                        'anon' => 0,
                        'automated' => 0,
                    ];
                }
            }

            // Increment year and month counts for all edits
            $data['year_count'][$editYear]['all']++;
            $data['year_count'][$editYear]['months'][$editMonth]['all']++;
            // This will ultimately be the size of the page by the end of the year
            $data['year_count'][$editYear]['size'] = $edit->getLength();

            // Keep track of which month had the most edits
            $editsThisMonth = $data['year_count'][$editYear]['months'][$editMonth]['all'];
            if ($editsThisMonth > $data['max_edits_per_month']) {
                $data['max_edits_per_month'] = $editsThisMonth;
            }

            // Initialize various user stats
            if (!isset($data['editors'][$username])) {
                $data['general']['editor_count']++;
                $data['editors'][$username] = [
                    'all' => 0,
                    'minor' => 0,
                    'minor_percentage' => 0,
                    'first' => $editTimestamp,
                    'first_id' => $edit->getId(),
                    'last' => null,
                    'atbe' => null,
                    'added' => 0,
                    'sizes' => [],
                ];
            }

            // Increment user counts
            $data['editors'][$username]['all']++;
            $data['editors'][$username]['last'] = $editTimestamp;
            $data['editors'][$username]['last_id'] = $edit->getId();

            // Store number of KB added with this edit
            $data['editors'][$username]['sizes'][] = $edit->getLength() / 1024;

            // Check if it was a revert
            if ($this->aeh->isRevert($edit->getComment())) {
                $data['general']['revert_count']++;

                // Since this was a revert, we don't want to treat the previous
                //   edit as legit content addition or removal
                if ($prevEdit && $prevEdit->getSize() > 0) {
                    $data['general']['added'] -= $prevEdit->getSize();
                }

                // @TODO: Test this against an edit war (use your sandbox)
                // Also remove as max added or deleted, if applicable
                if ($data['general']['max_add'] &&
                    $prevEdit->getId() === $data['general']['max_add']->getId()
                ) {
                    $data['general']['max_add'] = $prevMaxAddEdit;
                    $prevMaxAddEdit = $prevEdit; // in the event of edit wars
                } elseif ($data['general']['max_del'] &&
                    $prevEdit->getId() === $data['general']['max_del']->getId()
                ) {
                    $data['general']['max_del'] = $prevMaxDelEdit;
                    $prevMaxDelEdit = $prevEdit; // in the event of edit wars
                }
            } else {
                // Edit was not a revert, so treat size > 0 as content added
                if ($editSize > 0) {
                    $data['general']['added'] += $editSize;
                    $data['editors'][$username]['added'] += $editSize;

                    // Keep track of edit with max addition
                    if (!$data['general']['max_add'] || $editSize > $data['general']['max_add']->getSize()) {
                        // Keep track of old max_add in case we find out the next $edit was reverted
                        //   (and was also a max edit), in which case we'll want to use this one ($edit)
                        $prevMaxAddEdit = $data['general']['max_add'];

                        $data['general']['max_add'] = $edit;
                    }
                } elseif ($editSize < 0 && (
                    !$data['general']['max_del'] || $editSize < $data['general']['max_del']->getSize()
                )) {
                    $data['general']['max_del'] = $edit;
                }
            }

            // If anonymous, increase counts
            if ($edit->isAnon()) {
                $data['general']['anon_count']++;
                $data['year_count'][$editYear]['anon']++;
                $data['year_count'][$editYear]['months'][$editMonth]['anon']++;
            }

            // If minor edit, increase counts
            if ($edit->isMinor()) {
                $data['general']['minor_count']++;
                $data['year_count'][$editYear]['minor']++;
                $data['year_count'][$editYear]['months'][$editMonth]['minor']++;

                // Increment minor counts for this user
                $data['editors'][$username]['minor']++;
            }

            $automatedTool = $this->aeh->getTool($edit->getComment());
            if ($automatedTool) {
                $data['general']['automated_count']++;
                $data['year_count'][$editYear]['automated']++;
                $data['year_count'][$editYear]['months'][$editMonth]['automated']++;

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
            if ($editTimestamp > new DateTime('-1 day')) {
                $data['general']['count_history']['day']++;
            }
            if ($editTimestamp > new DateTime('-1 week')) {
                $data['general']['count_history']['week']++;
            }
            if ($editTimestamp > new DateTime('-1 month')) {
                $data['general']['count_history']['month']++;
            }
            if ($editTimestamp > new DateTime('-1 year')) {
                $data['general']['count_history']['year']++;
            }

            $revCount++;
            $prevEdit = $edit;
            $lastEdit = $edit;
        }

        // add percentages
        $data['general']['minor_percentage'] = round(
            ($data['general']['minor_count'] / $revCount) * 100,
            1
        );
        $data['general']['anon_percentage'] = round(
            ($data['general']['anon_count'] / $revCount) * 100,
            1
        );

        // other general statistics
        $dateFirst = $firstEdit->getTimestamp();
        $dateLast = $lastEdit->getTimestamp();
        $data['general']['datetime_first_edit'] = $dateFirst;
        $data['general']['datetime_last_edit'] = $dateLast;
        $interval = date_diff($dateLast, $dateFirst, true);

        $data['totaldays'] = $interval->format('%a');
        $data['general']['average_days_per_edit'] = round($data['totaldays'] / $revCount, 1);
        $editsPerDay = $data['totaldays']
            ? $revCount / ($data['totaldays'] / (365 / 12 / 24))
            : 0;
        $data['general']['edits_per_day'] = round($editsPerDay, 1);
        $editsPerMonth = $data['totaldays']
            ? $revCount / ($data['totaldays'] / (365 / 12))
            : 0;
        $data['general']['edits_per_month'] = round($editsPerMonth, 1);
        $editsPerYear = $data['totaldays']
            ? $revCount / ($data['totaldays'] / 365)
            : 0;
        $data['general']['edits_per_year'] = round($editsPerYear, 1);
        $data['general']['edits_per_editor'] = round($revCount / count($data['editors']), 1);

        $data['firstEdit'] = $firstEdit;
        $data['lastEdit'] = $lastEdit;

        // Various sorts
        arsort($data['editors']);
        arsort($data['tools']);
        ksort($data['year_count']);

        return $data;
    }
}
