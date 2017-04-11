<?php

namespace AppBundle\Controller;

use AppBundle\Helper\ApiHelper;
use AppBundle\Helper\LabsHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Config\Definition\Exception\Exception;
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
}
