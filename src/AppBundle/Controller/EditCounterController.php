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
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('editCounter/index.html.twig', [
            'page' => "ec",
            'pageTitle' => "tool_ec",
            'subtitle' => "tool_ec_desc"
        ]);
    }

    /**
     * @Route("/ec/get", name="EditCounterRedirect")
     */

    public function getAction() {
        $request = Request::createFromGlobals();

        $project = $request->query->get('project');
        $username = $request->query->get('user');

        if ($project == "") {
            // Redirect back to the index
            return $this->redirectToRoute("EditCounter", array());
        }
        if ($username == "") {
            // Redirect to the project selection
            return $this->redirectToRoute("EditCounterProject", array(
                'project' => $project
            ));
        }

        return $this->redirectToRoute('EditCounterResult', array(
            'project' => $project,
            'username' => $username));
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

        // Grab the connection to the replica database (which is separate from the above)
        $conn = $this->get('doctrine')->getManager("replicas")->getConnection();



        return $this->render('editCounter/result.html.twig', [
            'title' => "$username@$dbName Edit Counter",
            'page' => "ec",
            'project' => $project,
            'username' => $username,
            'wiki' => $dbName,
            'name' => $wikiName,
            'url' => $url,
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
        ]);
    }
}
