<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class TopEditsController extends Controller
{
    /**
     * @Route("/topedits", name="topEdits")
     * @Route("/topedits/", name="topEditsSlash")
     * @Route("/topedits/index.php", name="topEditsIndex")
     * @Route("/topedits/get", name="topEditsGet")
     */
    public function indexAction()
    {
        if (!$this->getParameter("enable.topedits")) {
            throw new NotFoundHttpException("This tool is disabled");
        }
        // replace this example code with whatever you need
        return $this->render('topedits/index.html.twig', [
            "pageTitle" => "tool_topedits",
            "subtitle" => "tool_topedits_desc",
            'page' => "topedits",
        ]);
    }

    /**
     * @Route("/topedits/{project}/{namespace}/{article}/{user}", name="topEditsResults")
     */
    public function resultAction($project, $namespace = 0, $article="Main_page", $user = "Example")
    {
        if (!$this->getParameter("enable.topedits")) {
            throw new NotFoundHttpException("This tool is disabled");
        }

        return $this->render('topedits/result.html.twig', array(
            "pageTitle" => "tool_topedits",
            "subtitle" => "tool_topedits_desc",
            'page' => "topedits",
        ));
    }
}
