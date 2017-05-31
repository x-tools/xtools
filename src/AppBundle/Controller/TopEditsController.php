<?php

namespace AppBundle\Controller;

use AppBundle\Helper\ApiHelper;
use AppBundle\Helper\LabsHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarDumper\VarDumper;
use Xtools\Page;
use Xtools\PagesRepository;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;

class TopEditsController extends Controller
{

    /** @var LabsHelper */
    private $lh;

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

        $projectName = $request->query->get('project');
        $username = $request->query->get('username');
        $namespace = $request->query->get('namespace');
        $article = $request->query->get('article');

        if ($projectName != "" && $username != "" && $namespace != "" && $article != "") {
            return $this->redirectToRoute("TopEditsResults", [
                'project'=>$projectName,
                'username' => $username,
                'namespace'=>$namespace,
                'article'=>$article,
            ]);
        } elseif ($projectName != "" && $username != "" && $namespace != "") {
            return $this->redirectToRoute("TopEditsResults", [
                'project'=>$projectName,
                'username' => $username,
                'namespace'=>$namespace,
            ]);
        } elseif ($projectName != "" && $username != "") {
            return $this->redirectToRoute("TopEditsResults", [
                'project' => $projectName,
                'username' => $username,
            ]);
        } elseif ($projectName != "") {
            return $this->redirectToRoute("TopEditsResults", [ 'project'=>$projectName ]);
        }

        // Set default project so we can populate the namespace selector.
        if (!$projectName) {
            $projectName = $this->container->getParameter('default_project');
        }

        $project = ProjectRepository::getProject($projectName, $this->container);

        return $this->render('topedits/index.html.twig', [
            'xtPageTitle' => 'tool-topedits',
            'xtSubtitle' => 'tool-topedits-desc',
            'xtPage' => 'topedits',
            'project' => $project,
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

        $project = ProjectRepository::getProject($project, $this->container);
        $user = new User($username);

        if ($article === "") {
            return $this->namespaceTopEdits($user, $project, $namespace);
        } else {
            return $this->singlePageTopEdits($user, $project, $namespace, $article);
        }
    }

    /**
     * List top edits by this user for all pages in a particular namespace.
     * @param User $user The User.
     * @param Project $project The project.
     * @param integer|string $namespaceId The namespace ID or 'all'
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function namespaceTopEdits(User $user, Project $project, $namespaceId)
    {
        // Get list of namespaces.
        $namespaces = $project->getNamespaces();

        // Get the basic data about the pages edited by this user.
        $params = ['username'=>$user->getUsername()];
        $nsClause = '';
        $namespaceMsg = 'all-namespaces';
        if (is_numeric($namespaceId)) {
            $nsClause = 'AND page_namespace = :namespace';
            $params['namespace'] = $namespaceId;
            $namespaceMsg = str_replace(' ', '_', strtolower($namespaces[$namespaceId]));
        }
        $revTable = $this->lh->getTable('revision', $project->getDatabaseName());
        $pageTable = $this->lh->getTable('page', $project->getDatabaseName());
        $query = "SELECT page_namespace, page_title, page_is_redirect, COUNT(page_title) AS count
                FROM $pageTable JOIN $revTable ON page_id = rev_page
                WHERE rev_user_text = :username $nsClause
                GROUP BY page_namespace, page_title
                ORDER BY count DESC
                LIMIT 100";
        $conn = $this->getDoctrine()->getManager('replicas')->getConnection();
        $editData = $conn->executeQuery($query, $params)->fetchAll();

        // Inform user if no revisions found.
        if (count($editData) === 0) {
            $this->addFlash("notice", ["nocontribs"]);
        }

        // Get page info about these 100 pages, so we can use their display title.
        $titles = array_map(function ($e) use ( $namespaces ) {
            // If non-mainspace, prepend namespace to the titles.
            $ns = $e['page_namespace'];
            $nsTitle = $ns > 0 ? $namespaces[$e['page_namespace']] . ':' : '';
            return $nsTitle . $e['page_title'];
        }, $editData);
        /** @var ApiHelper $apiHelper */
        $apiHelper = $this->get('app.api_helper');
        $displayTitles = $apiHelper->displayTitles($project->getDomain(), $titles);

        // Put all together, and return the view.
        $edits = [];
        foreach ($editData as $editDatum) {
            // If non-mainspace, prepend namespace to the titles.
            $ns = $editDatum['page_namespace'];
            $nsTitle = $ns > 0 ? $namespaces[$editDatum['page_namespace']] . ':' : '';
            $pageTitle = $nsTitle . $editDatum['page_title'];
            $editDatum['displaytitle'] = $displayTitles[$pageTitle];
            $editDatum['page_title'] = $pageTitle;
            $edits[] = $editDatum;
        }
        return $this->render('topedits/result_namespace.html.twig', [
            'xtPage' => 'topedits',
            'project' => $project,
            'user' => $user,
            'namespace' => $namespaceId,
            'edits' => $edits,
            'content_title' => $namespaceMsg,
        ]);
    }

    /**
     * List top edits by this user for a particular page.
     * @param User $user The user.
     * @param Project $project The project.
     * @param int $namespaceId The ID of the namespace of the page.
     * @param string $pageName The title (without namespace) of the page.
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    protected function singlePageTopEdits(User $user, Project $project, $namespaceId, $pageName)
    {
        // Get the full page name (i.e. no namespace prefix if NS 0).
        $namespaces = $project->getNamespaces();
        $fullPageName = $namespaceId ? $namespaces[$namespaceId].':'.$pageName : $pageName;
        $page = new Page($project, $fullPageName);
        $pageRepo = new PagesRepository();
        $page->setRepository($pageRepo);

        if (!$page->exists()) {
            // Redirect if the page doesn't exist.
            $this->addFlash("notice", ["no-result", $pageName]);
            //return $this->redirectToRoute("topedits");
        }

        // Get all revisions of this page by this user.
        $revTable = $this->lh->getTable('revision', $project->getDatabaseName());
        $query = "SELECT
                    revs.rev_id AS id,
                    revs.rev_timestamp AS timestamp,
                    (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                    revs.rev_comment AS comment
                FROM $revTable AS revs
                    LEFT JOIN $revTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                WHERE revs.rev_user_text in (:username) AND revs.rev_page = :pageid
                ORDER BY revs.rev_timestamp DESC
            ";
        $params = ['username' => $user->getUsername(), 'pageid' => $page->getId()];
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
            'user' => $user,
            'page' => $page,
            'total_added' => $totalAdded,
            'total_removed' => $totalRemoved,
            'revisions' => $revisions,
            'revision_count' => count($revisions),
        ]);
    }
}
