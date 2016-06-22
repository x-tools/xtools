<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class ArticleInfoController extends Controller
{
    /**
     * @Route("/articleinfo", name="articleInfo")
     * @Route("/articleinfo/", name="articleInfoSlash")
     * @Route("/articleinfo/index.php", name="articleInfoIndexPhp")
     * @Route("/articleinfo/get", name="articleInfoGet")
     */
    public function indexAction()
    {
        // replace this example code with whatever you need
        return $this->render('articleInfo/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            'title' => "tool_articleinfo",
            "pageTitle" => "tool_articleinfo",
        ]);
    }

    /**
     * @Route("/articleinfo/{project}", name="aboutPage")
     */
    public function articleInfoProjectAction()
    {
        // replace this example code with whatever you need
        return $this->render('articleInfo/result.html.twig', array(
            "title" => "About",
            "pageTitle" => "About us",
        ));
    }
}
