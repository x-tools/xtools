<?php
/**
 * This file contains only the TopEditsController class.
 */

namespace AppBundle\Controller;

use AppBundle\Helper\ApiHelper;
use AppBundle\Helper\LabsHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xtools\Page;
use Xtools\PagesRepository;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;
use Xtools\Edit;

/**
 * The Top Edits tool.
 */
class TopEditsController extends Controller
{

    /** @var LabsHelper The Labs helper, for WMF Labs installations. */
    private $lh;

    /**
     * Display the form.
     * @Route("/topedits", name="topedits")
     * @Route("/topedits", name="topEdits")
     * @Route("/topedits/", name="topEditsSlash")
     * @Route("/topedits/index.php", name="topEditsIndex")
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $this->lh = $this->get("app.labs_helper");
        $this->lh->checkEnabled("topedits");

        $projectName = $request->query->get('project');
        $username = $request->query->get('username', $request->query->get('user'));
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
     * Display the results.
     * @Route("/topedits/{project}/{username}/{namespace}/{article}", name="TopEditsResults",
     *     requirements={"article"=".+"})
     * @param string $project
     * @param string $username
     * @param int $namespace
     * @param string $article
     * @return RedirectResponse|Response
     */
    public function resultAction($project, $username, $namespace = 0, $article = "")
    {
        /** @var LabsHelper $lh */
        $this->lh = $this->get('app.labs_helper');
        $this->lh->checkEnabled('topedits');

        $projectData = ProjectRepository::getProject($project, $this->container);

        if (!$projectData->exists()) {
            $this->addFlash("notice", ["invalid-project", $project]);
            return $this->redirectToRoute("topedits");
        }

        $user = UserRepository::getUser($username, $this->container);

        if ($article === "") {
            return $this->namespaceTopEdits($user, $projectData, $namespace);
        } else {
            return $this->singlePageTopEdits($user, $projectData, $namespace, $article);
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
        // Make sure they've opted in to see this data.
        if (!$project->userHasOptedIn($user)) {
            return $this->render('topedits/result_namespace.html.twig', [
                'xtPage' => 'topedits',
                'project' => $project,
                'user' => $user,
                'namespace' => $namespaceId,
                'edits' => [],
                'content_title' => '',
            ]);
        }

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
            $this->addFlash("notice", ["no-contribs"]);
        }

        // Get page info about these 100 pages, so we can use their display title.
        $titles = array_map(function ($e) use ($namespaces) {
            // If non-mainspace, prepend namespace to the titles.
            $ns = $e['page_namespace'];
            $nsTitle = $ns > 0 ? $namespaces[$e['page_namespace']] . ':' : '';
            return $nsTitle . $e['page_title'];
        }, $editData);
        /** @var ApiHelper $apiHelper */
        $apiHelper = $this->get('app.api_helper');
        $displayTitles = $apiHelper->displayTitles($project->getDomain(), $titles);

        // Create page repo to be used in page objects
        $pageRepo = new PagesRepository();
        $pageRepo->setContainer($this->container);

        // Put all together, and return the view.
        $edits = [];
        foreach ($editData as $editDatum) {
            // If non-mainspace, prepend namespace to the titles.
            $ns = $editDatum['page_namespace'];
            $nsTitle = $ns > 0 ? $namespaces[$editDatum['page_namespace']] . ':' : '';
            $pageTitle = $nsTitle . $editDatum['page_title'];
            $editDatum['displaytitle'] = $displayTitles[$pageTitle];
            // $editDatum['page_title'] is retained without the namespace
            //  so we can link to TopEdits for that page
            $editDatum['page_title_ns'] = $pageTitle;
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
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    protected function singlePageTopEdits(User $user, Project $project, $namespaceId, $pageName)
    {
        // Get the full page name (i.e. no namespace prefix if NS 0).
        $namespaces = $project->getNamespaces();
        $fullPageName = $namespaceId ? $namespaces[$namespaceId].':'.$pageName : $pageName;
        $page = new Page($project, $fullPageName);
        $pageRepo = new PagesRepository();
        $pageRepo->setContainer($this->container);
        $page->setRepository($pageRepo);

        if (!$page->exists()) {
            // Redirect if the page doesn't exist.
            $this->addFlash("notice", ["no-result", $pageName]);
            return $this->redirectToRoute("topedits");
        }

        // Get all revisions of this page by this user.
        $revisionsData = $page->getRevisions($user);

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
            $revisions[] = new Edit($page, $revision);
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
