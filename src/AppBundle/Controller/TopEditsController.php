<?php
/**
 * This file contains only the TopEditsController class.
 */

namespace AppBundle\Controller;

use AppBundle\Helper\ApiHelper;
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
use Xtools\TopEdits;
use Xtools\TopEditsRepository;
use Xtools\Edit;

/**
 * The Top Edits tool.
 */
class TopEditsController extends Controller
{

    /**
     * Get the tool's shortname.
     * @return string
     */
    public function getToolShortname()
    {
        return 'topedits';
    }

    /**
     * Display the form.
     * @Route("/topedits", name="topedits")
     * @Route("/topedits", name="TopEdits")
     * @Route("/topedits/", name="topEditsSlash")
     * @Route("/topedits/index.php", name="TopEditsIndex")
     * @Route("/topedits/{project}", name="TopEditsProject")
     * @param Request $request
     * @param string $project The project name.
     * @return Response
     */
    public function indexAction(Request $request, $project = null)
    {
        $project = $request->query->get('project') ?: $project;
        $username = $request->query->get('username', $request->query->get('user'));
        $namespace = $request->query->get('namespace');
        $article = $request->query->get('article');

        // Legacy XTools.
        $user = $request->query->get('user');
        if (empty($username) && isset($user)) {
            $username = $user;
        }
        $page = $request->query->get('page');
        if (empty($article) && isset($page)) {
            $article = $page;
        }
        $wiki = $request->query->get('wiki');
        $lang = $request->query->get('lang');
        if (isset($wiki) && isset($lang) && empty($project)) {
            $project = $lang.'.'.$wiki.'.org';
        }

        $redirectParams = [
            'project' => $project,
            'username' => $username,
        ];
        if ($article != '') {
            $redirectParams['article'] = $article;
        }
        if ($namespace != '') {
            $redirectParams['namespace'] = $namespace;
        }

        // Redirect if at minimum project and username are provided.
        if ($project != '' && $username != '') {
            return $this->redirectToRoute('TopEditsResults', $redirectParams);
        }

        // Set default project so we can populate the namespace selector.
        if (!$project) {
            $project = $this->container->getParameter('default_project');
        }
        $project = ProjectRepository::getProject($project, $this->container);

        return $this->render('topedits/index.html.twig', [
            'xtPageTitle' => 'tool-topedits',
            'xtSubtitle' => 'tool-topedits-desc',
            'xtPage' => 'topedits',
            'project' => $project,
            'namespace' => (int) $namespace,
            'article' => $article,
        ]);
    }

    /**
     * Display the results.
     * @Route("/topedits/{project}/{username}/{namespace}/{article}", name="TopEditsResults",
     *     requirements={"article"=".+"})
     * @param Request $request The HTTP request.
     * @param string $project
     * @param string $username
     * @param int $namespace
     * @param string $article
     * @return RedirectResponse|Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request, $project, $username, $namespace = 0, $article = '')
    {
        $projectData = ProjectRepository::getProject($project, $this->container);

        if (!$projectData->exists()) {
            $this->addFlash('danger', ['invalid-project', $project]);
            return $this->redirectToRoute('topedits');
        }

        $user = UserRepository::getUser($username, $this->container);

        // Don't continue if the user doesn't exist.
        if (!$user->existsOnProject($projectData)) {
            $this->addFlash('danger', 'user-not-found');
            return $this->redirectToRoute('topedits', [
                'project' => $project,
                'namespace' => $namespace,
                'article' => $article,
            ]);
        }

        // Reject users with a crazy high edit count.
        if ($user->hasTooManyEdits($projectData)) {
            $this->addFlash('danger', ['too-many-edits', number_format($user->maxEdits())]);
            return $this->redirectToRoute('topedits', [
                'project' => $project,
                'namespace' => $namespace,
                'article' => $article,
            ]);
        }

        if ($article === '') {
            return $this->namespaceTopEdits($request, $user, $projectData, $namespace);
        } else {
            return $this->singlePageTopEdits($user, $projectData, $namespace, $article);
        }
    }

    /**
     * List top edits by this user for all pages in a particular namespace.
     * @param Request $request The HTTP request.
     * @param User $user The User.
     * @param Project $project The project.
     * @param integer|string $namespace The namespace ID or 'all'
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function namespaceTopEdits(Request $request, User $user, Project $project, $namespace)
    {
        $isSubRequest = $request->get('htmlonly')
            || $this->container->get('request_stack')->getParentRequest() !== null;

        // Make sure they've opted in to see this data.
        if (!$project->userHasOptedIn($user)) {
            $optedInPage = $project
                ->getRepository()
                ->getPage($project, $project->userOptInPage($user));

            return $this->render('topedits/result_namespace.html.twig', [
                'xtPage' => 'topedits',
                'xtTitle' => $user->getUsername(),
                'project' => $project,
                'user' => $user,
                'namespace' => $namespace,
                'edits' => [],
                'content_title' => '',
                'opted_in_page' => $optedInPage,
                'is_sub_request' => $isSubRequest,
            ]);
        }

        /**
         * Max number of rows per namespace to show. `null` here will cause to
         * use the TopEdits default.
         * @var int
         */
        $limit = $isSubRequest ? 10 : null;

        $topEdits = new TopEdits($project, $user, $namespace, $limit);
        $topEditsRepo = new TopEditsRepository();
        $topEditsRepo->setContainer($this->container);
        $topEdits->setRepository($topEditsRepo);

        return $this->render('topedits/result_namespace.html.twig', [
            'xtPage' => 'topedits',
            'xtTitle' => $user->getUsername(),
            'project' => $project,
            'user' => $user,
            'namespace' => $namespace,
            'te' => $topEdits,
            'is_sub_request' => $isSubRequest,
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
            $this->addFlash('notice', ['no-result', $pageName]);
            return $this->redirectToRoute('topedits');
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
            'xtTitle' => $user->getUsername() . ' - ' . $page->getTitle(),
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
