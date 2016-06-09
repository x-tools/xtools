<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;

class SimpleEditCounterController extends Controller
{
    /**
     * @Route("/sc", name="SimpleEditCounter")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('simpleEditCounter/index.html.twig', [
            "pageTitle" => "Quick, Dirty, Simple Edit Counter",
            "subtitle" => "Quick user contribution analysis",
            'page' => "sc",
        ]);
    }

    /**
     * @Route("/sc/get", name="SimpleEditCounterRedirect")
     */

    public function getAction() {
        $request = Request::createFromGlobals();

        $project = $request->query->get('project');
        $username = $request->query->get('user');

        if ($project == "") {
            // Redirect back to the index
            return $this->redirectToRoute("SimpleEditCounter", array());
        }
        if ($username == "") {
            // Redirect to the project selection
            return $this->redirectToRoute("SimpleEditCounterProject", array(
                'project' => $project
            ));
        }

        return $this->redirectToRoute('SimpleEditCounterResult', array(
            'project' => $project,
            'username' => $username));
    }

    /**
     * @Route("/sc/{project}", name="SimpleEditCounterProject")
     */
    public function projectAction($project) {
        return $this->render('simpleEditCounter/index.html.twig', [
            'title' => "$project edit counter",
            'page' => "sc",
            "pageTitle" => "Quick, Dirty, Simple Edit Counter",
            "subtitle" => "Quick user contribution analysis",
            'project' => $project,
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
        ]);
    }

    /**
     * @Route("/sc/{project}/{username}", name="SimpleEditCounterResult")
     */
    public function resultAction($project, $username) {
        $conn = $this->get('doctrine')->getManager("meta")->getConnection();
        $wikiQuery = $conn->createQueryBuilder();
        $wikiQuery
            ->select(['name','url'])
            ->from("wiki")
            ->where($wikiQuery->expr()->eq('dbname', ':project'))
            ->orwhere($wikiQuery->expr()->like('url', ":project"))
            ->setParameter("project", $project);
        $wikiStatement = $wikiQuery->execute();

        $wikis = $wikiStatement->fetchAll();

        if (sizeof($wikis) <1) {
            throw new Exception("Unknown wiki \"$project\"");
        }

        dump($wikis);

        $wikiName = $wikis[0]['name'];
        $url = $wikis[0]['url'];

        $conn = $this->get('doctrine')->getManager("replicas")->getConnection();
        $resultQuery = $conn->prepare( "
			SELECT 'id' as source, user_id as value FROM mw_user WHERE user_name = :username
			UNION
			SELECT 'arch' as source, COUNT(*) AS value FROM mw_archive WHERE ar_user_text = :username
			UNION
			SELECT 'rev' as source, COUNT(*) AS value FROM mw_revision WHERE rev_user_text = :username
			UNION
			SELECT 'groups' as source, ug_group as value FROM mw_user_groups JOIN mw_user on user_id = ug_user WHERE user_name = :username
            ");

        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();

        $results = $resultQuery->fetchAll();

        dump($results);

        if (sizeof($results) <1) {
            throw new Exception("Unknown user \"$username\"");
        }

        $id = "";
        $arch = "";
        $rev = "";
        $groups = "";

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

        if (strlen($groups) > 2) {
            $groups = substr($groups, 0, -2);
        }

        if (strlen($groups) == 0) {
            $groups = "No groups";
        }

        return $this->render('simpleEditCounter/result.html.twig', [
            'title' => "Plain and simple edit counter | $username@$wikiName",
            'page' => "sc",
            'url' => $url,
            'username' => $username,

            'id' => $id,
            'arch' => $arch,
            'rev' => $rev,
            'live' => $rev - $arch,
            'groups' => $groups,
        ]);
    }
}
