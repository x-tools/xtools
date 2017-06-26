<?php
/**
 * This file contains only the ApiController class.
 */

namespace AppBundle\Controller;

use AppBundle\Helper\AutomatedEditsHelper;
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

/**
 * Serves the external API of XTools.
 */
class ApiController extends FOSRestController
{
    /**
     * Get domain name, URL, and API URL of the given project.
     * @Rest\Get("/api/normalizeProject/{project}")
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
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Get all namespaces of the given project.
     * @Rest\Get("/api/namespaces/{project}")
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
                'api' => $proj->getApiUrl(),
                'namespaces' => $proj->getNamespaces(),
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Get non-automated edits for the given user.
     * @Rest\Get("/api/nonautomated_edits/{project}/{username}/{namespace}/{offset}/{format}")
     * @param string $project
     * @param string $username
     * @param string $namespace
     * @param int $offset
     * @param string $format
     * @return View
     */
    public function nonautomatedEdits($project, $username, $namespace, $offset = 0, $format = 'json')
    {
        $twig = $this->container->get('twig');
        $aeh = $this->get('app.automated_edits_helper');
        $data = $aeh->getNonautomatedEdits($project, $username, $namespace, $offset);

        if ($format === 'html') {
            $data = $twig->render('api/automated_edits.html.twig', [
                'edits' => $data,
                'projectUrl' =>  "https://$project",
            ]);
        }

        return new View(
            ['data' => $data],
            Response::HTTP_OK
        );
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
