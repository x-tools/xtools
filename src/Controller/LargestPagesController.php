<?php

declare(strict_types = 1);

namespace App\Controller;

use App\Model\LargestPages;
use App\Repository\LargestPagesRepository;
use OpenApi\Annotations as OA;
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
     * Get the largest pages on a project.
     * @Route(
     *     "/api/project/largest_pages/{project}/{namespace}",
     *     name="ProjectApiLargestPages",
     *     defaults={
     *         "namespace"="all"
     *     },
     *     methods={"GET"}
     * )
     * @OA\Tag(name="Project API")
     * @OA\Parameter(ref="#/components/parameters/Project")
     * @OA\Parameter(ref="#/components/parameters/Namespace")
     * @OA\Parameter(name="include_pattern", in="query", description="Include only titles that match this pattern.
            Either a regular expression (starts/ends with a forward slash),
            or a wildcard pattern with `%` as the wildcard symbol."
     * )
     * @OA\Parameter(name="exclude_pattern", in="query", description="Exclude titles that match this pattern.
            Either a regular expression (starts/ends with a forward slash),
            or a wildcard pattern with `%` as the wildcard symbol."
     * )
     * @OA\Response(
     *     response=200,
     *     description="List of largest pages for the project.",
     *     @OA\JsonContent(
     *         @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
     *         @OA\Property(property="namespace", ref="#/components/parameters/Namespace/schema"),
     *         @OA\Property(property="include_pattern", example="/Foo|Bar/"),
     *         @OA\Property(property="exclude_pattern", example="%baz"),
     *         @OA\Property(property="pages", type="array", @OA\Items(type="object"), example={{
     *             "rank": 1,
     *             "page_title": "Foo",
     *             "length": 50000
     *         }, {
     *             "rank": 2,
     *             "page_title": "Bar",
     *             "length": 30000
     *         }}),
     *         @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
     *     )
     * )
     * @OA\Response(response=404, ref="#/components/responses/404")
     * @OA\Response(response=503, ref="#/components/responses/503")
     * @OA\Response(response=504, ref="#/components/responses/504")
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
