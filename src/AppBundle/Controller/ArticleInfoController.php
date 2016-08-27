<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Helper\Apihelper;

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
        $api = $this->get("app.api_helper");
        $api->test();

        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $project = $request->query->get('project');
        $article = $request->query->get('article');

        if ($project != "" && $article != "") {
            return $this->redirectToRoute("ArticleInfoResult", array('project'=>$project, 'article' => $article));
        }
        else if ($article != "") {
            return $this->redirectToRoute("ArticleInfoProject", array('project'=>$project));
        }

        // replace this example code with whatever you need
        return $this->render('articleInfo/index.html.twig', [
            'page' => "articleinfo",
            'title' => "tool_articleinfo",
            "pageTitle" => "tool_articleinfo",
        ]);
    }

    /**
     * @Route("/articleinfo/{project}/{article}", name="ArticleInfoResult")
     */
    public function articleInfoProjectAction($project, $article)
    {
        // replace this example code with whatever you need
        return $this->render('articleInfo/result.html.twig', array(
            'page' => "articleinfo",
            "title" => "tool_articleinfo",
            "pageTitle" => "tool_articleinfo",
            "project" => $project,
            "article_title" => $article,
        ));
    }
}
