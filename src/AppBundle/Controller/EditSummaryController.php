<?php
/**
 * This file contains only the SimpleEditCounterController class.
 */

namespace AppBundle\Controller;

use Doctrine\DBAL\Connection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Cache\CacheItemPoolInterface;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use DateTime;
use DateInterval;

/**
 * This controller handles the Simple Edit Counter tool.
 */
class EditSummaryController extends XtoolsController
{

    /**
     * Get the tool's shortname.
     * @return string
     * @codeCoverageIgnore
     */
    public function getToolShortname()
    {
        return 'es';
    }

    /**
     * The Edit Summary search form.
     *
     * @param Request $request The HTTP request.
     *
     * @Route("/editsummary",           name="es")
     * @Route("/editsummary",           name="EditSummary")
     * @Route("/editsummary/",          name="EditSummarySlash")
     * @Route("/editsummary/index.php", name="EditSummaryIndexPhp")
     * @Route("/editsummary/{project}", name="EditSummaryProject")
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $params = $this->parseQueryParams($request);

        // If we've got a project, user, and namespace, redirect to results.
        if (isset($params['project']) && isset($params['username']) && isset($params['namespace'])) {
            return $this->redirectToRoute('EditSummaryResult', $params);
        }

        // Convert the given project (or default project) into a Project instance.
        $params['project'] = $this->getProjectFromQuery($params);

        // Show the form.
        return $this->render('editSummary/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-es',
            'xtSubtitle' => 'tool-es-desc',
            'xtPage' => 'es',

            // Defaults that will get overriden if in $params.
            'namespace' => 0,
        ], $params));
    }

    /**
     * Display the Edit Summary results
     *
     * @param Request $request The HTTP request.
     * @param string $namespace Namespace ID or 'all' for all namespaces.
     *
     * @Route("/editsummary/{project}/{username}/{namespace}", name="EditSummaryResult")
     *
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request, $namespace = 0)
    {
        $ret = $this->validateProjectAndUser($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($projectData, $user) = $ret;
        }

        $editSummaryUsage = $this->getEditSummaryUsage($projectData, $user, $namespace);

        // Assign the values and display the template
        return $this->render(
            'editSummary/result.html.twig',
            array_merge($editSummaryUsage, [
                'xtPage' => 'es',
                'xtTitle' => $user->getUsername(),
                'user' => $user,
                'project' => $projectData,
                'namespace' => $namespace,
            ])
        );
    }

    /**
     * Get data on edit summary usage of the given user
     * @param  Project $project
     * @param  User $user
     * @param  string $namespace
     * @return array
     * @todo Should we move this to an actual Repository? Very specific to this controller
     */
    private function getEditSummaryUsage(Project $project, User $user, $namespace)
    {
        $dbName = $project->getDatabaseName();

        $cacheKey = 'editsummaryusage.' . $dbName . '.'
            . $user->getCacheKey() . '.' . $namespace;

        $cache = $this->container->get('cache.app');
        if ($cache->hasItem($cacheKey)) {
            return $cache->getItem($cacheKey)->get();
        }

        // Load the database tables
        $revisionTable = $project->getRepository()->getTableName($dbName, 'revision');
        $pageTable = $project->getRepository()->getTableName($dbName, 'page');

        /**
         * Connection to the replica database
         *
         * @var Connection $conn
         */
        $conn = $this->get('doctrine')->getManager('replicas')->getConnection();

        $condNamespace = $namespace === 'all' ? '' : 'AND page_namespace = :namespace';
        $pageJoin = $namespace === 'all' ? '' : "JOIN $pageTable ON rev_page = page_id";
        $username = $user->getUsername();

        // Prepare the query and execute
        $sql = "SELECT rev_comment, rev_timestamp, rev_minor_edit
                FROM  $revisionTable
    ​            $pageJoin
                WHERE rev_user_text = :username
                $condNamespace
                ORDER BY rev_timestamp DESC";

        $resultQuery = $conn->prepare($sql);
        $resultQuery->bindParam('username', $username);
        if ($namespace !== 'all') {
            $resultQuery->bindParam('namespace', $namespace);
        }
        $resultQuery->execute();

        if ($resultQuery->errorCode() > 0) {
            $this->addFlash('notice', ['no-result', $username]);
            return $this->redirectToRoute(
                'EditSummaryProject',
                [
                    'project' => $project->getDomain()
                ]
            );
        }

        // Set defaults, so we don't get variable undefined errors
        $totalSummariesMajor = 0;
        $totalSummariesMinor = 0;
        $totalEditsMajor = 0;
        $totalEditsMinor = 0;
        $recentEditsMajor = 0;
        $recentEditsMinor = 0;
        $recentSummariesMajor = 0;
        $recentSummariesMinor = 0;
        $monthTotals = [];
        $monthEditsummaryTotals = [];
        $totalEdits = 0;
        $totalSummaries = 0;

        while ($row = $resultQuery->fetch()) {
            // Extract the date out of the date field
            $timestamp = DateTime::createFromFormat('YmdHis', $row['rev_timestamp']);

            $monthkey = date_format($timestamp, 'Y-m');

            // Check and see if the month is set for all major edits edits.
            // If not, default it to 1.
            if (!isset($monthTotals[$monthkey])) {
                $monthTotals[$monthkey] = 1;
            } else {
                $monthTotals[$monthkey]++;
            }

            // Grand total for number of edits
            $totalEdits++;

            // Total edit summaries
            if ($row['rev_comment'] !== '') {
                $totalSummaries++;
            }

            // Now do the same, if we have an edit summary
            if ($row['rev_minor_edit'] == 0) {
                if ($row['rev_comment'] !== '') {
                    isset($monthEditsummaryTotals[$monthkey]) ?
                        $monthEditsummaryTotals[$monthkey]++ :
                        $monthEditsummaryTotals[$monthkey] = 1;
                    $totalSummariesMajor++;
                }

                // Now do the same for recent edits
                $totalEditsMajor++;
                if ($recentEditsMajor < 150) {
                    $recentEditsMajor++;
                    if ($row['rev_comment'] != '') {
                        $recentSummariesMajor++;
                    }
                }
            } else {
                // The exact same procedure as documented above for minor edits
                // If there is a comment, count it
                if ($row['rev_comment'] !== '') {
                    isset($monthEditsummaryTotals[$monthkey]) ?
                        $monthEditsummaryTotals[$monthkey]++ :
                        $monthEditsummaryTotals[$monthkey] = 1;
                    $totalSummariesMinor++;
                    $totalEditsMinor++;
                } else {
                    $totalEditsMinor++;
                }

                // Handle recent edits
                if ($recentEditsMinor < 150) {
                    $recentEditsMinor++;
                    if ($row['rev_comment'] != '') {
                        $recentSummariesMinor++;
                    }
                }
            }
        }

        $result = [
            'totalEdits' => $totalEdits,
            'totalEditsMajor' => $totalEditsMajor,
            'totalEditsMinor' => $totalEditsMinor,
            'totalSummaries' => $totalSummaries,
            'totalSummariesMajor' => $totalSummariesMajor,
            'totalSummariesMinor' => $totalSummariesMinor,
            'recentEditsMajor' => $recentEditsMajor,
            'recentEditsMinor' => $recentEditsMinor,
            'recentSummariesMajor' => $recentSummariesMajor,
            'recentSummariesMinor' => $recentSummariesMinor,
            'monthTotals' => $monthTotals,
            'monthEditSumTotals' => $monthEditsummaryTotals,
        ];

        // Cache for 10 minutes, and return.
        $cacheItem = $cache->getItem($cacheKey)
            ->set($result)
            ->expiresAfter(new DateInterval('PT10M'));
        $cache->save($cacheItem);

        return $result;
    }
}
