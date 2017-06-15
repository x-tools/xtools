<?php

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

class ApiController extends FOSRestController
{
    /**
     * @Rest\Get("/api/normalizeProject/{project}")
     */
    public function normalizeProject($project)
    {
        $project = ProjectRepository::getProject($project, $this->container);

        if (!$project->exists()) {
            return new View(
                [
                    'error' => "$project is not a valid project",
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        return new View(
            [
                'domain' => $project->getDomain(),
                'url' => $project->getUrl(),
                'api' => $project->getApiUrl(),
            ],
            Response::HTTP_OK
        );
    }

    /**
     * @Rest\Get("/api/namespaces/{project}")
     */
    public function namespaces($project)
    {
        $project = ProjectRepository::getProject($project, $this->container);

        if (!$project->exists()) {
            return new View(
                [
                    'error' => "$project is not a valid project",
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        return new View(
            [
                'api' => $project->getApiUrl(),
                'namespaces' => $project->getNamespaces(),
            ],
            Response::HTTP_OK
        );
    }

    /**
     * @Rest\Get("/api/nonautomated_edits/{project}/{username}/{namespace}/{offset}/{format}")
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
}
