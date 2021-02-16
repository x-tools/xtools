<?php
/**
 * This file contains only the PagesController class.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Helper\I18nHelper;
use AppBundle\Model\Pages;
use AppBundle\Model\Project;
use AppBundle\Repository\PagesRepository;
use GuzzleHttp;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller serves the Pages tool.
 */
class PagesController extends XtoolsController
{
    /**
     * Get the name of the tool's index route.
     * This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'Pages';
    }

    /**
     * PagesController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     * @param I18nHelper $i18n
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container, I18nHelper $i18n)
    {
        // Causes the tool to redirect to the index page if the user has too high of an edit count.
        $this->tooHighEditCountAction = $this->getIndexRoute();

        // The countPagesApi action is exempt from the edit count limitation.
        $this->tooHighEditCountActionBlacklist = ['countPagesApi'];

        parent::__construct($requestStack, $container, $i18n);
    }

    /**
     * Display the form.
     * @Route("/pages", name="Pages")
     * @Route("/pages/index.php", name="PagesIndexPhp")
     * @Route("/pages/{project}", name="PagesProject")
     * @return Response
     */
    public function indexAction(): Response
    {
        // Redirect if at minimum project and username are given.
        if (isset($this->params['project']) && isset($this->params['username'])) {
            return $this->redirectToRoute('PagesResult', $this->params);
        }

        // Otherwise fall through.
        return $this->render('pages/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-pages',
            'xtSubtitle' => 'tool-pages-desc',
            'xtPage' => 'Pages',

            // Defaults that will get overridden if in $params.
            'username' => '',
            'namespace' => 0,
            'redirects' => 'noredirects',
            'deleted' => 'all',
            'start' => '',
            'end' => '',
        ], $this->params, ['project' => $this->project]));
    }

    /**
     * Every action in this controller (other than 'index') calls this first.
     * @param string $redirects One of 'noredirects', 'onlyredirects' or 'all' for both.
     * @param string $deleted One of 'live', 'deleted' or 'all' for both.
     * @return Pages
     * @codeCoverageIgnore
     */
    public function setUpPages(string $redirects, string $deleted): Pages
    {
        $pagesRepo = new PagesRepository();
        $pagesRepo->setContainer($this->container);
        $pages = new Pages(
            $this->project,
            $this->user,
            $this->namespace,
            $redirects,
            $deleted,
            $this->start,
            $this->end,
            $this->offset
        );
        $pages->setRepository($pagesRepo);

        return $pages;
    }

    /**
     * Display the results.
     * @Route(
     *     "/pages/{project}/{username}/{namespace}/{redirects}/{deleted}/{start}/{end}/{offset}",
     *     name="PagesResult",
     *     requirements={
     *         "namespace"="|all|\d+",
     *         "redirects"="|[^/]+",
     *         "deleted"="|all|live|deleted",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *         "offset"="|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}",
     *     },
     *     defaults={
     *         "namespace"=0,
     *         "start"=false,
     *         "end"=false,
     *         "offset"=false,
     *     }
     * )
     * @param string $redirects One of 'noredirects', 'onlyredirects' or 'all' for both.
     * @param string $deleted One of 'live', 'deleted' or 'all' for both.
     * @return RedirectResponse|Response
     * @codeCoverageIgnore
     */
    public function resultAction(string $redirects = 'noredirects', string $deleted = 'all')
    {
        // Check for legacy values for 'redirects', and redirect
        // back with correct values if need be. This could be refactored
        // out to XtoolsController, but this is the only tool in the suite
        // that deals with redirects, so we'll keep it confined here.
        $validRedirects = ['', 'noredirects', 'onlyredirects', 'all'];
        if ('none' === $redirects || !in_array($redirects, $validRedirects)) {
            return $this->redirectToRoute('PagesResult', array_merge($this->params, [
                'redirects' => 'noredirects',
                'deleted' => $deleted,
                'offset' => $this->offset,
            ]));
        }

        $pages = $this->setUpPages($redirects, $deleted);
        $pages->prepareData();

        $ret = [
            'xtPage' => 'Pages',
            'xtTitle' => $this->user->getUsername(),
            'summaryColumns' => $this->getSummaryColumns($pages),
            'pages' => $pages,
        ];

        if ('PagePile' === $this->request->query->get('format')) {
            return $this->getPagepileResult($this->project, $pages);
        }

        // Output the relevant format template.
        return $this->getFormattedResponse('pages/result', $ret);
    }

    /**
     * What columns to show in namespace totals table.
     * @param Pages $pages The Pages instance.
     * @return string[]
     * @codeCoverageIgnore
     */
    private function getSummaryColumns(Pages $pages): array
    {
        $summaryColumns = ['namespace'];
        if ('deleted' === $pages->getDeleted()) {
            // Showing only deleted pages shows only the deleted column, as redirects are non-applicable.
            $summaryColumns[] = 'deleted';
        } elseif ('onlyredirects' == $pages->getRedirects()) {
            // Don't show redundant pages column if only getting data on redirects or deleted pages.
            $summaryColumns[] = 'redirects';
        } elseif ('noredirects' == $pages->getRedirects()) {
            // Don't show redundant redirects column if only getting data on non-redirects.
            $summaryColumns[] = 'pages';
        } else {
            // Order is important here.
            $summaryColumns[] = 'pages';
            $summaryColumns[] = 'redirects';
        }

        // Show deleted column only when both deleted and live pages are visible.
        if ('all' === $pages->getDeleted()) {
            $summaryColumns[] = 'deleted';
        }

        $summaryColumns[] = 'total-page-size';
        $summaryColumns[] = 'average-page-size';

        return $summaryColumns;
    }

    /**
     * Create a PagePile for the given pages, and get a Redirect to that PagePile.
     * @param Project $project
     * @param Pages $pages
     * @return RedirectResponse
     * @throws HttpException
     * @see https://tools.wmflabs.org/pagepile/
     * @codeCoverageIgnore
     */
    private function getPagepileResult(Project $project, Pages $pages): RedirectResponse
    {
        $namespaces = $project->getNamespaces();
        $pageTitles = [];

        foreach (array_values($pages->getResults()) as $pagesData) {
            foreach ($pagesData as $page) {
                if (0 === (int)$page['namespace']) {
                    $pageTitles[] = $page['page_title'];
                } else {
                    $pageTitles[] = (
                        $namespaces[$page['namespace']] ?? $this->i18n->msg('unknown')
                    ).':'.$page['page_title'];
                }
            }
        }

        $pileId = $this->createPagePile($project, $pageTitles);

        return new RedirectResponse(
            "https://pagepile.toolforge.org/api.php?id=$pileId&action=get_data&format=html&doit1"
        );
    }

    /**
     * Create a PagePile with the given titles.
     * @param Project $project
     * @param string[] $pageTitles
     * @return int The PagePile ID.
     * @throws HttpException
     * @see https://pagepile.toolforge.org/
     * @codeCoverageIgnore
     */
    private function createPagePile(Project $project, array $pageTitles): int
    {
        /** @var GuzzleHttp\Client $client */
        $client = $this->container->get('eight_points_guzzle.client.xtools');

        $url = 'https://pagepile.toolforge.org/api.php';

        try {
            $res = $client->request('GET', $url, ['query' => [
                'action' => 'create_pile_with_data',
                'wiki' => $project->getDatabaseName(),
                'data' => implode("\n", $pageTitles),
            ]]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            throw new HttpException(
                414,
                'error-pagepile-too-large'
            );
        }

        $ret = json_decode($res->getBody()->getContents(), true);

        if (!isset($ret['status']) || 'OK' !== $ret['status']) {
            throw new HttpException(
                500,
                'Failed to create PagePile. There may be an issue with the PagePile API.'
            );
        }

        return $ret['pile']['id'];
    }

    /************************ API endpoints ************************/

    /**
     * Get a count of the number of pages created by a user,
     * including the number that have been deleted and are redirects.
     * @Route(
     *     "/api/user/pages_count/{project}/{username}/{namespace}/{redirects}/{deleted}/{start}/{end}",
     *     name="UserApiPagesCount",
     *     requirements={
     *         "namespace"="|\d+|all",
     *         "redirects"="|noredirects|onlyredirects|all",
     *         "deleted"="|all|live|deleted",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={
     *         "namespace"=0,
     *         "redirects"="noredirects",
     *         "deleted"="all",
     *         "start"=false,
     *         "end"=false,
     *     }
     * )
     * @param string $redirects One of 'noredirects', 'onlyredirects' or 'all' for both.
     * @param string $deleted One of 'live', 'deleted' or 'all' for both.
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function countPagesApiAction(string $redirects = 'noredirects', string $deleted = 'all'): JsonResponse
    {
        $this->recordApiUsage('user/pages_count');

        $pages = $this->setUpPages($redirects, $deleted);
        $counts = $pages->getCounts();

        if ('all' !== $this->namespace && isset($counts[$this->namespace])) {
            $counts = $counts[$this->namespace];
        }

        return $this->getFormattedApiResponse(['counts' => (object)$counts]);
    }

    /**
     * Get the pages created by by a user.
     * @Route(
     *     "/api/user/pages/{project}/{username}/{namespace}/{redirects}/{deleted}/{start}/{end}/{offset}",
     *     name="UserApiPagesCreated",
     *     requirements={
     *         "namespace"="|\d+|all",
     *         "redirects"="|noredirects|onlyredirects|all",
     *         "deleted"="|all|live|deleted",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *         "offset"="|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}",
     *     },
     *     defaults={
     *         "namespace"=0,
     *         "redirects"="noredirects",
     *         "deleted"="all",
     *         "start"=false,
     *         "end"=false,
     *         "offset"=false,
     *     }
     * )
     * @param string $redirects One of 'noredirects', 'onlyredirects' or 'all' for both.
     * @param string $deleted One of 'live', 'deleted' or blank for both.
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function getPagesApiAction(string $redirects = 'noredirects', string $deleted = 'all'): JsonResponse
    {
        $this->recordApiUsage('user/pages');

        $pages = $this->setUpPages($redirects, $deleted);
        $pagesList = $pages->getResults();

        if ('all' !== $this->namespace && isset($pagesList[$this->namespace])) {
            $pagesList = $pagesList[$this->namespace];
        }

        $ret = [
            'pages' => (object)$pagesList,
        ];

        if ($pages->getNumResults() === $pages->resultsPerPage()) {
            $ret['continue'] = $this->offset + 1;
        }

        return $this->getFormattedApiResponse($ret);
    }
}
