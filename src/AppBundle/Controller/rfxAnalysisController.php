<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class rfxAnalysisController extends Controller
{
    /**
     * @Route("/rfa",                  name="rfxAnalysis")
     * @Route("/rfa",                  name="rfa")
     * @Route("/rfa/index.php",        name="rfxAnalysisIndexPhp")
     * @Route("/rfa/{project}",        name="rfxAnalysisProject")
     * @Route("/rfa/{project}/{type}", name="rfxAnalysisProjectType")
     */
    public function indexAction(Request $request, $project = null, $type = null)
    {
        // Check if enabled
        $lh = $this->get("app.labs_helper");
        $lh->checkEnabled("rfa");

        $projectQuery = $request->get("project");
        $typeQuery = $request->get("type");
        $username = $request->get("username");

        if ($projectQuery != "" && $typeQuery != "" && $username != "")
        {
            return $this->redirectToRoute(
                "rfxAnalysisResult",
                [
                    "project"=>$projectQuery,
                    "type"=>$typeQuery,
                    "username"=>$username
                ]
            );
        }
        else if ($projectQuery != "" && $typeQuery != "")
        {
            return $this->redirectToRoute(
                "rfxAnalysisProjectType",
                [
                    "project"=>$projectQuery,
                    "type"=>$typeQuery
                ]
            );
        }
        // replace this example code with whatever you need
        return $this->render('rfxAnalysis/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            "xtPageTitle" => "rfa",
            'xtPage' => "rfa",
        ]);
    }

    /**
     * @Route("/rfa/{project}/{type}/{username}", name="rfxAnalysisResult")
     */
    public function aboutAction()
    {


        // replace this example code with whatever you need
        return $this->render('default/about.html.twig', array(
            "xtTitle" => "About",
            "xtPageTitle" => "about",
            'xtPage' => "index",
        ));
    }

    /**
     * @Route("/config", name="configPage")
     */
    public function configAction()
    {

        if ($this->container->getParameter('kernel.environment') != "dev") {
            throw new NotFoundHttpException();
        }

        $params = $this->container->getParameterBag()->all();

        foreach ($params as $key => $value) {
            if (strpos($key, "password") !== false) {
                $params[$key] = "<REDACTED>";
            }
        }

        // replace this example code with whatever you need
        return $this->render('default/config.html.twig', [
            "xtTitle" => "Config",
            "xtPageTitle" => "Config",
            'xtPage' => "index",
            'dump' => print_r($params, true),
        ]);
    }
}
