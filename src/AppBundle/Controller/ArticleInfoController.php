<?php
/**
 * This file contains only the ArticleInfoController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Xtools\ProjectRepository;
use Xtools\ArticleInfo;
use Xtools\Project;
use Xtools\Page;
use DateTime;
use Xtools\ArticleInfoRepository;

/**
 * This controller serves the search form and results for the ArticleInfo tool
 */
class ArticleInfoController extends XtoolsController
{
    /**
     * Get the tool's shortname.
     * @return string
     * @codeCoverageIgnore
     */
    public function getToolShortname()
    {
        return 'articleinfo';
    }

    /**
     * The search form.
     * @Route("/articleinfo", name="articleinfo")
     * @Route("/articleinfo", name="articleInfo")
     * @Route("/articleinfo/", name="articleInfoSlash")
     * @Route("/articleinfo/index.php", name="articleInfoIndexPhp")
     * @Route("/articleinfo/{project}", name="ArticleInfoProject")
     * @param Request $request The HTTP request.
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $params = $this->parseQueryParams($request);

        if (isset($params['project']) && isset($params['article'])) {
            return $this->redirectToRoute('ArticleInfoResult', $params);
        }

        // Convert the given project (or default project) into a Project instance.
        $params['project'] = $this->getProjectFromQuery($params);

        return $this->render('articleInfo/index.html.twig', [
            'xtPage' => 'articleinfo',
            'xtPageTitle' => 'tool-articleinfo',
            'xtSubtitle' => 'tool-articleinfo-desc',
            'project' => $params['project'],
        ]);
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
    public function gadgetAction(Request $request)
    {
        $rendered = $this->renderView('articleInfo/articleinfo.js.twig');

        // SUPER hacky, but it works and is safe.
        if ($request->query->get('uglify') != '') {
            // $ and " need to be escaped.
            $rendered = str_replace('$', '\$', trim($rendered));
            $rendered = str_replace('"', '\"', trim($rendered));

            // Uglify temporary file.
            $tmpFile = sys_get_temp_dir() . '/xtools_articleinfo_gadget.js';
            $script = "echo \"$rendered\" | tee $tmpFile >/dev/null && ";
            $script .= $this->get('kernel')->getRootDir() .
                "/Resources/node_modules/uglify-es/bin/uglifyjs $tmpFile --mangle " .
                "&& rm $tmpFile >/dev/null";
            $process = new Process($script);
            $process->run();

            // Check for errors.
            $errorOutput = $process->getErrorOutput();
            if ($errorOutput != '') {
                $response = new \Symfony\Component\HttpFoundation\Response(
                    "Error generating uglified JS. The server said:\n\n$errorOutput"
                );
                return $response;
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

        $response = new \Symfony\Component\HttpFoundation\Response($rendered);
        $response->headers->set('Content-Type', 'text/javascript');
        return $response;
    }

    /**
     * Display the results in given date range.
     * @Route(
     *    "/articleinfo/{project}/{article}/{start}/{end}", name="ArticleInfoResult",
     *     requirements={
     *         "article"=".+",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     }
     * )
     * @param Request $request
     * @param $article
     * @param null|string $start
     * @param null|string $end
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request, $article, $start = null, $end = null)
    {
        // This is some complicated stuff here. We pass $start and $end to method signature
        // for router regex parser to parse `article` with those parameters and then
        // manually retrieve what we want. It's done this way because programmatical way
        // is much easier (or maybe even only existing) solution for that.

        // Does path have `start` and `end` parameters (even empty ones)?
        if (1 === preg_match('/(.+?)\/(|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?$/', $article, $matches)) {
            $article = $matches[1];
            $start = $matches[2];
            $end = isset($matches[3]) ? $matches[3] : null;
        }

        list($start, $end) = $this->getUTCFromDateParams($start, $end, false);

        // In this case only the project is validated.
        $ret = $this->validateProjectAndUser($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            $project = $ret[0];
        }

        $page = $this->getAndValidatePage($project, $article);
        if ($page instanceof RedirectResponse) {
            return $page;
        }

        if (!$this->isDateRangeValid($page, $start, $end)) {
            $this->addFlash('notice', ['date-range-outside-revisions']);

            return $this->redirectToRoute('ArticleInfoResult', [
                'project' => $request->get('project'),
                'article' => $article
            ]);
        }

        $articleInfoRepo = new ArticleInfoRepository();
        $articleInfoRepo->setContainer($this->container);
        $articleInfo = new ArticleInfo($page, $this->container, $start, $end);
        $articleInfo->setRepository($articleInfoRepo);

        $articleInfo->prepareData();

        $maxRevisions = $this->container->getParameter('app.max_page_revisions');

        // Show message if we hit the max revisions.
        if ($articleInfo->tooManyRevisions()) {
            // FIXME: i18n number_format?
            $this->addFlash('notice', ['too-many-revisions', number_format($maxRevisions), $maxRevisions]);
        }

        $ret = [
            'xtPage' => 'articleinfo',
            'xtTitle' => $page->getTitle(),
            'project' => $project,
            'editorlimit' => $request->query->get('editorlimit', 20),
            'botlimit' => $request->query->get('botlimit', 10),
            'pageviewsOffset' => 60,
            'ai' => $articleInfo,
            'page' => $page,
        ];

        // Output the relevant format template.
        $format = $request->query->get('format', 'html');
        if ($format == '') {
            // The default above doesn't work when the 'format' parameter is blank.
            $format = 'html';
        }
        $response = $this->render("articleInfo/result.$format.twig", $ret);
        if ($format == 'wikitext') {
            $response->headers->set('Content-Type', 'text/plain');
        }

        return $response;
    }

    /**
     * Check if there were any revisions of given page in given date range.
     * @param Page $page
     * @param false|int $start
     * @param false|int $end
     * @return bool
     */
    private function isDateRangeValid(Page $page, $start, $end)
    {
        return $page->getNumRevisions(null, $start, $end) > 0;
    }

    /**
     * Get textshares information about the article.
     * @Route(
     *     "/articleinfo-authorship/{project}/{article}",
     *     name="ArticleInfoAuthorshipResult",
     *     requirements={"article"=".+"}
     * )
     * @param Request $request The HTTP request.
     * @param string $article
     * @return Response
     * @codeCoverageIgnore
     */
    public function textsharesResultAction(Request $request, $article)
    {
        // In this case only the project is validated.
        $ret = $this->validateProjectAndUser($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            $project = $ret[0];
        }

        $page = $this->getAndValidatePage($project, $article);
        if ($page instanceof RedirectResponse) {
            return $page;
        }

        $articleInfoRepo = new ArticleInfoRepository();
        $articleInfoRepo->setContainer($this->container);
        $articleInfo = new ArticleInfo($page, $this->container);
        $articleInfo->setRepository($articleInfoRepo);

        $isSubRequest = $request->get('htmlonly')
            || $this->get('request_stack')->getParentRequest() !== null;

        $limit = $isSubRequest ? 10 : null;

        return $this->render('articleInfo/textshares.html.twig', [
            'xtPage' => 'articleinfo',
            'xtTitle' => $page->getTitle(),
            'project' => $project,
            'page' => $page,
            'textshares' => $articleInfo->getTextshares($limit),
            'is_sub_request' => $isSubRequest,
        ]);
    }

    /************************ API endpoints ************************/

    /**
     * Get basic info on a given article.
     * @Route("/api/articleinfo/{project}/{article}", requirements={"article"=".+"})
     * @Route("/api/page/articleinfo/{project}/{article}", requirements={"article"=".+"})
     * @param Request $request The HTTP request.
     * @param string $project
     * @param string $article
     * @return View
     * See ArticleInfoControllerTest::testArticleInfoApi()
     * @codeCoverageIgnore
     */
    public function articleInfoApiAction(Request $request, $project, $article)
    {
        $projectData = ProjectRepository::getProject($project, $this->container);
        if (!$projectData->exists()) {
            return new JsonResponse(
                ['error' => "$project is not a valid project"],
                Response::HTTP_NOT_FOUND
            );
        }

        $page = $this->getAndValidatePage($projectData, $article);
        if ($page instanceof RedirectResponse) {
            return new JsonResponse(
                ['error' => "$article was not found"],
                Response::HTTP_NOT_FOUND
            );
        }

        $data = $this->getArticleInfoApiData($projectData, $page);

        if ($request->query->get('format') === 'html') {
            return $this->getApiHtmlResponse($projectData, $page, $data);
        }

        $body = array_merge([
            'project' => $projectData->getDomain(),
            'page' => $page->getTitle(),
        ], $data);

        return new JsonResponse(
            $body,
            Response::HTTP_OK
        );
    }

    /**
     * Generate the data structure that will used in the ArticleInfo API response.
     * @param  Project $project
     * @param  Page    $page
     * @return array
     * @codeCoverageIgnore
     */
    private function getArticleInfoApiData(Project $project, Page $page)
    {
        /** @var integer Number of days to query for pageviews */
        $pageviewsOffset = 30;

        $data = [
            'project' => $project->getDomain(),
            'page' => $page->getTitle(),
            'watchers' => (int) $page->getWatchers(),
            'pageviews' => $page->getLastPageviews($pageviewsOffset),
            'pageviews_offset' => $pageviewsOffset,
        ];

        try {
            $info = $page->getBasicEditingInfo();
        } catch (\Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException $e) {
            // No more open database connections.
            $data['error'] = 'Unable to fetch revision data. Please try again later.';
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            /**
             * The query most likely exceeded the maximum query time,
             * so we'll abort and give only info retrived by the API.
             */
            $data['error'] = 'Unable to fetch revision data. The query may have timed out.';
        }

        if ($info != false) {
            $creationDateTime = DateTime::createFromFormat('YmdHis', $info['created_at']);
            $modifiedDateTime = DateTime::createFromFormat('YmdHis', $info['modified_at']);
            $secsSinceLastEdit = (new DateTime)->getTimestamp() - $modifiedDateTime->getTimestamp();

            $data = array_merge($data, [
                'revisions' => (int) $info['num_edits'],
                'editors' => (int) $info['num_editors'],
                'author' => $info['author'],
                'author_editcount' => (int) $info['author_editcount'],
                'created_at' => $creationDateTime->format('Y-m-d'),
                'created_rev_id' => $info['created_rev_id'],
                'modified_at' => $modifiedDateTime->format('Y-m-d H:i'),
                'secs_since_last_edit' => $secsSinceLastEdit,
                'last_edit_id' => (int) $info['modified_rev_id'],
            ]);
        }

        return $data;
    }

    /**
     * Get the Response for the HTML output of the ArticleInfo API action.
     * @param  Project  $project
     * @param  Page     $page
     * @param  string[] $data The pre-fetched data.
     * @return Response
     * @codeCoverageIgnore
     */
    private function getApiHtmlResponse(Project $project, Page $page, $data)
    {
        $response = $this->render('articleInfo/api.html.twig', [
            'data' => $data,
            'project' => $project,
            'page' => $page,
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
     * @Route("/api/page/prose/{project}/{article}", requirements={"article"=".+"})
     * @param Request $request The HTTP request.
     * @param string $article
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function proseStatsApiAction(Request $request, $article)
    {
        $this->recordApiUsage('page/prose');

        // In this case only the project is validated.
        $ret = $this->validateProjectAndUser($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            $project = $ret[0];
        }

        $page = $this->getAndValidatePage($project, $article);
        if ($page instanceof RedirectResponse) {
            return new JsonResponse(
                ['error' => "$article was not found"],
                Response::HTTP_NOT_FOUND
            );
        }

        $articleInfoRepo = new ArticleInfoRepository();
        $articleInfoRepo->setContainer($this->container);
        $articleInfo = new ArticleInfo($page, $this->container);
        $articleInfo->setRepository($articleInfoRepo);

        $ret = array_merge(
            [
                'project' => $project->getDomain(),
                'page' => $page->getTitle(),
            ],
            $articleInfo->getProseStats()
        );

        return new JsonResponse(
            $ret,
            Response::HTTP_OK
        );
    }
}
