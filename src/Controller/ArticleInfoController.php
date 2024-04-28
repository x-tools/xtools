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
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Markup;

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
     * @codeCoverageIgnore
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
        } elseif ($this->articleInfo->numDeletedRevisions()) {
            $link = new Markup(
                $this->renderView('flashes/deleted_data.html.twig', [
                    'numRevs' => $this->articleInfo->numDeletedRevisions(),
                ]),
                'UTF-8'
            );
            $this->addFlashMessage(
                'warning',
                $link,
                [$this->articleInfo->numDeletedRevisions(), $link]
            );
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
     * Get basic information about a page.
     * @Route(
     *     "/api/page/articleinfo/{project}/{page}",
     *     name="PageApiArticleInfo",
     *     requirements={"page"=".+"},
     *     methods={"GET"}
     * )
     * @OA\Get(description="Get basic information about the history of a page.
            See also the [pageviews](https://w.wiki/6o9k) and [edit data](https://w.wiki/6o9m) REST APIs.")
     * @OA\Tag(name="Page API")
     * @OA\ExternalDocumentation(url="https://www.mediawiki.org/wiki/XTools/API/Page#Article_info")
     * @OA\Parameter(ref="#/components/parameters/Project")
     * @OA\Parameter(ref="#/components/parameters/Page")
     * @OA\Parameter(name="format", in="query", @OA\Schema(default="json", type="string", enum={"json","html"}))
     * @OA\Response(
     *     response=200,
     *     description="Basic information about the page.",
     *     @OA\JsonContent(
     *         @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
     *         @OA\Property(property="page", ref="#/components/parameters/Page/schema"),
     *         @OA\Property(property="watchers", type="integer"),
     *         @OA\Property(property="pageviews", type="integer"),
     *         @OA\Property(property="pageviews_offset", type="integer"),
     *         @OA\Property(property="revisions", type="integer"),
     *         @OA\Property(property="editors", type="integer"),
     *         @OA\Property(property="minor_edits", type="integer"),
     *         @OA\Property(property="author", type="string", example="Jimbo Wales"),
     *         @OA\Property(property="author_editcount", type="integer"),
     *         @OA\Property(property="created_at", type="date"),
     *         @OA\Property(property="created_rev_id", type="integer"),
     *         @OA\Property(property="modified_at", type="date"),
     *         @OA\Property(property="secs_since_last_edit", type="integer"),
     *         @OA\Property(property="last_edit_id", type="integer"),
     *         @OA\Property(property="assessment", type="object", example={
     *             "value":"FA",
     *             "color": "#9CBDFF",
     *             "category": "Category:FA-Class articles",
     *             "badge": "https://upload.wikimedia.org/wikipedia/commons/b/bc/Featured_article_star.svg"
     *         }),
     *         @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
     *     ),
     *     @OA\XmlContent(format="text/html")
     * )
     * @OA\Response(response=404, ref="#/components/responses/404")
     * @OA\Response(response=503, ref="#/components/responses/503")
     * @OA\Response(response=504, ref="#/components/responses/504")
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

        $this->addFlash('warning', 'In XTools 3.20, this endpoint will be renamed to /api/page/pageinfo');
        $this->addApiWarningAboutDates(['created_at', 'modified_at']);
        $this->addFlash('warning', 'In XTools 3.20, the author and author_editcount properties will be ' .
            'renamed to creator and creator_editcount, respectively.');
        $this->addFlash('warning', 'In XTools 3.20, the last_edit_id property will be renamed to modified_rev_id');
        $this->addFlash('warning', 'In XTools 3.20, the watchers property will return null instead of 0 ' .
            'if the number of page watchers is unknown.');
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
     *     requirements={"page"=".+"},
     *     methods={"GET"}
     * )
     * @OA\Tag(name="Page API")
     * @OA\ExternalDocumentation(url="https://www.mediawiki.org/wiki/XTools/Page_History#Prose")
     * @OA\Get(description="Get statistics about the [prose](https://en.wiktionary.org/wiki/prose) (characters,
            word count, etc.) and referencing of a page. ([more info](https://w.wiki/6oAF))")
     * @OA\Parameter(ref="#/components/parameters/Project")
     * @OA\Parameter(ref="#/components/parameters/Page", @OA\Schema(example="Metallica"))
     * @OA\Response(
     *     response=200,
     *     description="Prose stats",
     *     @OA\JsonContent(
     *         @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
     *         @OA\Property(property="page", ref="#/components/parameters/Page/schema"),
     *         @OA\Property(property="bytes", type="integer"),
     *         @OA\Property(property="characters", type="integer"),
     *         @OA\Property(property="words", type="integer"),
     *         @OA\Property(property="references", type="integer"),
     *         @OA\Property(property="unique_references", type="integer"),
     *         @OA\Property(property="sections", type="integer"),
     *         @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
     *     )
     * )
     * @OA\Response(response=404, ref="#/components/responses/404")
     * @param ArticleInfoRepository $articleInfoRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function proseStatsApiAction(
        ArticleInfoRepository $articleInfoRepo,
        AutomatedEditsHelper $autoEditsHelper
    ): JsonResponse {
        $responseCode = Response::HTTP_OK;
        $this->recordApiUsage('page/prose');
        $this->setupArticleInfo($articleInfoRepo, $autoEditsHelper);
        $this->addFlash('info', 'The algorithm used by this API has recently changed. ' .
            'See https://www.mediawiki.org/wiki/XTools/Page_History#Prose for details.');
        $ret = $this->articleInfo->getProseStats();
        if (null === $ret) {
            $this->addFlashMessage('error', 'api-error-wikimedia');
            $responseCode = Response::HTTP_BAD_GATEWAY;
            $ret = [];
        }
        return $this->getFormattedApiResponse($ret, $responseCode);
    }

    /**
     * Get the page assessments of one or more pages, along with various related metadata.
     * @Route(
     *     "/api/page/assessments/{project}/{pages}",
     *     name="PageApiAssessments",
     *     requirements={"pages"=".+"},
     *     methods={"GET"}
     * )
     * @OA\Tag(name="Page API")
     * @OA\Get(description="Get [assessment data](https://w.wiki/6oAM) of the given pages, including the overall
       quality classifications, along with a list of the WikiProjects and their classifications and importance levels.")
     * @OA\Parameter(ref="#/components/parameters/Project")
     * @OA\Parameter(ref="#/components/parameters/Pages")
     * @OA\Parameter(name="classonly", in="query", @OA\Schema(type="boolean"),
     *     description="Return only the overall quality assessment instead of for each applicable WikiProject."
     * )
     * @OA\Response(
     *     response=200,
     *     description="Assessmnet data",
     *     @OA\JsonContent(
     *         @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
     *         @OA\Property(property="pages", type="object",
     *             @OA\Property(property="Page title", type="object",
     *                 @OA\Property(property="assessment", ref="#/components/schemas/PageAssessment"),
     *                 @OA\Property(property="wikiprojects", type="object",
     *                     @OA\Property(property="name of WikiProject",
     *                         ref="#/components/schemas/PageAssessmentWikiProject"
     *                     )
     *                 )
     *             )
     *         ),
     *         @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
     *     )
     * )
     * @OA\Response(response=404, ref="#/components/responses/404")
     * @param string $pages May be multiple pages separated by pipes, e.g. Foo|Bar|Baz
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function assessmentsApiAction(string $pages): JsonResponse
    {
        $this->recordApiUsage('page/assessments');

        $pages = explode('|', $pages);
        $out = [
            'pages' => [],
        ];

        foreach ($pages as $pageTitle) {
            try {
                $page = $this->validatePage($pageTitle);
                $assessments = $page->getProject()
                    ->getPageAssessments()
                    ->getAssessments($page);

                $out['pages'][$page->getTitle()] = $this->getBoolVal('classonly')
                    ? $assessments['assessment']
                    : $assessments;
            } catch (XtoolsHttpException $e) {
                $out['pages'][$pageTitle] = false;
            }
        }

        return $this->getFormattedApiResponse($out);
    }

    /**
     * Get number of in and outgoing links, external links, and redirects to the given page.
     * @Route(
     *     "/api/page/links/{project}/{page}",
     *     name="PageApiLinks",
     *     requirements={"page"=".+"},
     *     methods={"GET"}
     * )
     * @OA\Tag(name="Page API")
     * @OA\Parameter(ref="#/components/parameters/Project")
     * @OA\Parameter(ref="#/components/parameters/Page")
     * @OA\Response(
     *     response=200,
     *     description="Counts of in and outgoing links, external links, and redirects.",
     *     @OA\JsonContent(
     *         @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
     *         @OA\Property(property="page", ref="#/components/parameters/Page/schema"),
     *         @OA\Property(property="links_ext_count", type="integer"),
     *         @OA\Property(property="links_out_count", type="integer"),
     *         @OA\Property(property="links_in_count", type="integer"),
     *         @OA\Property(property="redirects_count", type="integer"),
     *         @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
     *     )
     * )
     * @OA\Response(response=404, ref="#/components/responses/404")
     * @OA\Response(response=503, ref="#/components/responses/503")
     * @OA\Response(response=504, ref="#/components/responses/504")
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function linksApiAction(): JsonResponse
    {
        $this->recordApiUsage('page/links');
        return $this->getFormattedApiResponse($this->page->countLinksAndRedirects());
    }

    /**
     * Get the top editors (by number of edits) of a page.
     * @Route(
     *     "/api/page/top_editors/{project}/{page}/{start}/{end}/{limit}", name="PageApiTopEditors",
     *     requirements={
     *         "page"="(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?(?:\/(\d+))?)?$",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *         "limit"="\d+"
     *     },
     *     defaults={
     *         "start"=false,
     *         "end"=false,
     *         "limit"=20,
     *     },
     *     methods={"GET"}
     * )
     * @OA\Tag(name="Page API")
     * @OA\Parameter(ref="#/components/parameters/Project")
     * @OA\Parameter(ref="#/components/parameters/Page")
     * @OA\Parameter(ref="#/components/parameters/Start")
     * @OA\Parameter(ref="#/components/parameters/End")
     * @OA\Parameter(ref="#/components/parameters/Limit")
     * @OA\Parameter(name="nobots", in="query",
     *     description="Exclude bots from the results.", @OA\Schema(type="boolean")
     * )
     * @OA\Response(
     *     response=200,
     *     description="List of the top editors, sorted by how many edits they've made to the page.",
     *     @OA\JsonContent(
     *         @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
     *         @OA\Property(property="page", ref="#/components/parameters/Page/schema"),
     *         @OA\Property(property="start", ref="#/components/parameters/Start/schema"),
     *         @OA\Property(property="end", ref="#/components/parameters/End/schema"),
     *         @OA\Property(property="limit", ref="#/components/parameters/Limit/schema"),
     *         @OA\Property(property="top_editors", type="array", @OA\Items(type="object"), example={
     *             {
     *                 "rank": 1,
     *                 "username": "Jimbo Wales",
     *                 "count": 50,
     *                 "minor": 15,
     *                 "first_edit": {
     *                     "id": 12345,
     *                     "timestamp": 20200101125959
     *                 },
     *                 "last_edit": {
     *                     "id": 54321,
     *                     "timestamp": 20200120125959
     *                 }
     *             }
     *         }),
     *         @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
     *     )
     * )
     * @OA\Response(response=404, ref="#/components/responses/404")
     * @OA\Response(response=503, ref="#/components/responses/503")
     * @OA\Response(response=504, ref="#/components/responses/504")
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
            $this->getBoolVal('nobots')
        );

        $this->addApiWarningAboutDates(['timestamp']);
        return $this->getFormattedApiResponse([
            'top_editors' => $topEditors,
        ]);
    }

    /**
     * Get data about bots that have edited a page.
     * @Route(
     *     "/api/page/bot_data/{project}/{page}/{start}/{end}", name="PageApiBotData",
     *     requirements={
     *         "page"="(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={
     *         "start"=false,
     *         "end"=false,
     *     },
     *     methods={"GET"}
     * )
     * @OA\Tag(name="Page API")
     * @OA\Get(description="List bots that have edited a page, with edit counts and whether the account
           is still in the `bot` user group.")
     * @OA\Parameter(ref="#/components/parameters/Project")
     * @OA\Parameter(ref="#/components/parameters/Page")
     * @OA\Parameter(ref="#/components/parameters/Start")
     * @OA\Parameter(ref="#/components/parameters/End")
     * @OA\Response(
     *     response=200,
     *     description="List of bots",
     *     @OA\JsonContent(
     *         @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
     *         @OA\Property(property="page", ref="#/components/parameters/Page/schema"),
     *         @OA\Property(property="start", ref="#/components/parameters/Start/schema"),
     *         @OA\Property(property="end", ref="#/components/parameters/End/schema"),
     *         @OA\Property(property="bots", type="object",
     *             @OA\Property(property="Page title", type="object",
     *                 @OA\Property(property="count", type="integer", description="Number of edits to the page."),
     *                 @OA\Property(property="current", type="boolean",
     *                     description="Whether the account currently has the bot flag"
     *                 )
     *             )
     *         ),
     *         @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
     *     )
     * )
     * @OA\Response(response=404, ref="#/components/responses/404")
     * @OA\Response(response=503, ref="#/components/responses/503")
     * @OA\Response(response=504, ref="#/components/responses/504")
     * @param ArticleInfoRepository $articleInfoRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function botDataApiAction(
        ArticleInfoRepository $articleInfoRepo,
        AutomatedEditsHelper $autoEditsHelper
    ): JsonResponse {
        $this->recordApiUsage('page/bot_data');

        $this->setupArticleInfo($articleInfoRepo, $autoEditsHelper);
        $bots = $this->articleInfo->getBots();

        return $this->getFormattedApiResponse([
            'bots' => $bots,
        ]);
    }

    /**
     * Get counts of (semi-)automated tools that were used to edit the page.
     * @Route(
     *     "/api/page/automated_edits/{project}/{page}/{start}/{end}", name="PageApiAutoEdits",
     *     requirements={
     *         "page"="(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={
     *         "start"=false,
     *         "end"=false,
     *     },
     *     methods={"GET"}
     * )
     * @OA\Tag(name="Page API")
     * @OA\Get(description="Get counts of the number of times known (semi-)automated tools were used to edit the page.")
     * @OA\Parameter(ref="#/components/parameters/Project")
     * @OA\Parameter(ref="#/components/parameters/Page")
     * @OA\Parameter(ref="#/components/parameters/Start")
     * @OA\Parameter(ref="#/components/parameters/End")
     * @OA\Response(
     *     response=200,
     *     description="List of tools",
     *     @OA\JsonContent(
     *         @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
     *         @OA\Property(property="page", ref="#/components/parameters/Page/schema"),
     *         @OA\Property(property="start", ref="#/components/parameters/Start/schema"),
     *         @OA\Property(property="end", ref="#/components/parameters/End/schema"),
     *         @OA\Property(property="automated_tools", ref="#/components/schemas/AutomatedTools"),
     *         @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
     *     )
     * )
     * @OA\Response(response=404, ref="#/components/responses/404")
     * @OA\Response(response=503, ref="#/components/responses/503")
     * @OA\Response(response=504, ref="#/components/responses/504")
     * @param ArticleInfoRepository $articleInfoRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function getAutoEdits(
        ArticleInfoRepository $articleInfoRepo,
        AutomatedEditsHelper $autoEditsHelper
    ): JsonResponse {
        $this->recordApiUsage('page/auto_edits');

        $this->setupArticleInfo($articleInfoRepo, $autoEditsHelper);
        return $this->getFormattedApiResponse([
            'automated_tools' => $this->articleInfo->getAutoEditsCounts(),
        ]);
    }
}
