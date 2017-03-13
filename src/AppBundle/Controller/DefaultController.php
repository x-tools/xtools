<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            "xtPageTitle" => "welcome",
            'xtPage' => "index",
        ]);
    }

    /**
     * @Route("/about", name="aboutPage")
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

        foreach($params as $key=>$value) {
            if (strpos($key, "password") !== false) {
                $params[$key] = "<REDACTED>";
            }
        }

        // replace this example code with whatever you need
        return $this->render('default/config.html.twig', array(
            "xtTitle" => "Config",
            "xtPageTitle" => "Config",
            'xtPage' => "index",
            'dump' => print_r($params, true),
        ));
    }
}
