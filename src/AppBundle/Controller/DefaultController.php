<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     * @Route("/index.php", name="homepageIndexPhp")
     */
    public function indexAction()
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            "pageTitle" => "welcome",
            'page' => "index",
        ]);
    }

    /**
     * @Route("/about", name="aboutPage")
     */
    public function aboutAction()
    {
        // replace this example code with whatever you need
        return $this->render('default/about.html.twig', array(
            "title" => "About",
            "pageTitle" => "about",
            'page' => "index",
        ));
    }
}
