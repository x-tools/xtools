<?php
/**
 * This file contains the code that powers the RfX Analysis page of XTools.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xtools\ProjectRepository;
use Xtools\Page;
use Xtools\PageRepository;
use Xtools\UserRepository;
use Xtools\RFX;

/**
 * This controller handles the RfX Analysis tool.
 */
class RfXAnalysisController extends Controller
{

    /**
     * Get the tool's shortname.
     *
     * @return string
     * @codeCoverageIgnore
     */
    public function getToolShortname()
    {
        return 'rfx';
    }

    /**
     * Renders the index page for the RfX Tool
     *
     * @param Request $request Given by Symfony
     * @param string  $project Optional project.
     * @param string  $type    Optional RfX type
     *
     * @Route("/rfx",                  name="rfxAnalysis")
     * @Route("/rfx",                  name="rfx")
     * @Route("/rfx/",                 name="rfxSlash")
     * @Route("/rfx/index.php",        name="rfxAnalysisIndexPhp")
     * @Route("/rfx/{project}",        name="rfxAnalysisProject")
     * @Route("/rfx/{project}/{type}", name="rfxAnalysisProjectType")
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

        $rfx = $this->getParameter('rfx');

        $projectFields = [];

        foreach (array_keys($rfx) as $row) {
            $projectFields[$row] = $rfx[$row]['pages'];
        }

        // replace this example code with whatever you need
        return $this->render(
            'rfxAnalysis/index.html.twig',
            [
                'xtPageTitle' => 'tool-rfx',
                'xtSubtitle' => 'tool-rfx-desc',
                'xtPage' => 'rfx',
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
     * @Route("/rfx/{project}/{type}/{username}", name="rfxAnalysisResult")
     *
     * @return Response|RedirectResponse
     * @codeCoverageIgnore
     */
    public function resultAction($project, $type, $username)
    {
        $projectData = ProjectRepository::getProject($project, $this->container);

        if (!$projectData->exists()) {
            $this->addFlash('notice', ['invalid-project', $project]);
            return $this->redirectToRoute('rfx');
        }

        $db = $projectData->getDatabaseName();
        $domain = $projectData->getDomain();

        if ($this->getParameter('rfx')[$domain] === null) {
            $this->addFlash('notice', ['invalid-project-cant-use', $project]);
            return $this->redirectToRoute('rfx');
        }

        // Construct the page name
        if (!isset($this->getParameter('rfx')[$domain]['pages'][$type])) {
            $pagename = '';
        } else {
            $pagename = $this->getParameter('rfx')[$domain]['pages'][$type];
        }

        $user = UserRepository::getUser($username, $this->container);

        $pagename .= '/'.$user->getUsername();
        $page = new Page($projectData, $pagename);
        $pageRepo = new PageRepository();
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

        $rfx = new RFX(
            $text,
            $this->getParameter('rfx')[$domain]['sections'],
            'User'
        );
        $support = $rfx->getSection('support');
        $oppose = $rfx->getSection('oppose');
        $neutral = $rfx->getSection('neutral');
        $dup = $rfx->getDuplicates();

        $total = count($support) + count($oppose) + count($neutral);

        if ($total === 0) {
            $this->addFlash('notice', ['no-result', $pagename]);
            return $this->redirectToRoute(
                'rfxAnalysisProject',
                [
                    'project' => $projectData->getDatabaseName(),
                ]
            );
        }

        $end = $rfx->getEndDate();

        // replace this example code with whatever you need
        return $this->render(
            'rfxAnalysis/result.html.twig',
            [
                'xtTitle' => $user->getUsername(),
                'xtPage' => 'rfx',
                'project' => $projectData,
                'user' => $user,
                'page' => $page,
                'type' => $type,
                'support' => $support,
                'oppose' => $oppose,
                'neutral' => $neutral,
                'total' => $total,
                'duplicates' => $dup,
                'enddate' => $end,
            ]
        );
    }
}
