<?php
/**
 * This file contains the code that powers the AdminStats page of xTools.
 *
 * @category RfXAnalysis
 * @package  AppBundle\Controller
 * @author   Xtools Team <xtools@lists.wikimedia.org>
 * @license  GPL 3.0
 * @link     http://tools.wmflabs.org/xtools/rfa
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xtools\ProjectRepository;
use Xtools\RFA;

/**
 * Class RfXAnalysisController
 *
 * @category RfXAnalysis
 * @package  AppBundle\Controller
 * @author   Xtools Team <xtools@lists.wikimedia.org>
 * @license  GPL 3.0
 * @link     http://tools.wmflabs.org/xtools/rfa
 */
class RfXAnalysisController extends Controller
{

    /**
     * Get the tool's shortname.
     * @return string
     */
    public function getToolShortname()
    {
        return 'rfa';
    }

    /**
     * Renders the index page for the RfX Tool
     *
     * @param \Symfony\Component\HttpFoundation\Request $request Given by Symfony
     * @param string                                    $project Optional project.
     * @param string                                    $type    Optional RfX type
     *
     * @Route("/rfa",                  name="rfxAnalysis")
     * @Route("/rfa",                  name="rfa")
     * @Route("/rfa/index.php",        name="rfxAnalysisIndexPhp")
     * @Route("/rfa/{project}",        name="rfxAnalysisProject")
     * @Route("/rfa/{project}/{type}", name="rfxAnalysisProjectType")
     *
     * @return \Symfony\Component\HttpFoundation\Response|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function indexAction(Request $request, $project = null, $type = null)
    {
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

        $projects = array_keys($this->getParameter("rfa"));

        // replace this example code with whatever you need
        return $this->render(
            'rfxAnalysis/index.html.twig',
            [
                "xtPageTitle" => "tool-rfa",
                'xtPage' => "rfa",
                "projects" => $projects,
            ]
        );
    }

    /**
     * Renders the output page for the RfX Tool
     *
     * @param string $project  Optional project.
     * @param string $type     Type of RfX we are processing.
     * @param string $username Username of the person we're analizing.
     *
     * @Route("/rfa/{project}/{type}/{username}", name="rfxAnalysisResult")
     *
     * @return \Symfony\Component\HttpFoundation\Response|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function resultAction($project, $type, $username)
    {
        $api = $this->get("app.api_helper");

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
