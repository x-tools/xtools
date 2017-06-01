<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xtools\ProjectRepository;

class AutomatedEditsController extends Controller
{
    /**
     * @Route("/autoedits", name="autoedits")
     * @Route("/automatededits", name="autoeditsLong")
     * @Route("/autoedits/index.php", name="autoeditsIndexPhp")
     * @Route("/automatededits/index.php", name="autoeditsLongIndexPhp")
     */

    public function indexAction(Request $request)
    {
        // Pull the labs helper and check if enabled
        $lh = $this->get("app.labs_helper");
        $lh->checkEnabled("autoedits");

        // Pull the values out of the query string. These values default to
        // empty strings.
        $project = $request->query->get('project');
        $username = $request->query->get('username');
        $startDate = $request->query->get('start');
        $endDate = $request->query->get('end');

        // Redirect if the values are set.
        if ($project != "" && $username != "" && ($startDate != "" || $endDate != "")) {
            // Redirect to the route fully if we have the username project, and any date

            // Set start date to beginning of time if end date is provided
            // This is nasty, but necessary given URL structure
            if ($startDate === "") {
                $startDate = date('Y-m-d', 0);
            }

            return $this->redirectToRoute(
                "autoeditsResult",
                [
                    'project' => $project,
                    'username' => $username,
                    'start' => $startDate,
                    'end' => $endDate,
                ]
            );
        } elseif ($project != "" && $username != "") {
            // Redirect if we have the username and project
            return $this->redirectToRoute(
                "autoeditsResult",
                [
                    'project' => $project,
                    'username' => $username,
                ]
            );
        } elseif ($project != "") {
            // Redirect if we have the project name
            return $this->redirectToRoute(
                "autoeditsResult",
                [
                    'project' => $project
                ]
            );
        }

        /** @var ApiHelper */
        $api = $this->get("app.api_helper");

        // Set default project so we can populate the namespace selector.
        if (!$project) {
            $project = $this->container->getParameter('default_project');
        }

        $projectData = ProjectRepository::getProject($project, $this->container);

        // Default values for the variables to keep the template happy
        $namespaces = null;

        // If the project exists, actually populate the values
        if ($projectData->exists()) {
            $namespaces = $projectData->getNamespaces();
        }

        return $this->render('autoEdits/index.html.twig', [
            'xtPageTitle' => 'tool-autoedits',
            'xtSubtitle' => 'tool-autoedits-desc',
            'xtPage' => 'autoedits',
            'project' => $project,
            'namespaces' => $namespaces,
        ]);
    }

    /**
     * @Route("/autoedits/{project}/{username}/{start}/{end}", name="autoeditsResult")
     */
    public function resultAction($project, $username, $start = null, $end = null)
    {
        // Pull the labs helper and check if enabled
        $lh = $this->get("app.labs_helper");
        $lh->checkEnabled("autoedits");

        // Pull information about the project
        $projectData = ProjectRepository::getProject($project, $this->container);

        if (!$projectData->exists()) {
            $this->addFlash("notice", ["invalid-project", $project]);
            return $this->redirectToRoute("autoedits");
        }

        $dbName = $projectData->getDatabaseName();
        $projectUrl = $projectData->getUrl();

        // Grab our database connection
        $dbh = $this->get('doctrine')->getManager("replicas")->getConnection();

        // Variable parsing.
        // Username needs to be uppercase first (yay Mediawiki),
        // and we also need to handle undefined dates.
        $username = ucfirst($username);

        $invalidDates = (
            (isset($start) && strtotime($start) === false) ||
            (isset($end) && strtotime($end) === false)
        );

        // Validating the dates. If the dates are invalid, we'll redirect
        // to the project and username view.
        if ($invalidDates) {
            // Make sure to add the flash notice first.
            $this->addFlash("notice", ["invalid-date"]);

            // Then redirect us!
            return $this->redirectToRoute(
                "autoeditsResult",
                [
                    "project" => $project,
                    "username" => $username,
                ]
            );
        }

        // Now, load the semi-automated edit types.
        $AEBTypes = $this->getParameter("automated_tools");

        // Create a collection of queries that we're going to run.
        $queries = [];

        $revisionTable = $lh->getTable("revision", $dbName);
        $archiveTable = $lh->getTable("archive", $dbName);

        $cond_begin = $start ? " AND rev_timestamp > :start " : null;
        $cond_end = $end ? " AND rev_timestamp < :end ": null;

        foreach ($AEBTypes as $toolname => $check) {
            $toolname = $dbh->quote($toolname, \PDO::PARAM_STR);
            $check = $dbh->quote($check, \PDO::PARAM_STR);

            $queries[] .= "
                SELECT $toolname AS toolname, COUNT(*) AS count
                FROM $revisionTable
                WHERE rev_user_text = :username
                AND rev_comment REGEXP $check
                $cond_begin
                $cond_end
            ";
        }

        // Query to get combined (semi)automated using for all edits
        // (some automated edits overlap)
        $allAETools = $dbh->quote(implode('|', $AEBTypes), \PDO::PARAM_STR);
        $queries[] = "
            SELECT 'total_live' AS toolname, COUNT(*) AS count
            FROM $revisionTable
            WHERE rev_user_text = :username
            AND rev_comment REGEXP $allAETools
            $cond_begin
            $cond_end
        ";

        // Next, add two simple queries for the live and deleted edits.
        $queries[] = "
            SELECT 'live' AS toolname, COUNT(*) AS count
            FROM $revisionTable
            WHERE rev_user_text = :username
            $cond_begin
            $cond_end
        ";

        $cond_begin = str_replace("rev_timestamp", "ar_timestamp", $cond_begin);
        $cond_end = str_replace("rev_timestamp", "ar_timestamp", $cond_end);

        $queries[] = "
            SELECT 'deleted' AS toolname, COUNT(*) AS count
            FROM $archiveTable
            WHERE ar_user_text = :username
            $cond_begin
            $cond_end
        ";

        // Create a big query and execute.
        $stmt = implode(" UNION ", $queries);

        $sth = $dbh->prepare($stmt);

        $sth->bindParam("username", $username);
        $sth->bindParam("start", $start);
        $sth->bindParam("end", $end);

        $sth->execute();

        // handling results
        $results = [];
        $total_semi = 0;
        $total = 0;

        while ($row = $sth->fetch()) {
            // Different variables need to get set if the tool is
            // the live edits or deleted edits.
            // If it is neither and greater than 0,
            // add them to the array we're rendering and to our running total
            if ($row["toolname"] === "live") {
                $total += $row["count"];
            } elseif ($row["toolname"] === "deleted") {
                $total += $row["count"];
            } elseif ($row["toolname"] === "total_live") {
                $total_semi = $row["count"];
            } elseif ($row["count"] > 0) {
                $results[$row["toolname"]] = $row["count"];
            }
        }

        // Inform user if no revisions found.
        if ($total === 0) {
            $this->addFlash('notice', ['no-contribs']);
            return $this->redirectToRoute('autoedits');
        }

        // Sort the array and do some simple math.
        arsort($results);

        if ($total != 0) {
            $total_pct = ($total_semi / $total) * 100;
        } else {
            $total_pct = 0;
        }

        $ret = [
            'xtPage' => "autoedits",
            'xtTitle' => $username,
            'username' => $username,
            'projectUrl' => $projectUrl,
            'project' => $project,
            'semi_automated' => $results,
            'total_semi' => $total_semi,
            'total' => $total,
            'total_pct' => $total_pct,
        ];

        if (isset($start)) {
            $ret['start'] = $start;
        }
        if (isset($end)) {
            $ret['end'] = $end;
        }

        // Render the view with all variables set.
        return $this->render('autoEdits/result.html.twig', $ret);
    }
}
