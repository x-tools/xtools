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
        $redirects = $request->query->get('redirects');

        if ($project != "" && $username != "" && $namespace != "" && $redirects != "") {
            return $this->redirectToRoute("PagesResult", array('project'=>$project, 'username' => $username, 'namespace'=>$namespace, 'redirects'=>$redirects));
        }
        elseif ($project != "" && $username != "" && $namespace != "") {
            return $this->redirectToRoute("PagesResult", array('project'=>$project, 'username' => $username, 'namespace'=>$namespace));
        }
        elseif ($project != "" && $username != "" && $redirects != "") {
            return $this->redirectToRoute("PagesResult", array('project'=>$project, 'username' => $username, 'redirects'=>$redirects));
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
        return $this->render('pages/index.html.twig', [
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

        return $this->render('pages/index.html.twig', [
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
    public function resultAction($project, $username, $namespace = "all", $redirects = "none") {
        if (!$this->getParameter("enable.pages")) {
            throw new NotFoundHttpException("This tool is disabled");
        }


        $username = ucfirst($username);

        // Grab the connection to the meta database
        $conn = $this->get('doctrine')->getManager("meta")->getConnection();

        // Create the query we're going to run against the meta database
        $wikiQuery = $conn->createQueryBuilder();
        $wikiQuery
            ->select(['dbName','name','url'])
            ->from("wiki")
            ->where($wikiQuery->expr()->eq('dbname', ':project'))
            ->orwhere($wikiQuery->expr()->like('name', ':project'))
            ->orwhere($wikiQuery->expr()->like('url', ":project"))
            ->setParameter("project", $project);
        $wikiStatement = $wikiQuery->execute();

        // Fetch the wiki data
        $wikis = $wikiStatement->fetchAll();

        // Throw an exception if we can't find the wiki
        if (sizeof($wikis) <1) {
            $this->addFlash('notice', ["nowiki", $project]);
            return $this->redirectToRoute("Pages");
        }

        // Grab the data we need out of it.
        $dbName = $wikis[0]['dbName'];
        $wikiName = $wikis[0]['name'];
        $url = $wikis[0]['url'];

        if (substr($dbName, -2) != "_p") {
            $dbName .= "_p";
        }

        $user_id = 0;

        // Grab the connection to the replica database (which is separate from the above)
        $conn = $this->get('doctrine')->getManager("replicas")->getConnection();

        // Prepare the query and execute
        $resultQuery = $conn->prepare( "
			SELECT 'id' as source, user_id as value FROM $dbName.user WHERE user_name = :username
            ");

        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();

        $result = $resultQuery->fetchAll();

        if (isset($result[0]["value"])) {
            $user_id = $result[0]["value"];
        }

        $namespaceConditionArc = "";
        // $namespaceConditionArc2 = "";
        $namespaceConditionRev = "";

        if ($namespace != "all") {
            $namespaceConditionRev = " and page_namespace = '".intval($namespace)."' ";
            $namespaceConditionArc = " and ar_namespace = '".intval($namespace)."' ";
            //$namespaceConditionArc2 = " and b.ar_namespace = '".intval($namespace)."' ";
        }

        $redirectCondition = "";
        if ( $redirects == "onlyredirects" ){ $redirectCondition = " and page_is_redirect = '1' "; }
        if ( $redirects == "noredirects" ){ $redirectCondition = " and page_is_redirect = '0' "; }

        if ( $user_id == 0) { // IP Editor or undefined username.
            $whereRev = " rev_user_text = '$username' AND rev_user = '0' ";
            $whereArc = " ar_user_text = '$username' AND ar_user = '0' ";
            //$whereArc2 = " b.ar_user_text = '$username' AND b.ar_user = '0' ";
            $having = " rev_user_text = '$username' ";
        }
        else {
            $whereRev = " rev_user = '$user_id' AND rev_timestamp > 1 ";
            $whereArc = " ar_user = '$user_id' AND ar_timestamp > 1 ";
            //$whereArc2 = " b.ar_user = '$user_id' AND b.ar_timestamp > 1 ";
            $having = " rev_user = '$user_id' ";
        }

        $stmt = "
			(SELECT DISTINCT page_namespace as namespace, 'rev' as type, page_title as page_title, page_is_redirect as page_is_redirect, rev_timestamp as timestamp, rev_user, rev_user_text
			FROM $dbName.page
			JOIN $dbName.revision_userindex on page_id = rev_page
			WHERE  $whereRev  AND rev_parent_id = '0'  $namespaceConditionRev  $redirectCondition
			)
			
			UNION
			
			(SELECT  a.ar_namespace as namespace, 'arc' as type, a.ar_title as page_title, '0' as page_is_redirect, min(a.ar_timestamp) as timestamp , a.ar_user as rev_user, a.ar_user_text as rev_user_text
			FROM $dbName.archive_userindex a
			JOIN 
			 (
			  Select b.ar_namespace, b.ar_title
			  FROM $dbName.archive_userindex as b
			  LEFT JOIN $dbName.logging_logindex on log_namespace = b.ar_namespace and log_title = b.ar_title  and log_user = b.ar_user and (log_action = 'move' or log_action = 'move_redir')
			  WHERE  $whereArc AND b.ar_parent_id = '0' $namespaceConditionArc and log_action is null
			 ) AS c on c.ar_namespace= a.ar_namespace and c.ar_title = a.ar_title 
			GROUP BY a.ar_namespace, a.ar_title
			HAVING  $having
			)
			";
        $resultQuery = $conn->prepare($stmt);
        $resultQuery->execute();

        $result = $resultQuery->fetchAll();

        $pagesArray = [];
        $countArray = [];
        $total = 0;
        $redirectTotal = 0;
        $deletedTotal = 0;

        foreach ($result as $row) {
            $datetime = date(DATE_W3C, strtotime($row["timestamp"]));
            $human_time = date("Y-m-d", strtotime($row["timestamp"]));
            $pagesArray[$row["namespace"]][$datetime] = $row;
            $pagesArray[$row["namespace"]][$datetime]["human_time"] = $human_time;

            // Totals
            if (isset($countArray[$row["namespace"]]["total"])) {
                $countArray[$row["namespace"]]["total"]++;
            }
            else {
                $countArray[$row["namespace"]]["total"] = 1;
                $countArray[$row["namespace"]]["redirect"] = 0;
                $countArray[$row["namespace"]]["deleted"] = 0;
            }
            $total++;

            if ($row["page_is_redirect"]) {
                $redirectTotal++;
                // Redirects
                if (isset($countArray[$row["namespace"]]["redirect"])) {
                    $countArray[$row["namespace"]]["redirect"]++;
                } else {
                    $countArray[$row["namespace"]]["redirect"] = 1;
                }
            }

            if ($row["type"] === "arc") {
                $deletedTotal++;
                // Deleted
                if (isset($countArray[$row["namespace"]]["deleted"])) {
                    $countArray[$row["namespace"]]["deleted"]++;
                } else {
                    $countArray[$row["namespace"]]["deleted"] = 1;
                }
            }

        }

        ksort($pagesArray);
        ksort($countArray);

        foreach (array_keys($pagesArray) as $key) {
            krsort($pagesArray[$key]);
        }

        // Retrieving the namespaces, using the Apihelper class
        $api = $this->get("app.api_helper");
        $namespaces = $api->namespaces($url);

        // Assign the values and display the template
        return $this->render('pages/result.html.twig', [
            'title' => "tool_pages",
            'page' => "pages",
            "pageTitle" => "tool_pages",
            "subtitle" => "tool_pages_desc",
            'url' => $url,

            'project' => $project,
            'username' => $username,
            'namespace' => $namespace,
            'redirect' => $redirects,

            'namespaces' => $namespaces,

            'pages' => $pagesArray,
            'count' => $countArray,

            'total' => $total,
            'redirectTotal' => $redirectTotal,
            'deletedTotal' => $deletedTotal,
        ]);
    }
}
