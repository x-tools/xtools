<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xtools\ProjectRepository;
use Xtools\RFA;

class RfXAnalysisController extends Controller
{
    /**
     * @Route("/rfa",                  name="rfxAnalysis")
     * @Route("/rfa",                  name="rfa")
     * @Route("/rfa/index.php",        name="rfxAnalysisIndexPhp")
     * @Route("/rfa/{project}",        name="rfxAnalysisProject")
     * @Route("/rfa/{project}/{type}", name="rfxAnalysisProjectType")
     */
    public function indexAction(Request $request, $project = null, $type = null)
    {
        // Check if enabled
        $lh = $this->get("app.labs_helper");
        $lh->checkEnabled("rfa");

        $projectQuery = $request->get("project");
        $typeQuery = $request->get("type");
        $username = $request->get("username");

        if ($projectQuery != "" && $typeQuery != "" && $username != "") {
            return $this->redirectToRoute(
                "rfxAnalysisResult",
                [
                    "project"=>$projectQuery,
                    "type"=>$typeQuery,
                    "username"=>$username
                ]
            );
        } else if ($projectQuery != "" && $typeQuery != "") {
            return $this->redirectToRoute(
                "rfxAnalysisProjectType",
                [
                    "project"=>$projectQuery,
                    "type"=>$typeQuery
                ]
            );
        }
        // replace this example code with whatever you need
        return $this->render(
            'rfxAnalysis/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            "xtPageTitle" => "rfa",
            'xtPage' => "rfa",
            ]
        );
    }

    /**
     * @Route("/rfa/{project}/{type}/{username}", name="rfxAnalysisResult")
     */
    public function resultAction($project, $type, $username)
    {
        $lh = $this->get("app.labs_helper");
        $api = $this->get("app.api_helper");
        $lh->checkEnabled("rfa");

        $projectData = ProjectRepository::getProject($project, $this->container);

        if (!$projectData->exists()) {
            $this->addFlash("notice", ["invalid-project", $project]);
            return $this->redirectToRoute("rfa");
        }

        $db = $projectData->getDatabaseName();
        $wikiUrl = $projectData->getUrl();

        if ($this->getParameter("rfa")[$db] === null) {
            $this->addFlash("notice", ["invalid-project", $project]);
            return $this->redirectToRoute("rfa");
        }

        // Construct the page name
        if (!isset($this->getParameter("rfa")[$db]["pages"][$type])) {
            $pagename = "";
        } else {
            $pagename = $this->getParameter("rfa")[$db]["pages"][$type];
        }

        $pagename .= "/$username";

        $text = $api->getPageText($project, $pagename);

        $rfa = new RFA($text, $this->getParameter("rfa")[$db]["sections"], "User");

        if ($rfa->get_lasterror() != null) {
            $this->addFlash("notice", [$rfa->get_lasterror()]);
            return $this->redirectToRoute("rfa");
        }

        $support = $rfa->get_support();
        $oppose = $rfa->get_oppose();
        $neutral = $rfa->get_neutral();
        $dup = $rfa->get_duplicates();

        $end = $rfa->get_enddate();

        $percent = (sizeof($support) /
            (sizeof($support) + sizeof($oppose) + sizeof($neutral)));

        $percent = $percent * 100;

        $percent = round($percent, 2);

        // replace this example code with whatever you need
        return $this->render(
            'rfxAnalysis/result.html.twig', array(
                "xtTitle" => $username,
                'xtPage' => "rfa",
                'url' => $wikiUrl,
                'username' => $username,
                'type' => $type,
                'project' => $projectData->getDatabaseName(),
                'support' => $support,
                'oppose' => $oppose,
                'neutral' => $neutral,
                'duplicates' => $dup,
                'enddate' => $end,
                'percent' => $percent,
                'project_url' => $projectData->getUrl(),
                'pagename' => $pagename,

            )
        );
    }
}
