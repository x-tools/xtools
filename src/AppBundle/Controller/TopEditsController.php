<?php
/**
 * This file contains only the TopEditsController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Xtools\Project;
use Xtools\User;
use Xtools\TopEdits;
use Xtools\TopEditsRepository;
use Xtools\Edit;

/**
 * The Top Edits tool.
 */
class TopEditsController extends XtoolsController
{

    /**
     * Get the tool's shortname.
     * @return string
     * @codeCoverageIgnore
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
     * @return Response
     */
    public function indexAction()
    {
        // Redirect if at minimum project and username are provided.
        if (isset($this->params['project']) && isset($this->params['username'])) {
            return $this->redirectToRoute('TopEditsResults', $this->params);
        }

        // Convert the given project (or default project) into a Project instance.
        $this->params['project'] = $this->getProjectFromQuery($this->params);

        return $this->render('topedits/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-topedits',
            'xtSubtitle' => 'tool-topedits-desc',
            'xtPage' => 'topedits',

            // Defaults that will get overriden if in $this->params.
            'namespace' => 0,
            'article' => '',
        ], $this->params));
    }

    /**
     * Display the results.
     * @Route("/topedits/{project}/{username}/{namespace}/{article}", name="TopEditsResults",
     *     requirements={"article"=".+"})
     * @param int $namespace
     * @param string $article
     * @return RedirectResponse|Response
     * @codeCoverageIgnore
     */
    public function resultAction($namespace = 0, $article = '')
    {
        // Second parameter causes it return a Redirect to the index if the user has too many edits.
        // We only want to do this when looking at the user's overall edits, not just to a specific article.
        $ret = $this->validateProjectAndUser($article !== '' ?  null : 'topedits');
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($projectData, $user) = $ret;
        }

        if ($article === '') {
            return $this->namespaceTopEdits($user, $projectData, $namespace);
        } else {
            return $this->singlePageTopEdits($user, $projectData, $namespace, $article);
        }
    }

    /**
     * List top edits by this user for all pages in a particular namespace.
     * @param User $user The User.
     * @param Project $project The project.
     * @param integer|string $namespace The namespace ID or 'all'
     * @return \Symfony\Component\HttpFoundation\Response
     * @codeCoverageIgnore
     */
    public function namespaceTopEdits(User $user, Project $project, $namespace)
    {
        $isSubRequest = $this->request->get('htmlonly')
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

        $topEdits = new TopEdits($project, $user, null, $namespace, $limit);
        $topEditsRepo = new TopEditsRepository();
        $topEditsRepo->setContainer($this->container);
        $topEdits->setRepository($topEditsRepo);

        $topEdits->prepareData();

        $ret = [
            'xtPage' => 'topedits',
            'xtTitle' => $user->getUsername(),
            'project' => $project,
            'user' => $user,
            'namespace' => $namespace,
            'te' => $topEdits,
            'is_sub_request' => $isSubRequest,
        ];

        // Output the relevant format template.
        return $this->getFormattedReponse('topedits/result_namespace', $ret);
    }

    /**
     * List top edits by this user for a particular page.
     * @param User $user The user.
     * @param Project $project The project.
     * @param int $namespaceId The ID of the namespace of the page.
     * @param string $pageName The title (without namespace) of the page.
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @codeCoverageIgnore
     */
    protected function singlePageTopEdits(User $user, Project $project, $namespaceId, $pageName)
    {
        // Get the full page name (i.e. no namespace prefix if NS 0).
        $namespaces = $project->getNamespaces();
        $fullPageName = $namespaceId ? $namespaces[$namespaceId].':'.$pageName : $pageName;

        $page = $this->getAndValidatePage($project, $fullPageName);
        if (is_a($page, 'Symfony\Component\HttpFoundation\RedirectResponse')) {
            return $page;
        }

        // FIXME: add pagination.
        $topEdits = new TopEdits($project, $user, $page);
        $topEditsRepo = new TopEditsRepository();
        $topEditsRepo->setContainer($this->container);
        $topEdits->setRepository($topEditsRepo);

        $topEdits->prepareData();

        // Send all to the template.
        return $this->render('topedits/result_article.html.twig', [
            'xtPage' => 'topedits',
            'xtTitle' => $user->getUsername() . ' - ' . $page->getTitle(),
            'project' => $project,
            'user' => $user,
            'page' => $page,
            'te' => $topEdits,
        ]);
    }

    /************************ API endpoints ************************/

    /**
     * Get the all edits of a user to a specific page, maximum 1000.
     * @Route("/api/user/topedits/{project}/{username}/{namespace}/{article}", name="UserApiTopEditsArticle",
     *     requirements={"article"=".+", "namespace"="|\d+|all"})
     * @Route("/api/user/top_edits/{project}/{username}/{namespace}/{article}", name="UserApiTopEditsArticleUnderscored",
     *     requirements={"article"=".+", "namespace"="|\d+|all"})
     * @param int|string $namespace The ID of the namespace of the page, or 'all' for all namespaces.
     * @param string $article The title of the page. A full title can be used if the $namespace is blank.
     * @return Response
     * TopEdits and its Repo cannot be stubbed here :(
     * @codeCoverageIgnore
     */
    public function topEditsUserApiAction($namespace = 0, $article = '')
    {
        $this->recordApiUsage('user/topedits');

        // Second parameter causes it return a Redirect to the index if the user has too many edits.
        // We only want to do this when looking at the user's overall edits, not just to a specific article.
        $ret = $this->validateProjectAndUser($article !== '' ?  null : 'topedits');
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($project, $user) = $ret;
        }

        if (!$project->userHasOptedIn($user)) {
            return new JsonResponse(
                [
                    'error' => 'User:'.$user->getUsername().' has not opted in to detailed statistics.'
                ],
                Response::HTTP_FORBIDDEN
            );
        }

        $limit = $article === '' ? 100 : 1000;
        $topEdits = new TopEdits($project, $user, null, $namespace, $limit);
        $topEditsRepo = new TopEditsRepository();
        $topEditsRepo->setContainer($this->container);
        $topEdits->setRepository($topEditsRepo);

        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);

        if ($article === '') {
            // Do format the results.
            $topEdits->prepareData();
        } else {
            $namespaces = $project->getNamespaces();
            $fullPageName = is_numeric($namespace) ? $namespaces[$namespace].':'.$article : $article;

            $page = $this->getAndValidatePage($project, $fullPageName);
            if (is_a($page, 'Symfony\Component\HttpFoundation\RedirectResponse')) {
                $response->setData([
                    'error' => 'Page "'.$article.'" does not exist.',
                ]);
                $response->setStatusCode(Response::HTTP_NOT_FOUND);
                return $response;
            }

            $topEdits->setPage($page);
            $topEdits->prepareData(false);
        }

        $response->setData($topEdits->getTopEdits());
        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }
}
