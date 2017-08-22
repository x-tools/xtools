<?php
/**
 * This file contains the code that powers the RfX Analysis page of xTools.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xtools\ProjectRepository;
use Xtools\Page;
use Xtools\PagesRepository;
use Xtools\UserRepository;
use Xtools\RFA;

/**
 * This controller handles the RfX Analysis tool.
 */
class RfXAnalysisController extends Controller
{

    /**
     * Get the tool's shortname.
     *
     * @return string
     */
    public function getToolShortname()
    {
        return 'rfa';
    }

    /**
     * Renders the index page for the RfX Tool
     *
     * @param Request $request Given by Symfony
     * @param string  $project Optional project.
     * @param string  $type    Optional RfX type
     *
     * @Route("/rfa",                  name="rfxAnalysis")
     * @Route("/rfa",                  name="rfa")
     * @Route("/rfa/index.php",        name="rfxAnalysisIndexPhp")
     * @Route("/rfa/{project}",        name="rfxAnalysisProject")
     * @Route("/rfa/{project}/{type}", name="rfxAnalysisProjectType")
     *
     * @return Response|RedirectResponse
     */
    public function indexAction(Request $request, $project = null, $type = null)
    {
        if ($request->get('projecttype')
            && (strpos($request->get('projecttype'), '|') !== false)
        ) {
            $projectType = explode('|', $request->get('projecttype'), 2);
            $projectQuery = $projectType[0];
            $typeQuery = $projectType[1];
        } else {
            $projectQuery = $request->get('project');
            $typeQuery = $request->get('type');
        }

        $username = $request->get('username');

        if ($projectQuery != '' && $typeQuery != '' && $username != '') {
            return $this->redirectToRoute(
                'rfxAnalysisResult',
                [
                    'project' => $projectQuery,
                    'type' => $typeQuery,
                    'username' => $username
                ]
            );
        } elseif ($projectQuery != '' && $typeQuery != '') {
            return $this->redirectToRoute(
                'rfxAnalysisProjectType',
                [
                    'project' => $projectQuery,
                    'type' => $typeQuery
                ]
            );
        }

        $rfa = $this->getParameter('rfa');

        $projectFields = [];

        foreach (array_keys($rfa) as $row) {
            $projectFields[$row] = $rfa[$row]['pages'];
        }

        // replace this example code with whatever you need
        return $this->render(
            'rfxAnalysis/index.html.twig',
            [
                'xtPageTitle' => 'tool-rfa',
                'xtSubtitle' => 'tool-rfa-desc',
                'xtPage' => 'rfa',
                'project' => $projectQuery,
                'available' => $projectFields,
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
     * @return Response|RedirectResponse
     */
    public function resultAction($project, $type, $username)
    {
        $projectData = ProjectRepository::getProject($project, $this->container);

        if (!$projectData->exists()) {
            $this->addFlash("notice", ["invalid-project", $project]);
            return $this->redirectToRoute("rfa");
        }

        $db = $projectData->getDatabaseName();
        $domain = $projectData->getDomain();

        if ($this->getParameter("rfa")[$domain] === null) {
            $this->addFlash("notice", ["invalid-project-cant-use", $project]);
            return $this->redirectToRoute("rfa");
        }

        // Construct the page name
        if (!isset($this->getParameter("rfa")[$domain]["pages"][$type])) {
            $pagename = "";
        } else {
            $pagename = $this->getParameter("rfa")[$domain]["pages"][$type];
        }

        $user = UserRepository::getUser($username, $this->container);

        $pagename .= '/'.$user->getUsername();
        $page = new Page($projectData, $pagename);
        $pageRepo = new PagesRepository();
        $pageRepo->setContainer($this->container);
        $page->setRepository($pageRepo);

        $text = $page->getWikitext();

        if (!isset($text)) {
            $this->addFlash('notice', ['no-result', $pagename]);
            return $this->redirectToRoute(
                'rfxAnalysisProject',
                [
                    'project' => $projectData->getDatabaseName()
                ]
            );
        }

        $rfa = new RFA(
            $text,
            $this->getParameter("rfa")[$domain]["sections"],
            "User"
        );
        $support = $rfa->getSection("support");
        $oppose = $rfa->getSection("oppose");
        $neutral = $rfa->getSection("neutral");
        $dup = $rfa->getDuplicates();

        if ((sizeof($support) + sizeof($oppose) + sizeof($neutral)) == 0) {
            $this->addFlash("notice", ["no-result", $pagename]);
            return $this->redirectToRoute(
                "rfxAnalysisProject",
                [
                    "project" => $projectData->getDatabaseName()
                ]
            );
        }

        $end = $rfa->getEndDate();

        // replace this example code with whatever you need
        return $this->render(
            'rfxAnalysis/result.html.twig',
            [
                'xtTitle' => $user->getUsername(),
                'xtPage' => 'rfa',
                'project' => $projectData,
                'user' => $user,
                'page' => $page,
                'type' => $type,
                'support' => $support,
                'oppose' => $oppose,
                'neutral' => $neutral,
                'duplicates' => $dup,
                'enddate' => $end,
            ]
        );
    }
}
