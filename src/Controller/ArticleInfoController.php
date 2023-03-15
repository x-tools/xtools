<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\XtoolsHttpException;
use App\Helper\AutomatedEditsHelper;
use App\Model\ArticleInfo;
use App\Model\Authorship;
use App\Model\Page;
use App\Model\Project;
use App\Repository\ArticleInfoRepository;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller serves the search form and results for the ArticleInfo tool
 */
class ArticleInfoController extends XtoolsController
{
    protected ArticleInfo $articleInfo;

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'ArticleInfo';
    }

    /**
     * The search form.
     * @Route("/articleinfo", name="ArticleInfo")
     * @Route("/articleinfo/index.php", name="articleInfoIndexPhp")
     * @Route("/articleinfo/{project}", name="ArticleInfoProject")
     * @return Response
     */
    public function indexAction(): Response
    {
        if (isset($this->params['project']) && isset($this->params['page'])) {
            return $this->redirectToRoute('ArticleInfoResult', $this->params);
        }

        return $this->render('articleInfo/index.html.twig', array_merge([
            'xtPage' => 'ArticleInfo',
            'xtPageTitle' => 'tool-articleinfo',
            'xtSubtitle' => 'tool-articleinfo-desc',

            // Defaults that will get overridden if in $params.
            'start' => '',
            'end' => '',
            'page' => '',
        ], $this->params, ['project' => $this->project]));
    }

    /**
     * Setup the ArticleInfo instance and its Repository.
     * @param ArticleInfoRepository $articleInfoRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     */
    private function setupArticleInfo(
        ArticleInfoRepository $articleInfoRepo,
        AutomatedEditsHelper $autoEditsHelper
    ): void {
        if (isset($this->articleInfo)) {
            return;
        }

        $this->articleInfo = new ArticleInfo(
            $articleInfoRepo,
            $this->i18n,
            $autoEditsHelper,
            $this->page,
            $this->start,
            $this->end
        );
    }

    /**
     * Generate ArticleInfo gadget script for use on-wiki. This automatically points the
     * script to this installation's API.
     *
     * @Route("/articleinfo-gadget.js", name="ArticleInfoGadget")
     * @link https://www.mediawiki.org/wiki/XTools/ArticleInfo_gadget
     *
     * @return Response
     * @codeCoverageIgnore
     */
    public function gadgetAction(): Response
    {
        $rendered = $this->renderView('articleInfo/articleinfo.js.twig');
        $response = new Response($rendered);
        $response->headers->set('Content-Type', 'text/javascript');
        return $response;
    }

    /**
     * Display the results in given date range.
     * @Route(
     *    "/articleinfo/{project}/{page}/{start}/{end}", name="ArticleInfoResult",
     *     requirements={
     *         "page"="(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={
     *         "start"=false,
     *         "end"=false,
     *     }
     * )
     * @param ArticleInfoRepository $articleInfoRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(
        ArticleInfoRepository $articleInfoRepo,
        AutomatedEditsHelper $autoEditsHelper
    ): Response {
        if (!$this->isDateRangeValid($this->page, $this->start, $this->end)) {
            $this->addFlashMessage('notice', 'date-range-outside-revisions');

            return $this->redirectToRoute('ArticleInfo', [
                'project' => $this->request->get('project'),
            ]);
        }

        $this->setupArticleInfo($articleInfoRepo, $autoEditsHelper);
        $this->articleInfo->prepareData();

        $maxRevisions = $this->getParameter('app.max_page_revisions');

        // Show message if we hit the max revisions.
        if ($this->articleInfo->tooManyRevisions()) {
            $this->addFlashMessage('notice', 'too-many-revisions', [
                $this->i18n->numberFormat($maxRevisions),
                $maxRevisions,
            ]);
        }

        // For when there is very old data (2001 era) which may cause miscalculations.
        if ($this->articleInfo->getFirstEdit()->getYear() < 2003) {
            $this->addFlashMessage('warning', 'old-page-notice');
        }

        // When all username info has been hidden (see T303724).
        if (0 === $this->articleInfo->getNumEditors()) {
            $this->addFlashMessage('warning', 'error-usernames-missing');
        }

        $ret = [
            'xtPage' => 'ArticleInfo',
            'xtTitle' => $this->page->getTitle(),
            'project' => $this->project,
            'editorlimit' => (int)$this->request->query->get('editorlimit', 20),
            'botlimit' => $this->request->query->get('botlimit', 10),
            'pageviewsOffset' => 60,
            'ai' => $this->articleInfo,
            'showAuthorship' => Authorship::isSupportedPage($this->page) && $this->articleInfo->getNumEditors() > 0,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse('articleInfo/result', $ret);
    }

    /**
     * Check if there were any revisions of given page in given date range.
     * @param Page $page
     * @param false|int $start
     * @param false|int $end
     * @return bool
     */
    private function isDateRangeValid(Page $page, $start, $end): bool
    {
        return $page->getNumRevisions(null, $start, $end) > 0;
    }

    /************************ API endpoints ************************/

    /**
     * Get basic info on a given article.
     * @Route(
     *     "/api/articleinfo/{project}/{page}",
     *     name="ArticleInfoApiAction",
     *     requirements={"page"=".+"}
     * )
     * @Route("/api/page/articleinfo/{project}/{page}", requirements={"page"=".+"})
     * @param ArticleInfoRepository $articleInfoRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     * @return Response|JsonResponse
     * See ArticleInfoControllerTest::testArticleInfoApi()
     * @codeCoverageIgnore
     */
    public function articleInfoApiAction(
        ArticleInfoRepository $articleInfoRepo,
        AutomatedEditsHelper $autoEditsHelper
    ): Response {
        $this->recordApiUsage('page/articleinfo');

        $this->setupArticleInfo($articleInfoRepo, $autoEditsHelper);
        $data = [];

        try {
            $data = $this->articleInfo->getArticleInfoApiData($this->project, $this->page);
        } catch (ServerException $e) {
            // The Wikimedia action API can fail for any number of reasons. To our users
            // any ServerException means the data could not be fetched, so we capture it here
            // to avoid the flood of automated emails when the API goes down, etc.
            $data['error'] = $this->i18n->msg('api-error', [$this->project->getDomain()]);
        }

        if ('html' === $this->request->query->get('format')) {
            return $this->getApiHtmlResponse($this->project, $this->page, $data);
        }

        return $this->getFormattedApiResponse($data);
    }

    /**
     * Get the Response for the HTML output of the ArticleInfo API action.
     * @param Project $project
     * @param Page $page
     * @param string[] $data The pre-fetched data.
     * @return Response
     * @codeCoverageIgnore
     */
    private function getApiHtmlResponse(Project $project, Page $page, array $data): Response
    {
        $response = $this->render('articleInfo/api.html.twig', [
            'project' => $project,
            'page' => $page,
            'data' => $data,
        ]);

        // All /api routes by default respond with a JSON content type.
        $response->headers->set('Content-Type', 'text/html');

        // This endpoint is hit constantly and user could be browsing the same page over
        // and over (popular noticeboard, for instance), so offload brief caching to browser.
        $response->setClientTtl(350);

        return $response;
    }

    /**
     * Get prose statistics for the given article.
     * @Route(
     *     "/api/page/prose/{project}/{page}",
     *     name="PageApiProse",
     *     requirements={"page"=".+"}
     * )
     * @param ArticleInfoRepository $articleInfoRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function proseStatsApiAction(
        ArticleInfoRepository $articleInfoRepo,
        AutomatedEditsHelper $autoEditsHelper
    ): JsonResponse {
        $this->recordApiUsage('page/prose');
        $this->setupArticleInfo($articleInfoRepo, $autoEditsHelper);
        return $this->getFormattedApiResponse($this->articleInfo->getProseStats());
    }

    /**
     * Get the page assessments of one or more pages, along with various related metadata.
     * @Route(
     *     "/api/page/assessments/{project}/{pages}",
     *     name="PageApiAssessments",
     *     requirements={"pages"=".+"}
     * )
     * @param string $pages May be multiple pages separated by pipes, e.g. Foo|Bar|Baz
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function assessmentsApiAction(string $pages): JsonResponse
    {
        $this->recordApiUsage('page/assessments');

        $pages = explode('|', $pages);
        $out = [];

        foreach ($pages as $pageTitle) {
            try {
                $page = $this->validatePage($pageTitle);
                $assessments = $page->getProject()
                    ->getPageAssessments()
                    ->getAssessments($page);

                $out[$page->getTitle()] = $this->request->get('classonly')
                    ? $assessments['assessment']
                    : $assessments;
            } catch (XtoolsHttpException $e) {
                $out[$pageTitle] = false;
            }
        }

        return $this->getFormattedApiResponse($out);
    }

    /**
     * Get number of in and outgoing links and redirects to the given page.
     * @Route(
     *     "/api/page/links/{project}/{page}",
     *     name="PageApiLinks",
     *     requirements={"page"=".+"}
     * )
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function linksApiAction(): JsonResponse
    {
        $this->recordApiUsage('page/links');
        return $this->getFormattedApiResponse($this->page->countLinksAndRedirects());
    }

    /**
     * Get the top editors to a page.
     * @Route(
     *     "/api/page/top_editors/{project}/{page}/{start}/{end}/{limit}", name="PageApiTopEditors",
     *     requirements={
     *         "page"="(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?(?:\/(\d+))?)?$",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *         "limit"="|\d+"
     *     },
     *     defaults={
     *         "start"=false,
     *         "end"=false,
     *         "limit"=20,
     *     }
     * )
     * @param ArticleInfoRepository $articleInfoRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function topEditorsApiAction(
        ArticleInfoRepository $articleInfoRepo,
        AutomatedEditsHelper $autoEditsHelper
    ): JsonResponse {
        $this->recordApiUsage('page/top_editors');

        $this->setupArticleInfo($articleInfoRepo, $autoEditsHelper);
        $topEditors = $this->articleInfo->getTopEditorsByEditCount(
            (int)$this->limit,
            '' != $this->request->query->get('nobots')
        );

        return $this->getFormattedApiResponse([
            'top_editors' => $topEditors,
        ]);
    }
}
