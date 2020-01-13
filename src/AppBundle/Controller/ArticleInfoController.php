<?php
/**
 * This file contains only the ArticleInfoController class.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Exception\XtoolsHttpException;
use AppBundle\Helper\I18nHelper;
use AppBundle\Model\ArticleInfo;
use AppBundle\Model\Authorship;
use AppBundle\Model\Page;
use AppBundle\Model\Project;
use AppBundle\Repository\ArticleInfoRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This controller serves the search form and results for the ArticleInfo tool
 */
class ArticleInfoController extends XtoolsController
{
    /** @var ArticleInfo The ArticleInfo class that does all the work. */
    protected $articleInfo;

    /**
     * Get the name of the tool's index route. This is also the name of the associated model.
     * @return string
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
            'project' => $this->project,

            // Defaults that will get overridden if in $params.
            'start' => '',
            'end' => '',
            'page' => '',
        ], $this->params, ['project' => $this->project]));
    }

    /**
     * Setup the ArticleInfo instance and its Repository.
     */
    private function setupArticleInfo(): void
    {
        if (isset($this->articleInfo)) {
            return;
        }

        $articleInfoRepo = new ArticleInfoRepository();
        $articleInfoRepo->setContainer($this->container);
        $this->articleInfo = new ArticleInfo($this->page, $this->container, $this->start, $this->end);
        $this->articleInfo->setRepository($articleInfoRepo);
        $this->articleInfo->setI18nHelper($this->container->get('app.i18n_helper'));
    }

    /**
     * Generate ArticleInfo gadget script for use on-wiki. This automatically points the
     * script to this installation's API. Pass ?uglify=1 to uglify the code.
     *
     * @Route("/articleinfo-gadget.js", name="ArticleInfoGadget")
     * @link https://www.mediawiki.org/wiki/XTools#ArticleInfo_gadget
     *
     * @param Request $request The HTTP request
     * @return Response
     * @codeCoverageIgnore
     */
    public function gadgetAction(Request $request): Response
    {
        $rendered = $this->renderView('articleInfo/articleinfo.js.twig');

        // SUPER hacky, but it works and is safe.
        if ('' != $request->query->get('uglify')) {
            // $ and " need to be escaped.
            $rendered = str_replace('$', '\$', trim($rendered));
            $rendered = str_replace('"', '\"', trim($rendered));

            // Uglify temporary file.
            $tmpFile = sys_get_temp_dir() . '/xtools_articleinfo_gadget.js';
            $script = "echo \"$rendered\" | tee $tmpFile >/dev/null && ";
            $script .= $this->get('kernel')->getProjectDir().
                "/node_modules/uglify-es/bin/uglifyjs $tmpFile --mangle " .
                "&& rm $tmpFile >/dev/null";
            $process = new Process([$script]);
            $process->run();

            // Check for errors.
            $errorOutput = $process->getErrorOutput();
            if ('' != $errorOutput) {
                return new Response(
                    "Error generating uglified JS. The server said:\n\n$errorOutput"
                );
            }

            // Remove escaping.
            $rendered = str_replace('\$', '$', trim($process->getOutput()));
            $rendered = str_replace('\"', '"', trim($rendered));

            // Add comment after uglifying since it removes comments.
            $rendered = "/**\n * This code was automatically generated and should not " .
                "be manually edited.\n * For updates, please copy and paste from " .
                $this->generateUrl('ArticleInfoGadget', ['uglify' => 1], UrlGeneratorInterface::ABSOLUTE_URL) .
                "\n * Released under GPL v3 license.\n */\n" . $rendered;
        }

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
     * @param I18nHelper $i18n
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(I18nHelper $i18n): Response
    {
        if (!$this->isDateRangeValid($this->page, $this->start, $this->end)) {
            $this->addFlashMessage('notice', 'date-range-outside-revisions');

            return $this->redirectToRoute('ArticleInfo', [
                'project' => $this->request->get('project'),
            ]);
        }

        $this->setupArticleInfo();
        $this->articleInfo->prepareData();

        $maxRevisions = $this->container->getParameter('app.max_page_revisions');

        // Show message if we hit the max revisions.
        if ($this->articleInfo->tooManyRevisions()) {
            $this->addFlashMessage('notice', 'too-many-revisions', [
                $i18n->numberFormat($maxRevisions),
                $maxRevisions,
            ]);
        }

        // For when there is very old data (2001 era) which may cause miscalculations.
        if ($this->articleInfo->getFirstEdit()->getYear() < 2003) {
            $this->addFlashMessage('warning', 'old-page-notice');
        }

        $ret = [
            'xtPage' => 'ArticleInfo',
            'xtTitle' => $this->page->getTitle(),
            'project' => $this->project,
            'editorlimit' => $this->request->query->get('editorlimit', 20),
            'botlimit' => $this->request->query->get('botlimit', 10),
            'pageviewsOffset' => 60,
            'ai' => $this->articleInfo,
            'showAuthorship' => Authorship::isSupportedPage($this->page),
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
     * @return Response|JsonResponse
     * See ArticleInfoControllerTest::testArticleInfoApi()
     * @codeCoverageIgnore
     */
    public function articleInfoApiAction(): Response
    {
        $this->recordApiUsage('page/articleinfo');

        $this->setupArticleInfo();
        $data = $this->articleInfo->getArticleInfoApiData($this->project, $this->page);

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
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function proseStatsApiAction(): JsonResponse
    {
        $this->recordApiUsage('page/prose');

        $this->setupArticleInfo();
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
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function topEditorsApiAction(): JsonResponse
    {
        $this->recordApiUsage('page/top_editors');

        $this->setupArticleInfo();
        $topEditors = $this->articleInfo->getTopEditorsByEditCount(
            (int)$this->limit,
            '' != $this->request->query->get('nobots')
        );

        return $this->getFormattedApiResponse([
            'top_editors' => $topEditors,
        ]);
    }
}
