<?php

declare(strict_types = 1);

namespace App\Controller;

use App\Model\LargestPages;
use App\Repository\LargestPagesRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller serves the search form and results for the Largest Pages tool.
 */
class LargestPagesController extends XtoolsController
{
    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'LargestPages';
    }

    /**
     * The search form.
     * @Route("/largestpages", name="LargestPages")
     * @return Response
     */
    public function indexAction(): Response
    {
        // Redirect if required params are given.
        if (isset($this->params['project'])) {
            return $this->redirectToRoute('LargestPagesResult', $this->params);
        }

        return $this->render('largestPages/index.html.twig', array_merge([
            'xtPage' => 'LargestPages',
            'xtPageTitle' => 'tool-largestpages',
            'xtSubtitle' => 'tool-largestpages-desc',

            // Defaults that will get overriden if in $this->params.
            'project' => $this->project,
            'namespace' => 'all',
            'include_pattern' => '',
            'exclude_pattern' => '',
        ], $this->params));
    }

    /**
     * Instantiate a LargestPages object.
     * @param LargestPagesRepository $largestPagesRepo
     * @return LargestPages
     * @codeCoverageIgnore
     */
    protected function getLargestPages(LargestPagesRepository $largestPagesRepo): LargestPages
    {
        $this->params['include_pattern'] = $this->request->get('include_pattern', '');
        $this->params['exclude_pattern'] = $this->request->get('exclude_pattern', '');
        $largestPages = new LargestPages(
            $largestPagesRepo,
            $this->project,
            $this->namespace,
            $this->params['include_pattern'],
            $this->params['exclude_pattern']
        );
        $largestPages->setRepository($largestPagesRepo);
        return $largestPages;
    }

    /**
     * Display the largest pages on the requested project.
     * @Route(
     *     "/largestpages/{project}/{namespace}",
     *     name="LargestPagesResult",
     *     defaults={
     *         "namespace"="all"
     *     }
     * )
     * @param LargestPagesRepository $largestPagesRepo
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultsAction(LargestPagesRepository $largestPagesRepo): Response
    {
        $ret = [
            'xtPage' => 'LargestPages',
            'xtTitle' => $this->project->getDomain(),
            'lp' => $this->getLargestPages($largestPagesRepo),
        ];

        return $this->getFormattedResponse('largestPages/result', $ret);
    }

    /************************ API endpoints ************************/

    /**
     * Get the largest pages on the requested project.
     * @Route(
     *     "/api/project/largest_pages/{project}/{namespace}",
     *     name="ProjectApiLargestPages",
     *     defaults={
     *         "namespace"="all"
     *     }
     * )
     * @param LargestPagesRepository $largestPagesRepo
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function resultsApiAction(LargestPagesRepository $largestPagesRepo): JsonResponse
    {
        $this->recordApiUsage('project/largest_pages');
        $lp = $this->getLargestPages($largestPagesRepo);

        $pages = [];
        foreach ($lp->getResults() as $index => $page) {
            $pages[] = [
                'rank' => $index + 1,
                'page_title' => $page->getTitle(true),
                'length' => $page->getLength(),
            ];
        }

        return $this->getFormattedApiResponse([
            'pages' => $pages,
        ]);
    }
}
