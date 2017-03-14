<?php

namespace AppBundle\Controller;

use AppBundle\Helper\ApiHelper;
use AppBundle\Helper\LabsHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class TopEditsController extends Controller
{
    /**
     * @Route("/topedits", name="topedits")
     * @Route("/topedits", name="topEdits")
     * @Route("/topedits/", name="topEditsSlash")
     * @Route("/topedits/index.php", name="topEditsIndex")
     */
    public function indexAction()
    {

        $lh = $this->get("app.labs_helper");

        $lh->checkEnabled("topedits");

        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $project = $request->query->get('project');
        $username = $request->query->get('user');
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

        // replace this example code with whatever you need
        return $this->render('topedits/index.html.twig', [
            "xtPageTitle" => "tool_topedits",
            "xtSubtitle" => "tool_topedits_desc",
            'xtPage' => "topedits",
        ]);
    }

    /**
     * @Route("/topedits/{project}/{username}/{namespace}/{article}", name="TopEditsResults")
     */
    public function resultAction($project, $username, $namespace = 0, $article = "")
    {
        /** @var LabsHelper $lh */
        $lh = $this->get("app.labs_helper");
        $lh->checkEnabled("topedits");
        $lh->databasePrepare($project);

        $username = ucfirst($username);

        if ($article === "") {
            return $this->namespaceTopEdits($lh, $username, $project, $namespace);
        } else {
            return $this->singlePageTopEdits($lh, $project, $article, $username);
        }
    }

    /**
     * List top edits by this user for all pages in a particular namespace.
     * @param LabsHelper $lh
     * @param string $username
     * @param string $project
     * @param integer $namespace
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function namespaceTopEdits($lh, $username, $project, $namespace)
    {
        // Get the basic data about the pages edited by this user.
        $query = "SELECT page_namespace, page_title, page_is_redirect, COUNT(page_title) AS count
                FROM ".$lh->getTable('page')." JOIN ".$lh->getTable('revision_userindex')." ON page_id = rev_page
                WHERE rev_user_text = :username AND page_namespace = :namespace
                GROUP BY page_namespace, page_title
                ORDER BY count DESC
                LIMIT 100";
        $params = ['username'=>$username, 'namespace'=> $namespace];
        $editData = $lh->client->executeQuery($query, $params)->fetchAll();

        // Get page info about these 100 pages, so we can use their display title.
        $titles = array_map(function ($e) {
            return $e['page_title'];
        }, $editData);
        /** @var ApiHelper $apiHelper */
        $apiHelper = $this->get("app.api_helper");
        $displayTitles = $apiHelper->displayTitles($project, $titles);

        // Put all together, and return the view.
        $edits = [];
        foreach ($editData as $editDatum) {
            $pageTitle = $editDatum['page_title'];
            $editDatum['displaytitle'] = $displayTitles[$pageTitle];
            $edits[] = $editDatum;
        }
        return $this->render('topedits/result_namespace.html.twig', array(
            "xtPageTitle" => "tool_topedits",
            "xtSubtitle" => "tool_topedits_desc",
            'xtPage' => "topedits",
            'project' => $project,
            'username' => $username,
            'namespace' => $namespace,
            'edits' => $edits,
        ));
    }

    /**
     * List top edits by this user for a particular page.
     * @param LabsHelper $lh
     * @param string $project
     * @param string $article
     * @param string $username
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    protected function singlePageTopEdits($lh, $project, $article, $username)
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
                FROM ".$lh->getTable('revision_userindex')." AS revs
                    LEFT JOIN ".$lh->getTable('revision_userindex')."
                    AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                WHERE revs.rev_user_text in (:username) AND revs.rev_page = :pageid
                ORDER BY revs.rev_timestamp DESC
            ";
        $params = ['username' => $username, 'pageid' => $pageInfo['pageid']];
        $revisionsData = $lh->client->executeQuery($query, $params)->fetchAll();

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
            $revision['timestamp'] = date('Y-m-d H:i', $time);
            $revision['year'] = date('Y', $time);
            $revision['month'] = date('m', $time);
            $revisions[] = $revision;
        }

        // Send all to the template.
        return $this->render('topedits/result_article.html.twig', array(
            "xtPageTitle" => "tool_topedits",
            "xtSubtitle" => "tool_topedits_desc",
            'xtPage' => "topedits",
            'project' => $project,
            'username' => $username,
            'article' => $pageInfo,
            'total_added' => $totalAdded,
            'total_removed' => $totalRemoved,
            'revisions' => $revisions,
            'revision_count' => count($revisions),
        ));
    }
}
