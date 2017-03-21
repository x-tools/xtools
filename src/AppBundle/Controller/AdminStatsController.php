<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class AdminStatsController extends Controller
{

    /**
     * @Route("/adminstats", name="adminstats")
     * @Route("/adminstats", name="AdminStats")
     * @Route("/adminstats/", name="AdminStatsSlash")
     * @Route("/adminstats/index.php", name="AdminStatsSlash")
     */
    public function indexAction(Request $request)
    {

        $lh = $this->get("app.labs_helper");

        $lh->checkEnabled("adminstats");

        $projectQuery = $request->query->get('project');
        $startDate = $request->query->get('begin');
        $endDate = $request->query->get("end");

        if ($projectQuery != "" && $startDate != "" && $endDate != "") {
            return $this->redirectToRoute("AdminStatsResult", [
                'project'=>$projectQuery,
                'start' => $startDate,
                'end' => $endDate,
            ]);
        } elseif ($projectQuery != "" && $endDate != "") {
            return $this->redirectToRoute("AdminStatsResult", [
                'project'=>$projectQuery,
                'start' => "1970-01-01",
                'end' => $endDate,
            ]);
        } elseif ($projectQuery != "" && $startDate != "") {
            return $this->redirectToRoute("AdminStatsResult", [
                'project' => $projectQuery,
                'start' => $startDate,
            ]);
        } elseif ($projectQuery != "") {
            return $this->redirectToRoute("AdminStatsResult", [ 'project'=>$projectQuery ]);
        }

        // Otherwise fall through.
        return $this->render('adminStats/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            "xtPage" => "adminstats",
            "xtPageTitle" => "tool_adminstats",
            "xtSubtitle" => "tool_adminstats_desc",
        ]);
    }

    /**
     * @Route("/adminstats/{project}/{start}/{end}", name="AdminStatsResult")
     */
    public function resultAction($project, $start = "1970-01-01", $end = "2099-01-01")
    {

        $lh = $this->get("app.labs_helper");
        $api = $this->get("app.api_helper");

        $lh->checkEnabled("adminstats");

        $dbValues = $lh->databasePrepare($project, "AdminStats");

        //$days = date_diff($end, $start);
        $days = date_diff(new \DateTime($end), new \DateTime($start))->days;

        $conn = $this->get('doctrine')->getManager("replicas")->getConnection();

        $dbName = $dbValues["dbName"];
        $wikiName = $dbValues["wikiName"];
        $url = $dbValues["url"];

        // TODO: Fix this call within this controller
        //$data = $api->getAdmins($project);

        //dump($data);

        // Get admin ID's
        $query = "
    Select ug_user as user_id
    FROM user_groups
    WHERE ug_group = 'sysop'
    UNION
    SELECT ufg_user as user_id
    FROM user_former_groups
    WHERE ufg_group = 'sysop'
    ";

        $res = $conn->prepare( $query );
        $res->execute();

        $adminIdArr = [];

        while ($row = $res->fetch()) {
            $adminIdArr[] = $row["user_id"] ;
        }
        $adminIds = implode(',', $adminIdArr);

        $userTable = $lh->getTable("user", $dbName);
        $loggingTable = $lh->getTable("logging", $dbName);

        $query = "
    SELECT user_name, user_id
    ,SUM(IF( (log_type='delete'  AND log_action != 'restore'),1,0)) as mdelete
    ,SUM(IF( (log_type='delete'  AND log_action  = 'restore'),1,0)) as mrestore
    ,SUM(IF( (log_type='block'   AND log_action != 'unblock'),1,0)) as mblock
    ,SUM(IF( (log_type='block'   AND log_action  = 'unblock'),1,0)) as munblock
    ,SUM(IF( (log_type='protect' AND log_action !='unprotect'),1,0)) as mprotect
    ,SUM(IF( (log_type='protect' AND log_action  ='unprotect'),1,0)) as munprotect
    ,SUM(IF( log_type='rights',1,0)) as mrights
    ,SUM(IF( log_type='import',1,0)) as mimport
    ,SUM(IF(log_type !='',1,0)) as mtotal
    /* TODO: Fix this workaround */
    ,'' as 'group'
    FROM $loggingTable
    JOIN $userTable ON user_id = log_user
    WHERE  log_timestamp > '$start' AND log_timestamp <= '$end'
      AND log_type IS NOT NULL
      AND log_action IS NOT NULL
      AND log_type in ('block', 'delete', 'protect', 'import', 'rights')
      /*AND log_user in ( $adminIds )*/
    GROUP BY user_name
    HAVING mdelete > 0 OR user_id in ( $adminIds )
    ORDER BY mtotal DESC

    ";

        $res = $conn->prepare( $query );
        $res->execute();

        $users = $res->fetchAll();

        $adminsWithoutAction = 0;
        $adminCount = sizeof($adminIdArr);

        foreach ($users as $row) {
            if ($row["mtotal"] == 0) {
                $adminsWithoutAction++;
            }
        }

        $adminsWithoutActionPct = 0;
        if ($adminCount > 0) {
            $adminsWithoutActionPct = $adminsWithoutAction/$adminCount;
        }

        return $this->render("adminStats/result.html.twig", [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            "xtPage" => "adminstats",
            "xtPageTitle" => "tool_adminstats",
            "xtSubtitle" => "tool_adminstats_desc",

            'url' => $url,
            'project' => $project,
            'wikiName' => $wikiName,

            'start_date' => $start,
            'end_date' => $end,
            'days' => $days,

            'adminsWithoutAction' => $adminsWithoutAction,
            'admins_without_action_pct' => $adminsWithoutActionPct,
            'adminCount' => $adminCount,

            'users' => $users,
        ]);
    }
}
