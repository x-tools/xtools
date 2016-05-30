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
        return $this->render('simpleEditCounter/index.html.twig', []);
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
            ->select(['dbName','lang','`name`','family','url'])
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

        $dbName = $wikis[0]['dbName'];
        $lang = $wikis[0]['lang'];
        $name = $wikis[0]['name'];
        $family = $wikis[0]['family'];
        $url = $wikis[0]['url'];

        return $this->render('simpleEditCounter/result.html.twig', [
            'title' => "$username@$dbName Edit Counter",
            'project' => $project,
            'username' => $username,
            'wiki' => $dbName,
            'lang' => $lang,
            'name' => $name,
            'family' => $family,
            'url' => $url,
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
        ]);
    }
}
