<?php

namespace AppBundle\Controller;

use AppBundle\Helper\ApiHelper;
use AppBundle\Helper\LabsHelper;
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

class ApiController extends FOSRestController
{
    /**
     * @Rest\Get("/api/namespaces/{project}")
     */
    public function namespaces($project)
    {
        $api = $this->get("app.api_helper");

        try {
            $namespaces = $api->namespaces($project);
        } catch (Exception $e) {
            return new View(
                [
                    'error' => $e->getMessage(),
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        return new View(
            $namespaces,
            Response::HTTP_OK
        );
    }

    /**
     * @Rest\Get("/api/nonautomated_edits/{project}/{username}/{namespace}/{offset}")
     */
    public function nonautomatedEdits($project, $username, $namespace, $offset = 0)
    {
        $twig = $this->container->get('twig');
        $aeh = $this->get("app.automated_edits_helper");
        $editData = $aeh->getNonautomatedEdits($project, $username, $namespace, $offset);

        $markup = $twig->render('api/automated_edits.html.twig', [
            'edits' => $editData,
            'projectUrl' =>  "https://$project",
        ]);

        return new View(
            ['markup' => $markup],
            Response::HTTP_OK
        );
    }
}
