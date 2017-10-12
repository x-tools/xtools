<?php
/**
 * This file contains only the ArticleInfoController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Xtools\ProjectRepository;
use Xtools\Page;
use Xtools\PagesRepository;
use Xtools\ArticleInfo;

/**
 * This controller serves the search form and results for the ArticleInfo tool
 */
class ArticleInfoController extends Controller
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
        $projectQuery = $request->query->get('project');
        $article = $request->query->get('article');

        if ($projectQuery != '' && $article != '') {
            return $this->redirectToRoute('ArticleInfoResult', [ 'project' => $projectQuery, 'article' => $article ]);
        } elseif ($article != '') {
            return $this->redirectToRoute('ArticleInfoProject', [ 'project' => $projectQuery ]);
        }

        if ($projectQuery == '') {
            $projectQuery = $this->container->getParameter('default_project');
        }

        $project = ProjectRepository::getProject($projectQuery, $this->container);

        return $this->render('articleInfo/index.html.twig', [
            'xtPage' => 'articleinfo',
            'xtPageTitle' => 'tool-articleinfo',
            'xtSubtitle' => 'tool-articleinfo-desc',
            'project' => $project,
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
     * Display the results.
     * @Route("/articleinfo/{project}/{article}", name="ArticleInfoResult", requirements={"article"=".+"})
     * @param Request $request The HTTP request.
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request)
    {
        $projectQuery = $request->attributes->get('project');
        $project = ProjectRepository::getProject($projectQuery, $this->container);
        $this->projectRepo = $project->getRepository();
        if (!$project->exists()) {
            $this->addFlash('notice', ['invalid-project', $projectQuery]);
            return $this->redirectToRoute('articleInfo');
        }
        $this->dbName = $project->getDatabaseName();

        $pageQuery = $request->attributes->get('article');
        $page = new Page($project, $pageQuery);
        $pageRepo = new PagesRepository();
        $pageRepo->setContainer($this->container);
        $page->setRepository($pageRepo);

        if (!$page->exists()) {
            $this->addFlash('notice', ['no-exist', str_replace('_', ' ', $pageQuery)]);
            return $this->redirectToRoute('articleInfo');
        }

        $articleInfo = new ArticleInfo($page, $this->container);
        $articleInfo->prepareData();

        $numRevisions = $articleInfo->getNumRevisions();
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
}
