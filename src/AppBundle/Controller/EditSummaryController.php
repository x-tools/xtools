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
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;

/**
 * This controller handles the Simple Edit Counter tool.
 */
class EditSummaryController extends Controller
{

    /**
     * Get the tool's shortname.
     * @return string
     */
    public function getToolShortname()
    {
        return 'es';
    }

    /**
     * The Edit Summary search form.
     *
     * @param Request $request The HTTP request.
     * @param string  $project The project database name or domain.
     *
     * @Route("/editsummary",           name="es")
     * @Route("/editsummary",           name="EditSummary")
     * @Route("/editsummary/",          name="EditSummarySlash")
     * @Route("/editsummary/index.php", name="EditSummaryIndexPhp")
     * @Route("/editsummary/{project}", name="EditSummaryProject")
     *
     * @return Response
     */
    public function indexAction(Request $request, $project = null)
    {
        // Get the query parameters.
        $projectName = $project ?: $request->query->get('project');
        $username = $request->query->get('username', $request->query->get('user'));

        // If we've got a project and user, redirect to results.
        if ($projectName != '' && $username != '') {
            $routeParams = [ 'project' => $projectName, 'username' => $username ];
            return $this->redirectToRoute('EditSummaryResult', $routeParams);
        }

        // Instantiate the project if we can, or use the default.
        $theProject = (!empty($projectName))
            ? ProjectRepository::getProject($projectName, $this->container)
            : ProjectRepository::getDefaultProject($this->container);

        // Show the form.
        return $this->render(
            'simpleEditCounter/index.html.twig',
            [
                'xtPageTitle' => 'tool-es',
                'xtSubtitle' => 'tool-es-desc',
                'xtPage' => 'es',
                'project' => $theProject,
            ]
        );
    }

    /**
     * Display the Edit Summary results
     *
     * @param string $project  The project domain name.
     * @param string $username The username.
     *
     * @Route("/editsummary/{project}/{username}", name="EditSummaryResult")
     *
     * @return Response
     */
    public function resultAction($project, $username)
    {
        /**
         * ProjectRepository object representing the project
         *
         * @var Project $project
         */
        $project = ProjectRepository::getProject($project, $this->container);
        $projectRepo = $project->getRepository();

        // Start by checking if the project exits.
        // If not, show a message and redirect
        if (!$project->exists()) {
            $this->addFlash('notice', ['invalid-project', $project]);
            return $this->redirectToRoute('EditSummary');
        }

        // Load the database tables
        $dbName = $project->getDatabaseName();

        $revisionTable = $projectRepo->getTableName($dbName, 'revision');
        $pageTable = $projectRepo->getTableName($dbName, 'page');

        /**
         * Connection to the replica database
         *
         * @var Connection $conn
         */
        $conn = $this->get('doctrine')->getManager('replicas')->getConnection();

        // Prepare the query and execute
        $resultQuery = $conn->prepare(
            "SELECT rev_comment, rev_timestamp, rev_minor_edit
            FROM  $revisionTable 
​            JOIN $pageTable ON page_id = rev_page
            WHERE rev_user_text = :username
            ORDER BY rev_timestamp DESC"
        );

        $user = new User($username);
        $usernameParam = $user->getUsername();
        $resultQuery->bindParam('username', $usernameParam);
        $resultQuery->execute();

        if ($resultQuery->errorCode() > 0) {
            $this->addFlash('notice', [ 'no-result', $username ]);
            return $this->redirectToRoute(
                'EditSummaryProject',
                [
                    'project' => $project->getDomain()
                ]
            );
        }

        // Set defaults, so we don't get variable undefined errors
        $edit_sum_maj = 0;
        $edit_sum_min = 0;
        $maj = 0;
        $minn = 0;
        $rmaj = 0;
        $rmin = 0;
        $redit_sum_maj = 0;
        $redit_sum_min = 0;
        $month_totals = array();
        $month_editsummary_totals = array();

        while ($row = $resultQuery->fetch()) {
            // Extract the date out of the date field
            preg_match(
                '/(\d\d\d\d)(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)/',
                $row['rev_timestamp'],
                $d
            );

            // Note there are some unused variables here.
            // This is expected, to make the regex work.
            list($arr,$year,$month,$day,$hour,$min,$sec) = $d;

            $monthkey = $year."/".$month;
            $first_month = strtotime("$year-$month-$day");

            // Check and see if the month is set for all major edits edits.
            // If not, default it to 1.
            if (!isset($month_totals[$monthkey])) {
                $month_totals[$monthkey] = 1;
            } else {
                $month_totals[$monthkey]++;
            }
            // Now do the same, if we have an edit summary
            if ($row['rev_minor_edit'] == 0) {
                if ($row['rev_comment'] !== '') {
                    isset($month_editsummary_totals[$monthkey]) ?
                        $month_editsummary_totals[$monthkey]++ :
                        $month_editsummary_totals[$monthkey] = 1;
                    $edit_sum_maj++;
                }

                // Now do the same for recent edits
                $maj++;
                if ($rmaj <= 149) {
                    $rmaj++;
                    if ($row['rev_comment'] != '') {
                        $redit_sum_maj++;
                    }
                }
            } else {
                // The exact same procedure as documented above for minor edits
                // If there is a comment, count it
                if ($row['rev_comment'] !== '') {
                    isset($month_editsummary_totals[$monthkey]) ?
                        $month_editsummary_totals[$monthkey]++ :
                        $month_editsummary_totals[$monthkey] = 1;
                    $edit_sum_min++;
                    $minn++;
                } else {
                    $minn++;
                }

                // Handle recent edits
                if ($rmin <= 149) {
                    $rmin++;
                    if ($row['rev_comment'] != '') {
                        $redit_sum_min++;
                    }
                }
            }
        }

        // Some rounding to make things look pretty
        $edit_sum_maj
            = sprintf('%.2f', $edit_sum_maj ? $edit_sum_maj / $maj : 0) * 100;
        $edit_sum_min
            = sprintf('%.2f', $edit_sum_min ? $edit_sum_min / $min : 0) * 100;
        $redit_sum_maj
            = sprintf('%.2f', $redit_sum_maj ? $redit_sum_maj / $rmaj : 0) * 100;
        $redit_sum_min
            = sprintf('%.2f', $redit_sum_min ? $redit_sum_min / $rmin : 0) * 100;

        // Assign the values and display the template
        return $this->render(
            'editSummary/result.html.twig',
            [
                'xtPage' => 'es',
                'xtTitle' => $username,
                'user' => $user,
                'project' => $project,

                "edit_sum_maj" => $edit_sum_maj,
                "edit_sum_min" => $edit_sum_min,
                "maj" => $maj,
                "minn" => $minn,
                "rmaj" => $rmaj,
                "rmin" => $rmin,
                "redit_sum_maj" => $redit_sum_maj,
                "redit_sum_min" => $redit_sum_min,
                "month_totals" => $month_totals,
                "month_editsummary_totals" => $month_editsummary_totals,
            ]
        );
    }
}
