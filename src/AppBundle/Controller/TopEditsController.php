<?php
/**
 * This file contains only the TopEditsController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Xtools\TopEdits;
use Xtools\TopEditsRepository;

/**
 * The Top Edits tool.
 */
class TopEditsController extends XtoolsController
{
    /**
     * Get the name of the tool's index route.
     * This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute()
    {
        return 'TopEdits';
    }

    /**
     * TopEditsController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container)
    {
        $this->tooHighEditCountAction = $this->getIndexRoute();

        parent::__construct($requestStack, $container);
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
            return $this->redirectToRoute('TopEditsResult', $this->params);
        }

        return $this->render('topedits/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-topedits',
            'xtSubtitle' => 'tool-topedits-desc',
            'xtPage' => 'topedits',

            // Defaults that will get overriden if in $params.
            'namespace' => 0,
            'page' => '',
            'username' => '',
        ], $this->params, ['project' => $this->project]));
    }

    /**
     * Display the results.
     * @Route("/topedits/{project}/{username}/{namespace}/{page}", name="TopEditsResult",
     *     requirements = {"page"="|.+", "namespace" = "|all|\d+"},
     *     defaults = {"page" = "", "namespace" = "all"}
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction()
    {
        if (empty($this->page)) {
            return $this->namespaceTopEdits();
        } else {
            return $this->singlePageTopEdits();
        }
    }

    /**
     * List top edits by this user for all pages in a particular namespace.
     * @return Response
     * @codeCoverageIgnore
     */
    public function namespaceTopEdits()
    {
        // Make sure they've opted in to see this data.
        if (!$this->project->userHasOptedIn($this->user)) {
            $optedInPage = $this->project
                ->getRepository()
                ->getPage($this->project, $this->project->userOptInPage($this->user));

            return $this->render('topedits/result_namespace.html.twig', [
                'xtPage' => 'topedits',
                'xtTitle' => $this->user->getUsername(),
                'project' => $this->project,
                'user' => $this->user,
                'namespace' => $this->namespace,
                'opted_in_page' => $optedInPage,
                'is_sub_request' => $this->isSubRequest,
            ]);
        }

        /**
         * Max number of rows per namespace to show. `null` here will cause to
         * use the TopEdits default.
         * @var int
         */
        $limit = $this->isSubRequest ? 10 : null;

        $topEdits = new TopEdits($this->project, $this->user, null, $this->namespace, $limit);
        $topEditsRepo = new TopEditsRepository();
        $topEditsRepo->setContainer($this->container);
        $topEdits->setRepository($topEditsRepo);

        $topEdits->prepareData();

        $ret = [
            'xtPage' => 'topedits',
            'xtTitle' => $this->user->getUsername(),
            'project' => $this->project,
            'user' => $this->user,
            'namespace' => $this->namespace,
            'te' => $topEdits,
            'is_sub_request' => $this->isSubRequest,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse($this->request, 'topedits/result_namespace', $ret);
    }

    /**
     * List top edits by this user for a particular page.
     * @return Response
     * @codeCoverageIgnore
     */
    protected function singlePageTopEdits()
    {
        // FIXME: add pagination.
        $topEdits = new TopEdits($this->project, $this->user, $this->page);
        $topEditsRepo = new TopEditsRepository();
        $topEditsRepo->setContainer($this->container);
        $topEdits->setRepository($topEditsRepo);

        $topEdits->prepareData();

        // Send all to the template.
        return $this->render('topedits/result_article.html.twig', [
            'xtPage' => 'topedits',
            'xtTitle' => $this->user->getUsername() . ' - ' . $this->page->getTitle(),
            'project' => $this->project,
            'user' => $this->user,
            'page' => $this->page,
            'te' => $topEdits,
        ]);
    }

    /************************ API endpoints ************************/

    /**
     * Get the all edits of a user to a specific page, maximum 1000.
     * @Route("/api/user/topedits/{project}/{username}/{namespace}/{page}", name="UserApiTopEditsArticle",
     *     requirements = {"page"="|.+", "namespace"="|\d+|all"},
     *     defaults={"page"="", "namespace"="all"}
     * )
     * @Route("/api/user/top_edits/{project}/{username}/{namespace}/{page}", name="UserApiTopEditsArticleUnderscored",
     *     requirements={"page"="|.+", "namespace"="|\d+|all"},
     *     defaults={"page"="", "namespace"="all"}
     * )
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function topEditsUserApiAction()
    {
        $this->recordApiUsage('user/topedits');

        if (!$this->project->userHasOptedIn($this->user)) {
            return new JsonResponse(
                [
                    'error' => 'User:'.$this->user->getUsername().' has not opted in to detailed statistics.'
                ],
                Response::HTTP_FORBIDDEN
            );
        }

        $limit = isset($this->page) ? 1000 : 20;
        $topEdits = new TopEdits($this->project, $this->user, null, $this->namespace, $limit);
        $topEditsRepo = new TopEditsRepository();
        $topEditsRepo->setContainer($this->container);
        $topEdits->setRepository($topEditsRepo);

        if (isset($this->page)) {
            $topEdits->setPage($this->page);
            $topEdits->prepareData(false);
        } else {
            // Do format the results.
            $topEdits->prepareData();
        }

        return $this->getFormattedApiResponse([
            'top_edits' => $topEdits->getTopEdits(),
        ]);
    }
}
