<?php

namespace AppBundle\Controller;

use AppBundle\Helper\ApiHelper;
use AppBundle\Helper\LabsHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class TopEditsController extends Controller
{
    private $lh;
    private $projectUrl;

    /**
     * @Route("/topedits", name="topedits")
     * @Route("/topedits", name="topEdits")
     * @Route("/topedits/", name="topEditsSlash")
     * @Route("/topedits/index.php", name="topEditsIndex")
     */
    public function indexAction(Request $request)
    {
        $this->lh = $this->get("app.labs_helper");
        $this->lh->checkEnabled("topedits");

        $project = $request->query->get('project');
        $username = $request->query->get('username');
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

        // set default wiki so we can populate the namespace selector
        if (!$project) {
            $project = $this->container->getParameter('default_project');
        }

        /** @var ApiHelper */
        $api = $this->get("app.api_helper");

        return $this->render('topedits/index.html.twig', [
            'xtPageTitle' => 'tool_topedits',
            'xtSubtitle' => 'tool_topedits_desc',
            'xtPage' => 'topedits',
            'project' => $project,
            'namespaces' => $api->namespaces($project),
        ]);
    }

    /**
     * @Route("/topedits/{project}/{username}/{namespace}/{article}", name="TopEditsResults")
     */
    public function resultAction($project, $username, $namespace = 0, $article = "")
    {
        /** @var LabsHelper $lh */
        $this->lh = $this->get('app.labs_helper');
        $this->lh->checkEnabled('topedits');
        $dbValues = $this->lh->databasePrepare($project);
        $this->projectUrl = $dbValues['url'];

        $username = ucfirst($username);

        if ($article === "") {
            return $this->namespaceTopEdits($username, $project, $namespace);
        } else {
            return $this->singlePageTopEdits($username, $project, $article);
        }
    }

    /**
     * List top edits by this user for all pages in a particular namespace.
     * @param string $username
     * @param string $project
     * @param integer $namespace
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function namespaceTopEdits($username, $project, $namespace)
    {
        // Get the basic data about the pages edited by this user.
        $query = "SELECT page_namespace, page_title, page_is_redirect, COUNT(page_title) AS count
                FROM ".$this->lh->getTable('page')." JOIN ".$this->lh->getTable('revision')." ON page_id = rev_page
                WHERE rev_user_text = :username AND page_namespace = :namespace
                GROUP BY page_namespace, page_title
                ORDER BY count DESC
                LIMIT 100";
        $params = ['username'=>$username, 'namespace'=> $namespace];
        $conn = $this->getDoctrine()->getManager('replicas')->getConnection();
        $editData = $conn->executeQuery($query, $params)->fetchAll();

        // Get page info about these 100 pages, so we can use their display title.
        $titles = array_map(function ($e) {
            return $e['page_title'];
        }, $editData);
        /** @var ApiHelper $apiHelper */
        $apiHelper = $this->get('app.api_helper');
        $displayTitles = $apiHelper->displayTitles($project, $titles);

        // Put all together, and return the view.
        $edits = [];
        foreach ($editData as $editDatum) {
            $pageTitle = $editDatum['page_title'];
            $editDatum['displaytitle'] = $displayTitles[$pageTitle];
            $edits[] = $editDatum;
        }
        return $this->render('topedits/result_namespace.html.twig', [
            'xtPage' => 'topedits',
            'project' => $project,
            'project_url' => $this->projectUrl,
            'username' => $username,
            'namespace' => $namespace,
            'edits' => $edits,
        ]);
    }

    /**
     * List top edits by this user for a particular page.
     * @param string $username
     * @param string $project
     * @param string $article
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    protected function singlePageTopEdits($username, $project, $article)
    {
        /** @var ApiHelper $apiHelper */
        $apiHelper = $this->get("app.api_helper");
        $pageInfo = $apiHelper->getBasicPageInfo($project, $article, true);
        if (isset($pageInfo['missing']) && $pageInfo['missing']) {
            // Redirect if the page doesn't exist.
            $this->addFlash("notice", ["noresult", $article]);
            return $this->redirectToRoute("topedits");
        }

        // Get all revisions of this page by this user.
        $query = "SELECT
                    revs.rev_id AS id,
                    revs.rev_timestamp AS timestamp,
                    (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                    revs.rev_comment AS comment
                FROM ".$this->lh->getTable('revision')." AS revs
                    LEFT JOIN ".$this->lh->getTable('revision')."
                    AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                WHERE revs.rev_user_text in (:username) AND revs.rev_page = :pageid
                ORDER BY revs.rev_timestamp DESC
            ";
        $params = ['username' => $username, 'pageid' => $pageInfo['pageid']];
        $conn = $this->getDoctrine()->getManager('replicas')->getConnection();
        $revisionsData = $conn->executeQuery($query, $params)->fetchAll();

        // Loop through all revisions and format dates, find totals, etc.
        $totalAdded = 0;
        $totalRemoved = 0;
        $revisions = [];
        foreach ($revisionsData as $revision) {
            if ($revision['length_change'] > 0) {
                $totalAdded += $revision['length_change'];
            } else {
                $totalRemoved += $revision['length_change'];
            }
            $time = strtotime($revision['timestamp']);
            $revision['timestamp'] = $time; // formatted via Twig helper
            $revision['year'] = date('Y', $time);
            $revision['month'] = date('m', $time);
            $revisions[] = $revision;
        }

        // Send all to the template.
        return $this->render('topedits/result_article.html.twig', [
            'xtPage' => 'topedits',
            'project' => $project,
            'project_url' => $this->projectUrl,
            'username' => $username,
            'article' => $pageInfo,
            'total_added' => $totalAdded,
            'total_removed' => $totalRemoved,
            'revisions' => $revisions,
            'revision_count' => count($revisions),
        ]);
    }
}
