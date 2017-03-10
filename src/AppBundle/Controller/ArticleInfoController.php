<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use AppBundle\Helper\Apihelper;

class ArticleInfoController extends Controller
{
    /**
     * @Route("/articleinfo", name="articleinfo")
     * @Route("/articleinfo", name="articleInfo")
     * @Route("/articleinfo/", name="articleInfoSlash")
     * @Route("/articleinfo/index.php", name="articleInfoIndexPhp")
     * @Route("/articleinfo/{project}", name="ArticleInfoProject")
     */
    public function indexAction($project = null)
    {
        $lh = $this->get("app.labs_helper");

        $lh->checkEnabled("articleinfo");

        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $projectQuery = $request->query->get('project');
        $article = $request->query->get('article');

        if ($projectQuery != "" && $article != "") {
            return $this->redirectToRoute("ArticleInfoResult", array('project'=>$projectQuery, 'article' => $article));
        }
        else if ($article != "") {
            return $this->redirectToRoute("ArticleInfoProject", array('project'=>$projectQuery));
        }

        return $this->render('articleInfo/index.html.twig', [
            'page' => "articleinfo",
            'title' => "tool_articleinfo",
            "pageTitle" => "tool_articleinfo",
            "subtitle" => "tool_articleinfo_desc",
            "project" => $project,
        ]);
    }

    /**
     * @Route("/articleinfo/{project}/{article}", name="ArticleInfoResult")
     */
    public function articleInfoProjectAction($project, $article)
    {
        $lh = $this->get("app.labs_helper");

        $lh->checkEnabled("articleinfo");

        $dbValues = $lh->databasePrepare($project, "ArticleInfo");

        $dbName = $dbValues["dbName"];
        $wikiName = $dbValues["wikiName"];
        $url = $dbValues["url"];

        // replace this example code with whatever you need
        return $this->render('articleInfo/result.html.twig', array(
            'page' => "articleinfo",
            "title" => "tool_articleinfo",
            "pageTitle" => "tool_articleinfo",
            "project" => $project,
            "article_title" => $article,
            "subtitle" => "tool_articleinfo_desc",
        ));
    }
}
