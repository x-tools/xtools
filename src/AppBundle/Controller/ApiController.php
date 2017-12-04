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
     * as the /project/normalize endpoint, returning other basic info about the project.
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
            'total_editcount' => $user->countEdits($project, $namespace, $start, $end),
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

        $res['nonautomated_editcount'] = $res['total_editcount'] - $res['automated_editcount'];

        $view = View::create()->setStatusCode(Response::HTTP_OK);
        $view->setData($res);

        return $view->setFormat('json');
    }

    /**
     * Get non-automated edits for the given user.
     * @Rest\Get(
     *   "/api/user/nonautomated_edits/{project}/{username}/{namespace}/{start}/{end}/{offset}",
     *   requirements={
     *       "start" = "|\d{4}-\d{2}-\d{2}",
     *       "end" = "|\d{4}-\d{2}-\d{2}",
     *       "offset" = "\d*"
     *   }
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

        // Reject if they've made too many edits.
        if ($user->hasTooManyEdits($project)) {
            if ($request->query->get('format') !== 'html') {
                return new View(
                    [
                        'error' => 'Unable to show any data. User has made over ' .
                            $user->maxEdits() . ' edits.',
                    ],
                    Response::HTTP_FORBIDDEN
                );
            }

            $edits = [];
        } else {
            $edits = $user->getNonautomatedEdits($project, $namespace, $start, $end, $offset);
        }

        $view = View::create()->setStatusCode(Response::HTTP_OK);

        if ($request->query->get('format') === 'html') {
            if ($edits) {
                $edits = array_map(function ($attrs) use ($project, $username) {
                    $page = $project->getRepository()
                        ->getPage($project, $attrs['full_page_title']);
                    $pageTitles[] = $attrs['full_page_title'];
                    $attrs['id'] = $attrs['rev_id'];
                    $attrs['username'] = $username;
                    return new Edit($page, $attrs);
                }, $edits);
            }

            $twig = $this->container->get('twig');
            $view->setTemplate('api/nonautomated_edits.html.twig');
            $view->setTemplateData([
                'edits' => $edits,
                'project' => $project,
                'maxEdits' => $user->maxEdits(),
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
            $res['nonautomated_edits'] = $edits;

            $view->setData($res)->setFormat('json');
        }

        return $view;
    }
}
