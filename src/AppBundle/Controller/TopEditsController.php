<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class TopEditsController extends Controller
{
    /**
     * @Route("/topedits", name="topedits")
     * @Route("/topedits", name="topEdits")
     * @Route("/topedits/", name="topEditsSlash")
     * @Route("/topedits/index.php", name="topEditsIndex")
     */
    public function indexAction()
    {

        $lh = $this->get("app.labs_helper");

        $lh->checkEnabled("topedits");

        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $project = $request->query->get('project');
        $username = $request->query->get('user');
        $namespace = $request->query->get('namespace');
        $article = $request->query->get('article');

        if ($project != "" && $username != "" && $namespace != "" && $article != "") {
            return $this->redirectToRoute("TopEditsResults", [
                'project'=>$project,
                'username' => $username,
                'namespace'=>$namespace,
                'article'=>$article,
            ]);
        } elseif ($project != "" && $username != "" && $namespace != "") {
            return $this->redirectToRoute("TopEditsResults", [
                'project'=>$project,
                'username' => $username,
                'namespace'=>$namespace,
            ]);
        } elseif ($project != "" && $username != "") {
            return $this->redirectToRoute("TopEditsResults", [
                'project' => $project,
                'username' => $username,
            ]);
        } elseif ($project != "") {
            return $this->redirectToRoute("TopEditsResults", [ 'project'=>$project ]);
        }

        // replace this example code with whatever you need
        return $this->render('topedits/index.html.twig', [
            "xtPageTitle" => "tool_topedits",
            "xtSubtitle" => "tool_topedits_desc",
            'xtPage' => "topedits",
        ]);
    }

    /**
     * @Route("/topedits/{project}/{username}/{namespace}/{article}", name="TopEditsResults")
     */
    public function resultAction($project, $username, $namespace = 0, $article = "")
    {

        $lh = $this->get("app.labs_helper");

        $lh->checkEnabled("topedits");

        $username = ucfirst($username);

        $dbValues = $lh->databasePrepare($project, "topEdits");

        $dbName = $dbValues["dbName"];
        $wikiName = $dbValues["wikiName"];
        $url = $dbValues["url"];

        if ($article === "") {
            return $this->render('topedits/result_namespace.html.twig', array(
                "xtPageTitle" => "tool_topedits",
                "xtSubtitle" => "tool_topedits_desc",
                'xtPage' => "topedits",

                'project' => $project,
                'username' => $username,
            ));
        } else {
            return $this->render('topedits/result_article.html.twig', [
                "xtPageTitle" => "tool_topedits",
                "xtSubtitle" => "tool_topedits_desc",
                'xtPage' => "topedits",

                'project' => $project,
                'username' => $username,
            ]);
        }
    }
}
