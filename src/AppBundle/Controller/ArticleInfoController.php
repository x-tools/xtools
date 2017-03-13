<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use AppBundle\Helper\Apihelper;
use AppBundle\Helper\PageviewsHelper;

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
        $this->ph = $this->get('app.pageviews_helper');
    }

    /**
     * @Route("/articleinfo", name="articleinfo")
     * @Route("/articleinfo", name="articleInfo")
     * @Route("/articleinfo/", name="articleInfoSlash")
     * @Route("/articleinfo/index.php", name="articleInfoIndexPhp")
     * @Route("/articleinfo/{project}", name="ArticleInfoProject")
     */
    public function indexAction($project = null)
    {
        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $projectQuery = $request->query->get('project');
        $article = $request->query->get('article');

        if ($projectQuery != '' && $article != '') {
            return $this->redirectToRoute('ArticleInfoResult', [ 'project'=>$projectQuery, 'article' => $article ] );
        }
        else if ($article != '') {
            return $this->redirectToRoute('ArticleInfoProject', [ 'project'=>$projectQuery ] );
        }

        return $this->render('articleInfo/index.html.twig', [
            'xtPage' => 'articleinfo',
            'xtTitle' => 'tool_articleinfo',
            'xtPageTitle' => 'tool_articleinfo',
            'xtSubtitle' => 'tool_articleinfo_desc',
            'project' => $project,
        ]);
    }

    /**
     * @Route("/articleinfo/{project}/{article}", name="ArticleInfoResult")
     */
    public function resultAction(Request $request)
    {
        $project = $request->attributes->get( 'project' );
        $page = $request->attributes->get( 'article' );

        // sets the database name within $this->lh
        $dbValues = $this->lh->databasePrepare( $project, 'ArticleInfo' );
        $projectUrl = $dbValues['url'];

        $this->revisionTable = $this->lh->getTable( 'revision' );

        $api = $this->get('app.api_helper');
        $basicInfo = $api->getBasicPageInfo( $projectUrl, $page, !$request->query->get('nofollowredir') );

        // TODO: throw error if $basicInfo['missing'] is set

        $this->pageInfo = [
            'project' => preg_replace( '#^https?://#', '', rtrim( $projectUrl, '/' ) ),
            'project_url' => $projectUrl,
            'id' => $basicInfo['pageid'],
            'namespace' => $basicInfo['ns'],
            'title' => $basicInfo['title'],
            'lastrevid' => $basicInfo['lastrevid'],
            'length' => $basicInfo['length'],
            'protection' => $basicInfo['protection'],
            'url' => $basicInfo['fullurl']
        ];

        $this->pageInfo['watchers'] = ( isset( $basicInfo['watchers'] ) ) ? $basicInfo['watchers'] : "< 30";

        $pageProps = isset( $basicInfo['pageprops'] ) ? $basicInfo['pageprops'] : [];

        if ( isset( $pageProps['wikibase_item'] ) ) {
            $this->pageInfo['wikidataId'] = $pageProps['wikibase_item'];
            $this->pageInfo['numWikidataItems'] = $this->getNumWikidataItems();
        }
        if ( isset( $pageProps['disambiguation'] ) ) {
            $this->pageInfo['isDisamb'] = true;
        }
        // $this->pageInfo['revision_count'] = $this->getRevCount();

        // TODO: Adapted from legacy code; may be used to indicate how many dead ext links there are
        // if ( isset( $basicInfo->extlinks ) ){
        //     foreach ( $basicInfo->extlinks as $i => $link ){
        //         $this->extLinks[] = array("link" => $link->{'*'}, "status" => "unchecked" );
        //     }
        // }

        // var_dump($this->pageInfo);

        $this->pageHistory = $this->getHistory();
        $this->historyCount = count( $this->getHistory() );
        $this->pageInfo = array_merge( $this->pageInfo, $this->parseHistory() );
        $this->pageInfo['bots'] = $this->getBotData();
        $this->pageInfo['bot_count'] = count( $this->pageInfo['bots'] );
        $this->pageInfo['top_ten_count'] = $this->getTopTenCount();
        $this->pageInfo['top_ten_percentage'] = round(
            ( $this->pageInfo['top_ten_count'] / $this->pageInfo['revision_count'] ) * 100, 1
        );
        $this->pageInfo = array_merge( $this->pageInfo, $this->getLinksAndRedirects() );
        $this->pageInfo['pageviews_offset'] = 60;
        $this->pageInfo['pageviews'] = $this->ph->sumLastDays(
            $this->pageInfo['project'],
            $this->pageInfo['title'],
            $this->pageInfo['pageviews_offset']
        );

        $this->pageInfo['xtPage'] = 'articleinfo';

        return $this->render("articleInfo/result.html.twig", $this->pageInfo);
    }

    /**
     * Quickly get number of revisions of page with ID $this->pageInfo['id']
     * May be used to bypass expensive processing if the page has a extremely large number of revision
     * @return integer Revision count
     */
    private function getRevCount() {
        $query = "SELECT COUNT(*) AS count FROM " . $this->revisionTable . " WHERE rev_page = '" . $this->pageInfo['id'] . "'";
        $res = $this->lh->client->query( $query )->fetchAll();
        return $res[0]['count'];
    }

    /**
     * Get number of wikidata items (not just languages of sister projects)
     * @return integer Number of items
     */
    private function getNumWikidataItems() {
        $query = "SELECT COUNT(*) AS count
                  FROM wikidatawiki_p.wb_items_per_site
                  WHERE ips_item_id = ". ltrim( $this->pageInfo['wikidataId'], 'Q' );
        $res = $this->lh->client->query( $query )->fetchAll();
        return $res[0]['count'];
    }

    /**
     * Get info about bots that edited the page
     * This also sets $this->pageInfo['bot_revision_count'] and $this->pageInfo['bot_percentage']
     * @return array Associative array containing the bot's username, edit count to the page
     *               and whether or not they are currently a bot
     */
    private function getBotData() {
        $userGroupsTable = $this->lh->getTable( 'user_groups' );
        $userFromerGroupsTable = $this->lh->getTable( 'user_former_groups' );
        $query = "SELECT COUNT(rev_user_text) AS count, rev_user_text AS username, ug_group AS current
                  FROM $this->revisionTable
                  JOIN $userGroupsTable ON rev_user = ug_user
                  LEFT JOIN $userFromerGroupsTable ON rev_user = ufg_user
                  WHERE rev_page = " . $this->pageInfo['id'] . " AND (ug_group = 'bot' OR ufg_group = 'bot')
                  GROUP BY rev_user_text";
        $res = $this->lh->client->query( $query )->fetchAll();

        // Parse the botedits
        $bots = [];
        $sum = 0;
        foreach ( $res as $bot ){
            $bots[] = [
                'username' =>$bot['username'],
                'count' => $bot['count'],
                'current' => $bot['current'] === 'bot'
            ];
            $sum += $bot['count'];
        }

        usort( $bots, function( $a, $b ) {
            return $b['count'] - $a['count'];
        } );

        $this->pageInfo['bot_revision_count'] = $sum;
        $this->pageInfo['bot_percentage'] = round( ( $sum / $this->pageInfo['revision_count'] ) * 100, 1 );

        return $bots;
    }

    /**
     * Get the number of edits made to the page by the top 10% of editors
     * This is ran *after* parseHistory() since we need the grand totals first.
     * Various stats are also set for each editor in $this->pageInfo['editors']
     * @return integer Number of edits
     */
    private function getTopTenCount() {
        $topTenCount = $counter = 0;

        foreach( $this->pageInfo['editors'] as $editor => $info ) {
            // Is the user in the top 10%?
            if ( $counter <= $this->pageInfo['editor_count'] * 0.1 ) {
                $topTenCount += $info['all'];
                $counter++;
            }

            $this->pageInfo['editors'][$editor]['minor_percentage'] = ( $info['all'] ) ?  ( $info['minor'] / $info['all'] ) * 100 : 0 ;

            if ( $info['all'] > 1 ) {
                $secs = intval( ( strtotime( $info['last'] ) - strtotime( $info['first'] ) ) / $info['all'] );
                $this->pageInfo['editors'][$editor]['atbe'] = $secs / ( 60 * 60 * 24 ) ;
            }

            if ( count( $info['sizes'] ) ) {
                $this->pageInfo['editors'][$editor]['size'] = array_sum( $info['sizes'] ) / count( $info['sizes'] ) ;
            }
            else {
                $this->pageInfo['editors'][$editor]['size'] = 0;
            }
        }

        return $topTenCount;
    }

    /**
     * Get number of in and outgoing links and redirects to the page
     * @return array Associative array containing counts
     */
    private function getLinksAndRedirects() {
        $pageId = $this->pageInfo['id'];
        $namespace = $this->pageInfo['namespace'];
        $title = str_replace( ' ', '_', $this->pageInfo['title'] );
        $externalLinksTable = $this->lh->getTable( 'externallinks' );
        $pageLinksTable = $this->lh->getTable( 'pagelinks' );
        $redirectTable = $this->lh->getTable( 'redirect' );

        // FIXME: probably need to make the $title mysql-safe or whatever
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

        $res = $this->lh->client->query( $query )->fetchAll();

        $data = [];

        // transform to associative array by 'type'
        foreach ($res as $row) {
            $data[$row['type'] . '_count'] = $row['value'];
        }

        return $data;
    }

    /**
     * Get every revision of the page
     * @return array The data
     */
    private function getHistory() {
        $userTable = $this->lh->getTable( 'user' );

        $query = "SELECT rev_id, rev_parent_id, rev_user_text, rev_user, rev_timestamp, rev_minor_edit, rev_len
                  FROM $this->revisionTable
                  WHERE rev_page = '" . $this->pageInfo['id'] . "' AND rev_timestamp > 1
                  ORDER BY rev_timestamp";

        $res = $this->lh->client->query( $query )->fetchAll();
        return $res;
    }

    /**
     * Parse the revision history, which should be at $this->pageHistory
     * @return array Associative "master" array of metadata about the page
     */
    private function parseHistory() {
        if ( $this->historyCount == 0 ){
            // $this->error = "no records";
            return;
        }

        $firstEdit = $this->pageHistory[0];

        // The month of the first edit. Used as a comparison when building the per-month data
        $firstEditMonth = strtotime( date( 'Y-m-01, 00:00', strtotime( $firstEdit['rev_timestamp'] ) ) );

        $lastEdit = $this->pageHistory[ $this->historyCount - 1 ];
        $secondLastEdit = $this->historyCount === 1 ? $lastEdit : $this->pageHistory[ $this->historyCount - 2 ];

        // Now we can start our master array. This one will be HUGE!
        $data = [
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
                'size' => $this->historyCount > 1 ? $lastEdit['rev_len'] - $secondLastEdit['rev_len'] : $lastEdit['rev_len']
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
            'year_count' => [],
            'revision_count' => count( $this->pageHistory ),
            'editors' => [],
            'editor_count' => 0,
            'anons' => [],
            'anon_count' => 0,
            'year_count' => [],
            'minor_count' => 0,
            'count_history' => [ 'day' => 0, 'week' => 0, 'month' => 0, 'year' => 0 ],
            'current_size' => $this->pageHistory[ $this->historyCount-1 ]['rev_len'],
            'textshares' => [],
            'textshare_total' => 0,
            'tools' => [],
            'automated_count' => 0
        ];

        // And now comes the logic for filling said master array
        foreach ( $this->pageHistory as $i => $rev ) {
            $newSize = $rev['rev_len'];
            $diffSize = $i > 0 ? $newSize - $this->pageHistory[$i - 1]['rev_len'] : $newSize;
            $timestamp = date_parse( $rev['rev_timestamp'] );
            $username = htmlspecialchars($rev['rev_user_text']);

            // Sometimes, with old revisions (2001 era), the revisions from 2002 come before 2001
            if ( strtotime( $rev['rev_timestamp'] ) < strtotime( $data['first_edit']['timestamp'] ) ) {
                $data['first_edit'] = [
                    'timestamp' => $rev['rev_timestamp'],
                    'user' => htmlspecialchars( $rev['rev_user_text'] ),
                    'size' => $rev['rev_len']
                ];
                // $first_edit_parse = date_parse( $data['first_edit']['timestamp'] );
            }

            // Fill in the blank arrays for the year and 12 months
            if ( !isset( $data['year_count'][$timestamp['year']] ) ) {
                $data['year_count'][$timestamp['year']] = [ 'all' => 0, 'minor' => 0, 'anon' => 0, 'months' => [] ];

                for( $i = 1; $i <= 12; $i++ ) {
                    $timeObj = mktime( 0, 0, 0, $i, 1, $timestamp['year'] );

                    // don't show zeros for months before the first edit or after the current month
                    if ( $timeObj < $firstEditMonth || $timeObj > strtotime('last day of this month') ) {
                        continue;
                    }

                    $data['year_count'][$timestamp['year']]['months'][$i] = [ 'all' => 0, 'minor' => 0, 'anon' => 0, 'size' => [] ];
                }
            }

            // Increment counts
            $data['year_count'][$timestamp['year']]['all']++;
            $data['year_count'][$timestamp['year']]['months'][$timestamp['month']]['all']++;
            $data['year_count'][$timestamp['year']]['months'][$timestamp['month']]['size'][] = number_format( ( $rev['rev_len'] / 1024 ), 2 );

            // Now to fill in various user stats
            if ( !isset( $data['editors'][$username] ) ) {
                $data['editor_count']++;
                $data['editors'][$username] = [
                    'all' => 0,
                    'minor' => 0,
                    'minor_percentage' => 0,
                    'first' => date( 'Y-m-d, H:i', strtotime( $rev['rev_timestamp'] ) ),
                    'first_id' => $rev['rev_id'],
                    'last' => null,
                    'atbe' => null,
                    'added' => 0,
                    'sizes' => [],
                    'urlencoded' => rawurlencode( $rev['rev_user_text'] ), //str_replace( array( '+' ), array( '_' ), urlencode( $rev['rev_user_text'] ) )
                ];
            }

            // Increment these counts...
            $data['editors'][$username]['all']++;
            $data['editors'][$username]['added'] += $diffSize;
            $data['editors'][$username]['last'] = date( 'Y-m-d, H:i', strtotime( $rev['rev_timestamp'] ) );
            $data['editors'][$username]['last_id'] = $rev['rev_id'];
            $data['editors'][$username]['sizes'][] = number_format( ( $rev['rev_len'] / 1024 ), 2 );

            // $newSize = ($rev["rev_parent_id"] != 0) ? $rev["rev_len"] - $this->markedRevisions[ $rev["rev_parent_id"] ]["rev_len"] : $rev["rev_len"];
            // $revert = isset($this->markedRevisions[ $rev["rev_id"] ]["revert"]) ? $this->markedRevisions[ $rev["rev_id"] ]["revert"] : false;

            // if ( !$revert ){
                if ( $newSize > 0 ){
                    $data['textshare_total'] += $newSize;
                    if ( !isset( $data['textshares'][$username]['all'] ) ){
                        $data['textshares'][$username]['all'] = 0;
                    }
                    $data['textshares'][$username]['all'] += $newSize;
                }
                if ( $diffSize > $data['max_add']['size'] ){
                    $data['max_add']['timestamp'] = \DateTime::createFromFormat('YmdHis', $rev['rev_timestamp'] );
                    $data['max_add']['revid'] = $rev['rev_id'];
                    $data['max_add']['user'] = $rev['rev_user_text'];
                    $data['max_add']['size'] = $diffSize;
                }
                if ( $diffSize < $data['max_del']['size'] ){
                    $data['max_del']['timestamp'] = \DateTime::createFromFormat('YmdHis', $rev['rev_timestamp'] );
                    $data['max_del']['revid'] = $rev['rev_id'];
                    $data['max_del']['user'] = $rev['rev_user_text'];
                    $data['max_del']['size'] = $diffSize;
                }
            // }


            if ( !$rev['rev_user'] ) {
                if ( !isset( $rev['rev_user']['anons'][$username] ) ) {
                    $data['anon_count']++;
                }
                //Anonymous, increase counts
                $data['anons'][] = $username;
                $data['year_count'][$timestamp['year']]['anon']++;
                $data['year_count'][$timestamp['year']]['months'][$timestamp['month']]['anon']++;
            }

            if ( $rev['rev_minor_edit'] ) {
                //Logged in, increase counts
                $data['minor_count']++;
                $data['year_count'][$timestamp['year']]['minor']++;
                $data['year_count'][$timestamp['year']]['months'][$timestamp['month']]['minor']++;
                $data['editors'][$username]['minor']++;
            }

//          if ( $this->checkAEB ){
//              foreach ( $this->AEBTypes as $tool => $signature ){
//                  if ( preg_match( $signature["regex"], $rev["rev_comment"]) ){
//                      $data['automated_count']++;
//                      $data['year_count'][$timestamp['year']]['automated']++;
//                      $data['year_count'][$timestamp['year']]['months'][$timestamp['month']]['automated']++;
//                      $data['tools'][$tool]++;
//                      break;
//                  }
//              }
//          }

            // Increment "edits per <time>" counts
            if ( strtotime( $rev['rev_timestamp'] ) > strtotime( '-1 day' ) ) $data['count_history']['day']++;
            if ( strtotime( $rev['rev_timestamp'] ) > strtotime( '-1 week' ) ) $data['count_history']['week']++;
            if ( strtotime( $rev['rev_timestamp'] ) > strtotime( '-1 month' ) ) $data['count_history']['month']++;
            if ( strtotime( $rev['rev_timestamp'] ) > strtotime( '-1 year' ) ) $data['count_history']['year']++;
        }

        // add percentages
        $data['minor_percentage'] = round( ( $data['minor_count'] / $data['revision_count'] ) * 100, 1 );
        $data['anon_percentage'] = round( ( $data['anon_count'] / $data['revision_count'] ) * 100, 1 );

        // other general statistics
        $data['datetime_first_edit'] = $dateFirst = \DateTime::createFromFormat('YmdHis', $data['first_edit']['timestamp']);
        $data['datetime_last_edit']  = $dateLast  = \DateTime::createFromFormat('YmdHis', $data['last_edit']['timestamp']);
        $interval = date_diff($dateLast, $dateFirst, true);

        $data['totaldays'] = $interval->format('%a');
        $data['average_days_per_edit'] = round( $data['totaldays'] / $data['revision_count'], 1 );
        $data['edits_per_day'] = round( $data['totaldays'] ? $data['revision_count'] / ( $data['totaldays'] / ( 365 / 12 / 24 ) ) : 0, 1 );
        $data['edits_per_month'] = round( $data['totaldays'] ? $data['revision_count'] / ( $data['totaldays'] / ( 365 / 12 ) ) : 0, 1 );
        $data['edits_per_year'] = round( $data['totaldays'] ? $data['revision_count'] / ( $data['totaldays'] / 365 ) : 0, 1 );
        $data['edits_per_editor'] = round( $data['revision_count'] / count( $data['editors'] ), 1 );

        // Various sorts
        arsort( $data['editors'] );
        arsort( $data['textshares'] );
        ksort( $data['year_count'] );

        return $data;
    }
}
