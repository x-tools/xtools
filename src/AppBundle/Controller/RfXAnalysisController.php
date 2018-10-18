<?php
/**
 * This file contains the code that powers the RfX Analysis page of XTools.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Model\Page;
use AppBundle\Model\RFX;
use AppBundle\Repository\PageRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller handles the RfX Analysis tool.
 */
class RfXAnalysisController extends XtoolsController
{

    /**
     * Get the name of the tool's index route.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'RfXAnalysis';
    }

    /**
     * Renders the index page for the RfX Tool
     * @Route("/rfx", name="RfXAnalysis")
     * @Route("/rfx/", name="RfXAnalysisSlash")
     * @Route("/rfx/index.php", name="rfxAnalysisIndexPhp")
     * @Route("/rfx/{project}", name="rfxAnalysisProject")
     * @Route("/rfx/{project}/{type}", name="rfxAnalysisProjectType")
     * @return Response|RedirectResponse
     */
    public function indexAction()
    {
        if ($this->request->get('projecttype')
            && false !== strpos($this->request->get('projecttype'), '|')
        ) {
            $projectType = explode('|', $this->request->get('projecttype'), 2);
            $projectQuery = $projectType[0];
            $typeQuery = $projectType[1];
        } else {
            $projectQuery = $this->request->get('project');
            $typeQuery = $this->request->get('type');
        }

        $username = $this->request->get('username');

        if ('' != $projectQuery && '' != $typeQuery && '' != $username) {
            return $this->redirectToRoute(
                'rfxAnalysisResult',
                [
                    'project' => $projectQuery,
                    'type' => $typeQuery,
                    'username' => $username,
                ]
            );
        } elseif ('' != $projectQuery && '' != $typeQuery) {
            return $this->redirectToRoute(
                'rfxAnalysisProjectType',
                [
                    'project' => $projectQuery,
                    'type' => $typeQuery,
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
                'xtPage' => 'RfXAnalysis',
                'project' => $projectQuery,
                'available' => $projectFields,
            ]
        );
    }

    /**
     * Renders the output page for the RfX Tool
     * @Route("/rfx/{project}/{type}/{username}", name="rfxAnalysisResult")
     * @param string $type Type of RfX we are processing.
     * @return Response|RedirectResponse
     * @codeCoverageIgnore
     */
    public function resultAction(string $type)
    {
        $domain = $this->project->getDomain();

        if (null === $this->getParameter('rfx')[$domain]) {
            $this->addFlashMessage('notice', 'invalid-project-cant-use', [$this->project]);
            return $this->redirectToRoute('RfXAnalysis');
        }

        // Construct the page name
        if (!isset($this->getParameter('rfx')[$domain]['pages'][$type])) {
            $pageTitle= '';
        } else {
            $pageTitle= $this->getParameter('rfx')[$domain]['pages'][$type];
        }

        $pageTitle.= '/'.$this->user->getUsername();
        $page = new Page($this->project, $pageTitle);
        $pageRepo = new PageRepository();
        $pageRepo->setContainer($this->container);
        $page->setRepository($pageRepo);

        $text = $page->getWikitext();

        if (!isset($text)) {
            $this->addFlashMessage('notice', 'no-result', [$pageTitle]);
            return $this->redirectToRoute(
                'rfxAnalysisProject',
                [
                    'project' => $this->project->getDatabaseName(),
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

        if (0 === $total) {
            $this->addFlashMessage('notice', 'no-result', [$pageTitle]);
            return $this->redirectToRoute(
                'rfxAnalysisProject',
                [
                    'project' => $this->project->getDatabaseName(),
                ]
            );
        }

        $end = $rfx->getEnd();

        // replace this example code with whatever you need
        return $this->render(
            'rfxAnalysis/result.html.twig',
            [
                'xtTitle' => $this->user->getUsername(),
                'xtPage' => 'RfXAnalysis',
                'project' => $this->project,
                'user' => $this->user,
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
