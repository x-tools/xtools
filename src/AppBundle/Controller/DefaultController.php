<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction()
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            "pageTitle" => "Welcome to xTools!"
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
            "pageTitle" => "About us",
        ));
    }
}
