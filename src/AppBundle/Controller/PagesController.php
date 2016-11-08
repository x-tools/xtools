<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PagesController extends Controller
{
    /**
     * @Route("/pages", name="Pages")
     * @Route("/pages/", name="PagesSlash")
     * @Route("/pages/index.php", name="PagesIndexPhp")
     */
    public function indexAction()
    {
        if (!$this->getParameter("enable.pages")) {
            throw new NotFoundHttpException("This tool is disabled");
        }

        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $project = $request->query->get('project');
        $username = $request->query->get('user');
        $namespace = $request->query->get('namespace');


        if ($project != "" && $username != "" && $namespace != "") {
            return $this->redirectToRoute("PagesResult", array('project'=>$project, 'username' => $username, 'namespace'=>$namespace));
        }
        elseif ($project != "" && $username != "") {
            return $this->redirectToRoute("PagesProjectUsername", array('project'=>$project, 'username' => $username));
        }
        else if ($project != "") {
            return $this->redirectToRoute("PagesProject", array('project'=>$project));
        }


        // Retrieving the global groups, using the Apihelper class
        $api = $this->get("app.api_helper");
        $namespaces = $api->namespaces("http://localhost/~wiki");

        // Otherwise fall through.
        return $this->render('Pages/index.html.twig', [
            "pageTitle" => "tool_pages",
            "subtitle" => "tool_pages_desc",
            'page' => "pages",
            'title' => "tool_pages",

            'namespaces' => $namespaces,
        ]);
    }

    /**
     * @Route("/pages/{project}", name="PagesProject")
     */
    public function projectAction($project) {

        if (!$this->getParameter("enable.pages")) {
            throw new NotFoundHttpException("This tool is disabled");
        }
        return $this->render('Pages/index.html.twig', [
            'title' => "tool_sc",
            'page' => "sc",
            "pageTitle" => "tool_sc",
            "subtitle" => "tool_sc_desc",
            'project' => $project,
        ]);
    }

    /**
     * @Route("/pages/{project}/{username}", name="PagesProjectUsername")
     */
    public function projectUsernameAction($project, $username) {
        if (!$this->getParameter("enable.pages")) {
            throw new NotFoundHttpException("This tool is disabled");
        }

        return $this->redirectToRoute("PagesResult", array("project"=>$project, "username"=>$username, "namespace"=>"all"));
    }

    /**
     * @Route("/pages/{project}/{username}/{namespace}", name="PagesResult")
     */
    public function resultAction($project, $username, $namespace) {
        if (!$this->getParameter("enable.pages")) {
            throw new NotFoundHttpException("This tool is disabled");
        }


        // Assign the values and display the template
        return $this->render('pages/result.html.twig', [
            'title' => "tool_pages",
            'page' => "pages",
            "pageTitle" => "tool_pages",
            "subtitle" => "tool_pages_desc",

            'project' => $project,
            'username' => $username,
            'namespace' => $namespace,
        ]);
    }
}
