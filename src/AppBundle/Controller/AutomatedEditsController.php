<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        // Pull the values out of the query string.  These values default to
        // empty strings.
        $projectQuery = $request->query->get('project');
        $username = $request->query->get('username');
        $startDate = $request->query->get('begin');
        $endDate = $request->query->get("end");

        // Redirect if the values are set.
        if ($projectQuery != "" && $username != "" && $startDate != "" && $endDate != "") {
            // Redirect ot the route fully
            return $this->redirectToRoute(
                "autoeditsResult",
                [
                    'project'=>$projectQuery,
                    'username'=>$username,
                    'start' => $startDate,
                    'end' => $endDate,
                ]
            );
        } elseif ($projectQuery != "" && $username != "" && $endDate != "") {
            // Redirect if we have the username, enddate and project
            return $this->redirectToRoute(
                "autoeditsResult",
                [
                    'project'=>$projectQuery,
                    'username'=>$username,
                    'end' => $endDate,
                ]
            );
        } elseif ($projectQuery != "" && $username != "" && $startDate != "") {
            // Redirect if we have the username, stardate and project
            return $this->redirectToRoute(
                "autoeditsResult",
                [
                    'project' => $projectQuery,
                    'username'=>$username,
                    'start' => $startDate,
                ]
            );
        } elseif ($projectQuery != "" && $username != "") {
            // Redirect if we have the username and project
            return $this->redirectToRoute(
                "autoeditsResult",
                [
                    'project' => $projectQuery,
                    'username'=>$username,
                ]
            );
        } elseif ($projectQuery != "") {
            // Redirect if we have the project name
            return $this->redirectToRoute(
                "autoeditsResult",
                [
                    'project'=>$projectQuery
                ]
            );
        }

        // set default wiki so we can populate the namespace selector
        if (!$projectQuery) {
            $projectQuery = $this->container->getParameter('default_project');
        }

        /** @var ApiHelper */
        $api = $this->get("app.api_helper");

        return $this->render('autoEdits/index.html.twig', [
            'xtPageTitle' => 'tool_autoedits',
            'xtSubtitle' => 'tool_autoedits_desc',
            'xtPage' => 'autoedits',
            'project' => $projectQuery,
            'namespaces' => $api->namespaces($projectQuery),
        ]);
    }

    /**
     * @Route("/autoedits/{project}/{username}/{begin}/{end}", name="autoeditsResult")
     */
    public function resultAction($project, $username, $begin = null, $end = null)
    {
        // Pull the labs helper and check if enabled
        $lh = $this->get("app.labs_helper");
        $lh->checkEnabled("autoedits");

        // Pull information about the project from the Labs Helper
        $dbValues = $lh->databasePrepare($project, "AutomatedEdits");

        $dbName = $dbValues["dbName"];
        $wikiName = $dbValues["wikiName"];
        $url = $dbValues["url"];

        // Grab our database connection
        $dbh = $this->get('doctrine')->getManager("replicas")->getConnection();

        // Variable parsing.
        // Username needs to be uppercase first (yay Mediawiki),
        // and we also need to handle undefined dates.
        $username = ucfirst($username);

        if ($begin == null) {
            $begin = date("Y-m-d", strtotime("-1 month"));
        }

        if ($end == null) {
            $end = date("Y-m-d");
        }

        // Validating the dates.  If the dates are invalid, we'll redirect
        // to the project and username view.
        if (strtotime($begin) === false || strtotime($end) === false) {
            // Make sure to add the flash notice first.
            $this->addFlash("notice", ["invalid_date"]);

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
        $AEBTypes = [];
        $AEBTypes = $this->getParameter("automated_tools");

        // Create a collection of queries that we're going to run.
        $queries = [];

        $rev = $lh->getTable("revision", $dbName);
        $arc = $lh->getTable("archive", $dbName);
  
        $cond_begin = ( $begin ) ? " AND rev_timestamp > '$begin' " : null;
        $cond_end = ( $end ) ? " AND rev_timestamp < '$end' ": null;

        foreach ($AEBTypes as $toolname => $check) {
            $toolname = $dbh->quote($toolname, \PDO::PARAM_STR);
            $check = $dbh->quote($check, \PDO::PARAM_STR);
        
            $queries[] .= "
                SELECT $toolname as toolname, count(*) as count
                FROM $rev
                WHERE rev_user_text = '$username' 
                AND rev_comment REGEXP $check
                $cond_begin
                $cond_end
            ";
        }

        // Next, add two simple queries for the live and deleted edits.
        $queries[] = "
            SELECT 'live' as toolname ,count(*) as count
            from $rev
            WHERE rev_user_text = '$username'
            $cond_begin
            $cond_end
        ";

        $cond_begin = str_replace("rev_timestamp", "ar_timestamp", $cond_begin);
        $cond_end = str_replace("rev_timestamp", "ar_timestamp", $cond_end);

        $queries[] = "
            SELECT 'deleted' as toolname, count(*) as count
            from $arc
            WHERE ar_user_text = '$username'
            $cond_begin
            $cond_end
        ";

        // Create a big query and execute.
        $stmt = implode(" UNION ", $queries);

        $sth = $dbh->prepare($stmt);

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
            if ($row["toolname"] == "live") {
                $total += $row["count"];
            } elseif ($row["toolname"] == "deleted") {
                $total += $row["count"];
            } elseif ($row["count"] > 0) {
                $results[$row["toolname"]] = $row["count"];
                $total_semi = $total_semi+$row["count"];
            }
        }

        // Sort the array and do some simple math.
        arsort($results);

        if ($total != 0) {
            $total_pct = ($total_semi / $total) * 100;
        } else {
            $total_pct = 0;
        }


        // Render the view with all variables set.
        return $this->render('autoEdits/result.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            //"xtPageTitle" => "autoedits",
            'xtPage' => "autoedits",
            "username" => $username,
            "url" => $url,
            "wikiName" => $wikiName,
            "semi_automated" => $results,
            "begin" => date("Y-m-d", strtotime($begin)),
            "end" => date("Y-m-d", strtotime($end)),
            "total_semi" => $total_semi,
            "total" => $total,
            "total_pct" => $total_pct,

        ]);
    }
}
