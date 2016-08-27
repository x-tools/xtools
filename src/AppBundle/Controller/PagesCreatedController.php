<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;

class PagesCreatedController extends Controller
{
    /**
     * @Route("/pages", name="PagesCreated")
     * @Route("/pages/", name="PagesCreatedSlash")
     * @Route("/pages/index.php", name="PagesCreatedIndexPhp")
     */
    public function indexAction()
    {

        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $project = $request->query->get('project');
        $username = $request->query->get('user');

        if ($project != "" && $username != "") {
            return $this->redirectToRoute("PagesCreatedResult", array('project'=>$project, 'username' => $username));
        }
        else if ($project != "") {
            return $this->redirectToRoute("PagesCreatedProject", array('project'=>$project));
        }

        // Otherwise fall through.
        return $this->render('pagesCreated/index.html.twig', [
            "pageTitle" => "tool_sc",
            "subtitle" => "tool_sc_desc",
            'page' => "sc",
            'title' => "tool_sc",
        ]);
    }

    /**
     * @Route("/pages/{project}", name="PagesCreatedProject")
     */
    public function projectAction($project) {
        return $this->render('pagesCreated/index.html.twig', [
            'title' => "tool_sc",
            'page' => "sc",
            "pageTitle" => "tool_sc",
            "subtitle" => "tool_sc_desc",
            'project' => $project,
        ]);
    }

    /**
     * @Route("/pages/{project}/{username}", name="PagesCreatedResult")
     */
    public function resultAction($project, $username) {

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
            $this->addFlash('notice', "Unknown wiki $project");
            return $this->redirectToRoute("SimpleEditCounter");
        }

        // Grab the data we need out of it.
        $dbName = $wikis[0]['dbName'];
        $wikiName = $wikis[0]['name'];
        $url = $wikis[0]['url'];

        if (substr($dbName, -2) != "_p") {
            $dbName .= "_p";
        }

        // Grab the connection to the replica database (which is separate from the above)
        $conn = $this->get('doctrine')->getManager("replicas")->getConnection();

        // Prepare the query and execute
        $resultQuery = $conn->prepare( "
			SELECT 'id' as source, user_id as value FROM $dbName.user WHERE user_name = :username
			UNION
			SELECT 'arch' as source, COUNT(*) AS value FROM $dbName.archive_userindex WHERE ar_user_text = :username
			UNION
			SELECT 'rev' as source, COUNT(*) AS value FROM $dbName.revision_userindex WHERE rev_user_text = :username
			UNION
			SELECT 'groups' as source, ug_group as value FROM $dbName.user_groups JOIN user on user_id = ug_user WHERE user_name = :username
            ");

        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();

        // Fetch the result data
        $results = $resultQuery->fetchAll();

        dump($results);

        // Initialize the variables - just so we don't get variable undefined errors if there is a problem
        $id = "";
        $arch = "";
        $rev = "";
        $groups = "";

        // Iterate over the results, putting them in the right variables
        foreach($results as $row) {
            if($row["source"] == "id") {
                $id = $row["value"];
            }
            if($row["source"] == "arch") {
                $arch = $row["value"];
            }
            if($row["source"] == "rev") {
                $rev = $row["value"];
            }
            if($row["source"] == "groups") {
                $groups .= $row["value"]. ", ";
            }
        }

        // Unknown user - If the user is created the $results variable will have 3 entries.  This is a workaround to detect
        // non-existant IPs.
        if (sizeof($results) < 3 && $arch == 0 && $rev == 0) {
            //throw new Exception("User \"$username\" does not exist or has not made edits");
            $this->addFlash('notice', "User $username does not exist or has not made edits");
            return $this->redirectToRoute("SimpleEditCounterProject", ["project"=>$project]);
        }

        // Remove the last comma and space
        if (strlen($groups) > 2) {
            $groups = substr($groups, 0, -2);
        }

        // If the user isn't in any groups, show a message.
        if (strlen($groups) == 0) {
            $groups = "---";
        }

        // Assign the values and display the template
        return $this->render('simpleEditCounter/result.html.twig', [
            'title' => "tool_sc",
            'page' => "sc",
            "pageTitle" => "tool_sc",
            "subtitle" => "tool_sc_desc",
            'url' => $url,
            'username' => $username,
            'project' => $wikiName,

            'id' => $id,
            'arch' => $arch,
            'rev' => $rev + $arch,
            'live' => $rev,
            'groups' => $groups,
        ]);
    }
}
