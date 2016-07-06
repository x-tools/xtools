<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;

class EditCounterController extends Controller
{
    /**
     * @Route("/ec", name="EditCounter")
     * @Route("/ec/", name="EditCounterSlash")
     * @Route("/ec/index.php", name="EditCounterIndexPhp")
     */
    public function indexAction()
    {

        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $project = $request->query->get('project');
        $username = $request->query->get('user');

        if ($project != "" && $username != "") {
            return $this->redirectToRoute("EditCounterResult", array('project'=>$project, 'username' => $username));
        }
        else if ($project != "") {
            return $this->redirectToRoute("EditCounterProject", array('project'=>$project));
        }

        // Otherwise fall through.
        return $this->render('editCounter/index.html.twig', [
            "pageTitle" => "tool_ec",
            "subtitle" => "tool_ec_desc",
            'page' => "ec",
            'title' => "tool_ec",
        ]);
    }

    /**
     * @Route("/ec/{project}", name="EditCounterProject")
     */
    public function projectAction($project) {
        return $this->render('editCounter/index.html.twig', [
            'title' => "$project edit counter",
            'page' => "ec",
            'project' => $project,
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
        ]);
    }
    
    /**
     * @Route("/ec/{project}/{username}", name="EditCounterResult")
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
            throw new Exception("Unknown wiki \"$project\"");
        }

        // Grab the data we need out of it.
        $dbName = $wikis[0]['dbName'];
        $wikiName = $wikis[0]['name'];
        $url = $wikis[0]['url'];

        if (substr($dbName, -2) != "_p") {
            $dbName .= "_p";
        }

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
			(SELECT 'first_rev' as source, rev_timestamp FROM $dbName.`revision_userindex` WHERE rev_user_text = :username ORDER BY rev_timestamp ASC LIMIT 1)
			UNION
			(SELECT 'latest_rev' as source, rev_timestamp FROM $dbName.`revision_userindex` WHERE rev_user_text = :username ORDER BY rev_timestamp DESC LIMIT 1)
			UNION
			SELECT 'groups' as source, ug_group as value FROM $dbName.user_groups JOIN user on user_id = ug_user WHERE user_name = :username
            ");

        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();

        // Fetch the result data
        $results = $resultQuery->fetchAll();

        // Unknown user
        if (sizeof($results) < 3) {
            throw new Exception("Unknown user \"$username\"");
        }

        // Initialize the variables - just so we don't get variable undefined errors if there is a problem
        $id = "";
        $arch = "";
        $rev = "";
        $first_rev = "";
        $latest_rev = "";
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
            if($row["source"] == "first_rev") {
                $first_rev = $row["value"];
            }
            if($row["source"] == "latest_rev") {
                $latest_rev = $row["value"];
            }
            if($row["source"] == "groups") {
                $groups .= $row["value"]. ", ";
            }
        }

        // Remove the last comma and space
        if (strlen($groups) > 2) {
            $groups = substr($groups, 0, -2);
        }

        // If the user isn't in any groups, show a message.
        if (strlen($groups) == 0) {
            $groups = "No groups";
        }

        return $this->render('editCounter/result.html.twig', [
            'title' => "tool_ec",
            'page' => "ec",
            'project' => $project,
            'username' => $username,
            'wiki' => $dbName,
            'name' => $wikiName,
            'url' => $url,
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            'user_id' => $id,
            'user_groups' => $groups,
            'deleted_edits' => $arch,
            'total_edits' => $rev + $arch,
            'live_edits' => $rev,
            'first_rev' => $first_rev,
            'latest_rev' => $latest_rev,
        ]);
    }
}
