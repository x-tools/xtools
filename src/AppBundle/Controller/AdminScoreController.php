<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use DateTime;

class AdminScoreController extends Controller
{
    /**
     * @Route("/adminscore", name="adminscore")
     * @Route("/adminscore", name="AdminScore")
     * @Route("/adminscore/", name="AdminScoreSlash")
     * @Route("/adminscore/index.php", name="AdminScoreIndexPhp")
     * @Route("/scottywong tools/adminscore.php", name="AdminScoreLegacy")
     * @Route("/adminscore/{project}", name="AdminScoreProject")
     */
    public function indexAction(Request $request, $project = null)
    {
        $lh = $this->get("app.labs_helper");
        $lh->checkEnabled("adminscore");

        $projectQuery = $request->query->get('project');
        $username = $request->query->get('username');

        if ($projectQuery != "" && $username != "") {
            return $this->redirectToRoute("AdminScoreResult", [ 'project'=>$projectQuery, 'username' => $username ]);
        } elseif ($projectQuery != "") {
            return $this->redirectToRoute("AdminScoreProject", [ 'project'=>$projectQuery ]);
        }

        // Otherwise fall through.
        return $this->render('adminscore/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            'xtPage' => 'adminscore',
            'xtPageTitle' => 'tool-adminscore',
            'xtSubtitle' => 'tool-adminscore-desc',
            "project" => $project,
        ]);
    }

    /**
     * @Route("/adminscore/{project}/{username}", name="AdminScoreResult")
     */
    public function resultAction($project, $username)
    {

        $lh = $this->get("app.labs_helper");

        $lh->checkEnabled("adminscore");

        $username = ucfirst($username);

        $dbValues = $lh->databasePrepare($project, "AdminScore");

        $dbName = $dbValues["dbName"];
        $wikiName = $dbValues["wikiName"];
        $url = $dbValues["url"];

        $userTable = $lh->getTable("user", $dbName);
        $pageTable = $lh->getTable("page", $dbName);
        $loggingTable = $lh->getTable("logging", $dbName, "userindex");
        $revisionTable = $lh->getTable("revision", $dbName);
        $archiveTable = $lh->getTable("archive", $dbName);

        // MULTIPLIERS (to review)
        $ACCT_AGE_MULT = 1.25;   # 0 if = 365 jours
        $EDIT_COUNT_MULT = 1.25;     # 0 if = 10 000
        $USER_PAGE_MULT = 0.1;     # 0 if =
        $PATROLS_MULT = 1; # 0 if =
        $BLOCKS_MULT = 1.4;     # 0 if = 10
        $AFD_MULT = 1.15;
        $RECENT_ACTIVITY_MULT = 0.9;     # 0 if =
        $AIV_MULT = 1.15;
        $EDIT_SUMMARIES_MULT = 0.8;   # 0 if =
        $NAMESPACES_MULT = 1.0;     # 0 if =
        $PAGES_CREATED_LIVE_MULT = 1.4; # 0 if =
        $PAGES_CREATED_ARCHIVE_MULT = 1.4; # 0 if =
        $RPP_MULT = 1.15;     # 0 if =
        $USERRIGHTS_MULT = 0.75;   # 0 if =

        // Grab the connection to the replica database (which is separate from the above)
        $conn = $this->get('doctrine')->getManager("replicas")->getConnection();

        // Prepare the query and execute
        $resultQuery = $conn->prepare("
        SELECT 'id' AS source, user_id AS value FROM $userTable
            WHERE user_name = :username
        UNION
        SELECT 'account-age' AS source, user_registration AS value FROM $userTable
            WHERE user_name=:username
        UNION
        SELECT 'edit-count' AS source, user_editcount AS value FROM $userTable
            WHERE user_name=:username
        UNION
        SELECT 'user-page' AS source, page_len AS value FROM $pageTable
            WHERE page_namespace=2 AND page_title=:username
        UNION
        SELECT 'patrols' AS source, COUNT(*) AS value FROM $loggingTable
            WHERE log_type='patrol'
                AND log_action='patrol'
                AND log_namespace=0
                AND log_deleted=0 AND log_user_text=:username
        UNION
        SELECT 'blocks' AS source, COUNT(*) AS value FROM $loggingTable l
            INNER JOIN $userTable u ON l.log_user = u.user_id
            WHERE l.log_type='block' AND l.log_action='block'
            AND l.log_namespace=2 AND l.log_deleted=0 AND u.user_name=:username
        UNION
        SELECT 'afd' AS source, COUNT(*) AS value FROM $revisionTable
            WHERE rev_page LIKE 'Articles for deletion/%'
                AND rev_page NOT LIKE 'Articles_for_deletion/Log/%'
                AND rev_user_text=:username
        UNION
        SELECT 'recent-activity' AS source, COUNT(*) AS value FROM $revisionTable
            WHERE rev_user_text=:username AND rev_timestamp > (now()-INTERVAL 730 day) AND rev_timestamp < now()
        UNION
        SELECT 'aiv' AS source, COUNT(*) AS value FROM $revisionTable
            WHERE rev_page LIKE 'Administrator intervention against vandalism%' AND rev_user_text=:username
        UNION
        SELECT 'edit-summaries' AS source, COUNT(*) AS value FROM $revisionTable JOIN page ON rev_page=page_id
            WHERE page_namespace=0 AND rev_user_text=:username
        UNION
        SELECT 'namespaces' AS source, count(*) AS value FROM $revisionTable JOIN page ON rev_page=page_id
            WHERE rev_user_text=:username AND page_namespace=0
        UNION
        SELECT 'pages-created-live' AS source, COUNT(*) AS value FROM $revisionTable
            WHERE rev_user_text=:username AND rev_parent_id=0
        UNION
        SELECT 'pages-created-deleted' AS source, COUNT(*) AS value FROM $archiveTable
            WHERE ar_user_text=:username AND ar_parent_id=0
        UNION
        SELECT 'rpp' AS source, COUNT(*) AS value FROM $revisionTable
            WHERE rev_page LIKE 'Requests_for_page_protection%' AND rev_user_text=:username
        ");

        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();

        // Fetch the result data
        $results = $resultQuery->fetchAll();

        $master = [];
        $total = 0;

        $id = 0;

        foreach ($results as $row) {
            $key = $row["source"];
            $value = $row["value"];

            if ($key == "acct_age") {
                $now = new DateTime();
                $date = new DateTime($value);
                $diff = $date->diff($now);
                $formula = 365*$diff->format("%y")+30*$diff->format("%m")+$diff->format("%d");
                $value = $formula-365;
            }

            if ($key == "id") {
                $id = $value;
            } else {
                $multiplierKey = strtoupper($row["source"] . "_MULT");
                $multiplier = ( isset($$multiplierKey) ? $$multiplierKey : 1 );
                $score = max(min($value * $multiplier, 100), -100);
                $master[$key]["mult"] = $multiplier;
                $master[$key]["value"] = $value;
                $master[$key]["score"] = $score;
                $total += $score;
            }
        }

        if ($id == 0) {
            $this->addFlash("notice", [ "no-result", $username ]);
            return $this->redirectToRoute("AdminScore", [ "project"=>$project ]);
        }

        return $this->render('adminscore/result.html.twig', [
            'xtPage' => 'adminscore',
            'xtTitle' => $username,
            'projectUrl' => $url,
            'username' => $username,
            'project' => $wikiName,
            'master' => $master,
            'total' => $total,
        ]);
    }
}
