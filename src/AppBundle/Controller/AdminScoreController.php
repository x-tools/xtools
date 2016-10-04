<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class AdminScoreController extends Controller
{
    /**
     * @Route("/adminscore", name="AdminScore")
     * @Route("/adminscore/", name="AdminScoreSlash")
     * @Route("/adminscore/index.php", name="AdminScoreIndexPhp")
     * @Route("/adminscore/get", name="AdminScoreGet")
     * @Route("/scottywong tools/adminscore.php", name="AdminScoreLegacy")
     */
    public function indexAction()
    {
        if (!$this->getParameter("enable.adminscore")) {
            throw new NotFoundHttpException("This tool is disabled");
        }

        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $project = $request->query->get('project');
        $username = $request->query->get('user');

        if ($project != "" && $username != "") {
            return $this->redirectToRoute("AdminScoreResult", array('project'=>$project, 'username' => $username));
        }
        else if ($project != "") {
            return $this->redirectToRoute("AdminScoreProject", array('project'=>$project));
        }

        // Otherwise fall through.
        return $this->render('adminscore/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            "page" => "adminscore",
            "pageTitle" => "tool_adminscore",
            "subtitle" => "tool_adminscore_desc"
        ]);
    }

    /**
     * @Route("/adminscore/{project}/{username}", name="AdminScoreResult")
     */
    public function resultAction($project, $username)
    {
        if (!$this->getParameter("enable.adminscore")) {
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
            return $this->redirectToRoute("AdminScore");
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
        /*
        1 => array('Account age', \"SELECT user_registration FROM user WHERE user_name='\".$_REQUEST['account'].\"';\", 'date'),
		2 => array('Edit count', \"SELECT user_editcount FROM user WHERE user_name='\".$_REQUEST['account'].\"';\", '-100+0.01*'),
		3 => array('User page', \"SELECT page_len FROM page WHERE page_namespace=2 AND page_title='\".$_REQUEST['account'].\"';\", '-100+0.1*'),
		4 => array('Patrols', \"SELECT COUNT(*) FROM logging WHERE log_type='patrol' and log_action='patrol' and log_namespace=0 and log_deleted=0 and log_user_text='\".$_REQUEST['account'].\"';\", '-100+0.01*'),
		5 => array('Block count', \"SELECT COUNT(*) FROM logging WHERE log_type=\\"block\\" AND log_action=\\"block\\" AND log_namespace=2 AND log_deleted=0 AND log_title='\".$_REQUEST['account'].\"';\", '100-10*'),
		6 => array('WP:AFD', \"SELECT COUNT(*) FROM revision WHERE rev_page like 'Articles for deletion/%' and rev_page not like 'Articles_for_deletion/Log/%' and rev_user_text='\".$_REQUEST['account'].\"';\",'1*'),
		7 => array('Recent activity', \"SELECT COUNT(*) FROM revision WHERE rev_user_text='\".$_REQUEST['account'].\"' AND rev_timestamp > (now()-INTERVAL 730 day) and rev_timestamp < now();\",'1*'),
		8 => array('WP:RPP', \"SELECT COUNT(*) FROM revision WHERE rev_page like 'Administrator intervention against vandalism%' and rev_user_text='\".$_REQUEST['account'].\"';\",'1*'),
		9 => array('Edit summaries', \"SELECT COUNT(*) FROM revision JOIN page ON rev_page=page_id WHERE page_namespace=0 AND rev_user_text='\".$_REQUEST['account'].\"' AND rev_comment='';\",'1*'),
		10 => array('Namespaces', \"SELECT count(*) FROM revision JOIN page ON rev_page=page_id WHERE rev_user_text='\".$_REQUEST['account'].\"' AND page_namespace=0;\",'1*'),
		11 => array('Articles created', \"SELECT DISTINCT page_id FROM page JOIN revision ON page_id=rev_page WHERE rev_user_text='\".$_REQUEST['account'].\"' and page_namespace=0 AND page_is_redirect=0;\",'1*'),
		12 => array('WP:AIV', \"SELECT COUNT(*) FROM revision WHERE rev_page like 'Requests_for_page_protection%' and rev_user_text='\".$_REQUEST['account'].\"';\",'1*')
	    );*/
        $resultQuery = $conn->prepare( "
        SELECT 'acct_age' as source, user_registration as value FROM user WHERE user_name=:username
        UNION
        SELECT 'edit_count' as source, user_editcount as value FROM user WHERE user_name=:username
        UNION
        SELECT 'user_page' as source, page_len as value FROM page WHERE page_namespace=2 AND page_title=:username
        UNION
        SELECT 'patrols' as source, COUNT(*) as value FROM logging WHERE log_type='patrol' and log_action='patrol' and log_namespace=0 and log_deleted=0 and log_user_text=:username
        UNION
        SELECT 'blocks' as source, COUNT(*) as value FROM logging WHERE log_type='block' AND log_action='block' AND log_namespace=2 AND log_deleted=0 AND log_title=:username
        UNION
        SELECT 'afd' as source, COUNT(*) as value FROM revision WHERE rev_page like 'Articles for deletion/%' and rev_page not like 'Articles_for_deletion/Log/%' and rev_user_text=:username
        UNION
        SELECT 'recent_activity' as source, COUNT(*) as value FROM revision WHERE rev_user_text=:username AND rev_timestamp > (now()-INTERVAL 730 day) and rev_timestamp < now()
        UNION
        SELECT 'aiv' as source, COUNT(*) as value FROM revision WHERE rev_page like 'Administrator intervention against vandalism%' and rev_user_text=:username
        UNION
        SELECT 'edit_summaries' as source, COUNT(*) as value FROM revision JOIN page ON rev_page=page_id WHERE page_namespace=0 AND rev_user_text=:username
        UNION
        SELECT 'namespaces' as source, count(*) as value FROM revision JOIN page ON rev_page=page_id WHERE rev_user_text=:username AND page_namespace=0
        UNION
        SELECT 'articles_created' as source, page_id as value FROM page JOIN revision ON page_id=rev_page WHERE rev_user_text=:username and page_namespace=0 AND page_is_redirect=0 GROUP BY page_id
        UNION
        SELECT 'rpp' as source, COUNT(*) as value FROM revision WHERE rev_page like 'Requests_for_page_protection%' and rev_user_text=:username
        ");

        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();

        // Fetch the result data
        $results = $resultQuery->fetchAll();

        dump($results);


        // replace this example code with whatever you need
        return $this->render('adminscore/result.html.twig', array(
            "page" => "adminscore",
            "title" => "About",
            "pageTitle" => "about",
            'url' => $url,
            'username' => $username,
            'project' => $wikiName,
        ));
    }
}
