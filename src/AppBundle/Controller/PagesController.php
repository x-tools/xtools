<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
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
        $redirects = $request->query->get('redirect');


        if ($project != "" && $username != "" && $namespace != "" && $redirects != "") {
            return $this->redirectToRoute("PagesResult", array('project'=>$project, 'username' => $username, 'namespace'=>$namespace, 'redirects'=>$redirects));
        }
        elseif ($project != "" && $username != "" && $namespace != "") {
            return $this->redirectToRoute("PagesResult", array('project'=>$project, 'username' => $username, 'namespace'=>$namespace));
        }
        elseif ($project != "" && $username != "") {
            return $this->redirectToRoute("PagesResult", array('project'=>$project, 'username' => $username));
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

        // Retrieving the global groups, using the Apihelper class
        $api = $this->get("app.api_helper");
        $namespaces = $api->namespaces("http://localhost/~wiki");

        return $this->render('Pages/index.html.twig', [
            "pageTitle" => "tool_pages",
            "subtitle" => "tool_pages_desc",
            'page' => "pages",
            'title' => "tool_pages",
            'project' => $project,

            'namespaces' => $namespaces,
        ]);
    }

    /**
     * @Route("/pages/{project}/{username}/{namespace}/{redirects}", name="PagesResult")
     */
    public function resultAction($project, $username, $namespace="all", $redirects = "none") {
        if (!$this->getParameter("enable.pages")) {
            throw new NotFoundHttpException("This tool is disabled");
        }
        $namespaceConditionArc = "";
        $namespaceConditionArc2 = "";
        $namespaceConditionRev = "";

        if ($namespace != "all") {
            $namespaceConditionRev = " and page_namespace = '".intval($namespace)."' ";
            $namespaceConditionArc = " and ar_namespace = '".intval($namespace)."' ";
            $namespaceConditionArc2 = " and b.ar_namespace = '".intval($namespace)."' ";
        }

        $redirectCondition = "";
        if ( $redirects == "onlyredirects" ){ $redirectCondition = " and page_is_redirect = '1' "; }
        if ( $redirects == "noredirects" ){ $redirectCondition = " and page_is_redirect = '0' "; }

        //if ( $ui->isIP ){
            $whereRev = " rev_user_text = '$username' AND rev_user = '0' ";
            $whereArc = " ar_user_text = '$username' AND ar_user = '0' ";
            $whereArc2 = " b.ar_user_text = '$username' AND b.ar_user = '0' ";
            $having = " rev_user_text = '$username' ";
        //}
        /*
        else {
            $whereRev = " rev_user = '$userid' AND rev_timestamp > 1 ";
            $whereArc = " ar_user = '$userid' AND ar_timestamp > 1 ";
            $whereArc2 = " b.ar_user = '$userid' AND b.ar_timestamp > 1 ";
            $having = " rev_user = '$userid' ";
        }*/

        $query2 = "
			(SELECT DISTINCT page_namespace as namespace, 'rev' as type, page_title as page_title, page_is_redirect as page_is_redirect, rev_timestamp as timestamp, rev_user, rev_user_text
			FROM page
			JOIN revision_userindex on page_id = rev_page
			WHERE  $whereRev  AND rev_parent_id = '0'  $namespaceConditionRev  $redirectCondition
			)
			
			UNION
			
			(SELECT  a.ar_namespace as namespace, 'arc' as type, a.ar_title as page_title, '0' as page_is_redirect, min(a.ar_timestamp) as timestamp , a.ar_user as rev_user, a.ar_user_text as rev_user_text
			FROM archive_userindex a
			JOIN 
			 (
			  Select b.ar_namespace, b.ar_title
			  FROM archive_userindex as b
			  LEFT JOIN logging_logindex on log_namespace = b.ar_namespace and log_title = b.ar_title  and log_user = b.ar_user and (log_action = 'move' or log_action = 'move_redir')
			  WHERE  $whereArc AND b.ar_parent_id = '0' $namespaceConditionArc and log_action is null
			 ) AS c on c.ar_namespace= a.ar_namespace and c.ar_title = a.ar_title 
			GROUP BY a.ar_namespace, a.ar_title
			HAVING  $having
			)
			";

        dump($query2);


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
