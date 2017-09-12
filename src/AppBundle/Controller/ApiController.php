<?php
/**
 * This file contains only the ApiController class.
 */

namespace AppBundle\Controller;

use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Debug\Exception\FatalErrorException;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use Xtools\ProjectRepository;
use Xtools\UserRepository;
use Xtools\Page;
use Xtools\PagesRepository;
use Xtools\Edit;
use DateTime;

/**
 * Serves the external API of XTools.
 */
class ApiController extends FOSRestController
{
    /**
     * Get domain name, URL, and API URL of the given project.
     * @Rest\Get("/api/project/normalize/{project}")
     * @param string $project Project database name, URL, or domain name.
     * @return View
     */
    public function normalizeProject($project)
    {
        $proj = ProjectRepository::getProject($project, $this->container);

        if (!$proj->exists()) {
            return new View(
                [
                    'error' => "$project is not a valid project",
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        return new View(
            [
                'domain' => $proj->getDomain(),
                'url' => $proj->getUrl(),
                'api' => $proj->getApiUrl(),
                'database' => $proj->getDatabaseName(),
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Get all namespaces of the given project. This endpoint also does the same thing
     * as the normalize_project endpoint, returning other basic info about the project.
     * @Rest\Get("/api/project/namespaces/{project}")
     * @param string $project The project name.
     * @return View
     */
    public function namespaces($project)
    {
        $proj = ProjectRepository::getProject($project, $this->container);

        if (!$proj->exists()) {
            return new View(
                [
                    'error' => "$project is not a valid project",
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        return new View(
            [
                'domain' => $proj->getDomain(),
                'url' => $proj->getUrl(),
                'api' => $proj->getApiUrl(),
                'database' => $proj->getDatabaseName(),
                'namespaces' => $proj->getNamespaces(),
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Count the number of automated edits the given user has made.
     * @Rest\Get(
     *   "/api/user/automated_editcount/{project}/{username}/{namespace}/{start}/{end}/{tools}",
     *   requirements={"start" = "|\d{4}-\d{2}-\d{2}", "end" = "|\d{4}-\d{2}-\d{2}"}
     * )
     * @param Request $request The HTTP request.
     * @param string $project
     * @param string $username
     * @param int|string $namespace ID of the namespace, or 'all' for all namespaces
     * @param string $start In the format YYYY-MM-DD
     * @param string $end In the format YYYY-MM-DD
     * @param string $tools Non-blank to show which tools were used and how many times.
     */
    public function automatedEditCount(
        Request $request,
        $project,
        $username,
        $namespace = 'all',
        $start = '',
        $end = '',
        $tools = ''
    ) {
        $project = ProjectRepository::getProject($project, $this->container);
        $user = UserRepository::getUser($username, $this->container);

        $res = [
            'project' => $project->getDomain(),
            'username' => $user->getUsername(),
        ];

        if ($tools != '') {
            $tools = $user->getAutomatedCounts($project, $namespace, $start, $end);
            $res['automated_editcount'] = 0;
            foreach ($tools as $tool) {
                $res['automated_editcount'] += $tool['count'];
            }
            $res['automated_tools'] = $tools;
        } else {
            $res['automated_editcount'] = $user->countAutomatedEdits($project, $namespace, $start, $end);
        }

        $view = View::create()->setStatusCode(Response::HTTP_OK);
        $view->setData($res);

        return $view->setFormat('json');
    }

    /**
     * Get non-automated edits for the given user.
     * @Rest\Get(
     *   "/api/user/nonautomated_edits/{project}/{username}/{namespace}/{start}/{end}/{offset}",
     *   requirements={"start" = "|\d{4}-\d{2}-\d{2}", "end" = "|\d{4}-\d{2}-\d{2}"}
     * )
     * @param Request $request The HTTP request.
     * @param string $project
     * @param string $username
     * @param int|string $namespace ID of the namespace, or 'all' for all namespaces
     * @param string $start In the format YYYY-MM-DD
     * @param string $end In the format YYYY-MM-DD
     * @param int $offset For pagination, offset results by N edits
     * @return View
     */
    public function nonautomatedEdits(
        Request $request,
        $project,
        $username,
        $namespace,
        $start = '',
        $end = '',
        $offset = 0
    ) {
        $twig = $this->container->get('twig');
        $project = ProjectRepository::getProject($project, $this->container);
        $user = UserRepository::getUser($username, $this->container);
        $data = $user->getNonautomatedEdits($project, $namespace, $start, $end, $offset);

        $view = View::create()->setStatusCode(Response::HTTP_OK);

        if ($request->query->get('format') === 'html') {
            $edits = array_map(function ($attrs) use ($project, $username) {
                $nsName = '';
                if ($attrs['page_namespace']) {
                    $nsName = $project->getNamespaces()[$attrs['page_namespace']];
                }
                $page = $project->getRepository()
                    ->getPage($project, $nsName . ':' . $attrs['page_title']);
                $attrs['id'] = $attrs['rev_id'];
                $attrs['username'] = $username;
                return new Edit($page, $attrs);
            }, $data);

            $twig = $this->container->get('twig');
            $view->setTemplate('api/nonautomated_edits.html.twig');
            $view->setTemplateData([
                'edits' => $edits,
                'project' => $project,
            ]);
            $view->setFormat('html');
        } else {
            $res = [
                'project' => $project->getDomain(),
                'username' => $user->getUsername(),
            ];
            if ($namespace != '' && $namespace !== 'all') {
                $res['namespace'] = $namespace;
            }
            if ($start != '') {
                $res['start'] = $start;
            }
            if ($end != '') {
                $res['end'] = $end;
            }
            $res['offset'] = $offset;
            $res['nonautomated_edits'] = $data;

            $view->setData($res)->setFormat('json');
        }

        return $view;
    }

    /**
     * Get basic info on a given article.
     * @Rest\Get("/api/articleinfo/{project}/{article}", requirements={"article"=".+"})
     * @Rest\Get("/api/page/articleinfo/{project}/{article}", requirements={"article"=".+"})
     * @param Request $request The HTTP request.
     * @param string $project
     * @param string $article
     * @return View
     */
    public function articleInfo(Request $request, $project, $article)
    {
        /** @var integer Number of days to query for pageviews */
        $pageviewsOffset = 30;

        $project = ProjectRepository::getProject($project, $this->container);
        if (!$project->exists()) {
            return new View(
                ['error' => "$project is not a valid project"],
                Response::HTTP_NOT_FOUND
            );
        }

        $page = new Page($project, $article);
        $pageRepo = new PagesRepository();
        $pageRepo->setContainer($this->container);
        $page->setRepository($pageRepo);

        if (!$page->exists()) {
            return new View(
                ['error' => "$article was not found"],
                Response::HTTP_NOT_FOUND
            );
        }

        $info = $page->getBasicEditingInfo();
        $creationDateTime = DateTime::createFromFormat('YmdHis', $info['created_at']);
        $modifiedDateTime = DateTime::createFromFormat('YmdHis', $info['modified_at']);
        $secsSinceLastEdit = (new DateTime)->getTimestamp() - $modifiedDateTime->getTimestamp();

        $data = [
            'revisions' => (int) $info['num_edits'],
            'editors' => (int) $info['num_editors'],
            'author' => $info['author'],
            'author_editcount' => (int) $info['author_editcount'],
            'created_at' => $creationDateTime->format('Y-m-d'),
            'created_rev_id' => $info['created_rev_id'],
            'modified_at' => $modifiedDateTime->format('Y-m-d H:i'),
            'secs_since_last_edit' => $secsSinceLastEdit,
            'last_edit_id' => (int) $info['modified_rev_id'],
            'watchers' => (int) $page->getWatchers(),
            'pageviews' => $page->getLastPageviews($pageviewsOffset),
            'pageviews_offset' => $pageviewsOffset,
        ];

        $view = View::create()->setStatusCode(Response::HTTP_OK);

        if ($request->query->get('format') === 'html') {
            $twig = $this->container->get('twig');
            $view->setTemplate('api/articleinfo.html.twig');
            $view->setTemplateData([
                'data' => $data,
                'project' => $project,
                'page' => $page,
            ]);
            $view->setFormat('html');
        } else {
            $res = array_merge([
                'project' => $project->getDomain(),
                'page' => $page->getTitle(),
            ], $data);
            $view->setData($res)->setFormat('json');
        }

        return $view;
    }

    /**
     * Record usage of a particular XTools tool. This is called automatically
     *   in base.html.twig via JavaScript so that it is done asynchronously
     * @Rest\Put("/api/usage/{tool}/{project}/{token}")
     * @param  string $tool    Internal name of tool
     * @param  string $project Project domain such as en.wikipedia.org
     * @param  string $token   Unique token for this request, so we don't have people
     *                         meddling with these statistics
     * @return View
     */
    public function recordUsage($tool, $project, $token)
    {
        // Validate token
        if (!$this->isCsrfTokenValid('intention', $token)) {
            return new View(
                [],
                Response::HTTP_FORBIDDEN
            );
        }

        // Don't update counts for tools that aren't enabled
        if (!$this->container->getParameter("enable.$tool")) {
            return new View(
                [
                    'error' => 'This tool is disabled'
                ],
                Response::HTTP_FORBIDDEN
            );
        }

        $conn = $this->getDoctrine()->getManager('default')->getConnection();
        $date =  date('Y-m-d');

        // Increment count in timeline
        $existsSql = "SELECT 1 FROM usage_timeline
                      WHERE date = '$date'
                      AND tool = '$tool'";

        if (count($conn->query($existsSql)->fetchAll()) === 0) {
            $createSql = "INSERT INTO usage_timeline
                          VALUES(NULL, '$date', '$tool', 1)";
            $conn->query($createSql);
        } else {
            $updateSql = "UPDATE usage_timeline
                          SET count = count + 1
                          WHERE tool = '$tool'
                          AND date = '$date'";
            $conn->query($updateSql);
        }

        // Update per-project usage, if applicable
        if (!$this->container->getParameter('app.single_wiki')) {
            $existsSql = "SELECT 1 FROM usage_projects
                          WHERE tool = '$tool'
                          AND project = '$project'";

            if (count($conn->query($existsSql)->fetchAll()) === 0) {
                $createSql = "INSERT INTO usage_projects
                              VALUES(NULL, '$tool', '$project', 1)";
                $conn->query($createSql);
            } else {
                $updateSql = "UPDATE usage_projects
                              SET count = count + 1
                              WHERE tool = '$tool'
                              AND project = '$project'";
                $conn->query($updateSql);
            }
        }

        return new View(
            [],
            Response::HTTP_NO_CONTENT
        );
    }
}
