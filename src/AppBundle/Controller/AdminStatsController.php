<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class AdminStatsController extends Controller
{

    /**
     * @Route("/adminstats", name="AdminStats")
     * @Route("/adminstats/", name="AdminStatsSlash")
     * @Route("/adminstats/index.php", name="AdminStatsSlash")
     */
    public function indexAction()
    {
        $lh = $this->get("app.labs_helper");

        $lh->checkEnabled("adminstats");

        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $projectQuery = $request->query->get('project');
        $startDate = $request->query->get('begin');
        $endDate = $request->query->get("end");


        if ($projectQuery != "" && $startDate != "" && $endDate != "") {
            return $this->redirectToRoute("AdminStatsResult", array('project'=>$projectQuery, 'start' => $startDate, 'end' => $endDate));
        }
        elseif ($projectQuery != "" && $endDate != "") {
            return $this->redirectToRoute("AdminStatsResult", array('project'=>$projectQuery, 'start' => "1970-01-01", 'end' => $endDate));
        }
        elseif ($projectQuery != "" && $startDate != "") {
            return $this->redirectToRoute("AdminStatsResult", array('project'=>$projectQuery, 'start' => $startDate));
        }
        else if ($projectQuery != "") {
            return $this->redirectToRoute("AdminStatsResult", array('project'=>$projectQuery));
        }

        // Otherwise fall through.
        return $this->render('adminStats/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            "page" => "adminstats",
            "pageTitle" => "tool_adminstats",
            "subtitle" => "tool_adminstats_desc",
        ]);
    }

    // TODO: Handle start and end date
    /**
     * @Route("/adminstats/{project}/{start}/{end}", name="AdminStatsResult")
     */
    public function resultAction($project, $start = null, $end = null) {

        $lh = $this->get("app.labs_helper");

        $lh->checkEnabled("adminstats");

        $dbValues = $lh->databasePrepare($project, "AdminStats");

        $dbName = $dbValues["dbName"];
        $wikiName = $dbValues["wikiName"];
        $url = $dbValues["url"];

        $users = ["Matthew" => ["group" => "A", "delete" => "0", "restore" => "0", "block" => "0", "unblock" => "0", "protect" => "0", "unprotect" => "0", "import" => "0", "rights"=>"0", "total"=>"0"],
        "Aexandra" => ["group" => "ABC", "delete" => "0", "restore" => "0", "block" => "0", "unblock" => "0", "protect" => "0", "unprotect" => "0", "import" => "0", "rights"=>"0", "total"=>"0"]];

        dump($users);

        return $this->render("adminStats/result.html.twig", [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            "page" => "adminstats",
            "pageTitle" => "tool_adminstats",
            "subtitle" => "tool_adminstats_desc",

            'url' => $url,
            'project' => $project,
            'wikiName' => $wikiName,

            'users' => $users,
        ]);
    }
}
