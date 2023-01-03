<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\AutomatedEditsHelper;
use App\Helper\I18nHelper;
use App\Model\TopEdits;
use App\Repository\PageRepository;
use App\Repository\ProjectRepository;
use App\Repository\TopEditsRepository;
use App\Repository\UserRepository;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The Top Edits tool.
 */
class TopEditsController extends XtoolsController
{
    protected AutomatedEditsHelper $autoEditsHelper;
    protected TopEditsRepository $topEditsRepo;

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'TopEdits';
    }

    /**
     * TopEditsController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     * @param CacheItemPoolInterface $cache
     * @param Client $guzzle
     * @param I18nHelper $i18n
     * @param ProjectRepository $projectRepo
     * @param UserRepository $userRepo
     * @param PageRepository $pageRepo
     * @param TopEditsRepository $topEditsRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     */
    public function __construct(
        RequestStack $requestStack,
        ContainerInterface $container,
        CacheItemPoolInterface $cache,
        Client $guzzle,
        I18nHelper $i18n,
        ProjectRepository $projectRepo,
        UserRepository $userRepo,
        PageRepository $pageRepo,
        TopEditsRepository $topEditsRepo,
        AutomatedEditsHelper $autoEditsHelper
    ) {
        $this->topEditsRepo = $topEditsRepo;
        $this->autoEditsHelper = $autoEditsHelper;
        $this->limit = 1000;
        parent::__construct($requestStack, $container, $cache, $guzzle, $i18n, $projectRepo, $userRepo, $pageRepo);
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function tooHighEditCountRoute(): string
    {
        return $this->getIndexRoute();
    }

    /**
     * The Top Edits by page action is exempt from the edit count limitation.
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function tooHighEditCountActionAllowlist(): array
    {
        return ['singlePageTopEdits'];
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function restrictedApiActions(): array
    {
        return ['namespaceTopEditsUserApi'];
    }

    /**
     * Display the form.
     * @Route("/topedits", name="topedits")
     * @Route("/topedits", name="TopEdits")
     * @Route("/topedits/index.php", name="TopEditsIndex")
     * @Route("/topedits/{project}", name="TopEditsProject")
     * @return Response
     */
    public function indexAction(): Response
    {
        // Redirect if at minimum project and username are provided.
        if (isset($this->params['project']) && isset($this->params['username'])) {
            if (empty($this->params['page'])) {
                return $this->redirectToRoute('TopEditsResultNamespace', $this->params);
            }
            return $this->redirectToRoute('TopEditsResultPage', $this->params);
        }

        return $this->render('topedits/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-topedits',
            'xtSubtitle' => 'tool-topedits-desc',
            'xtPage' => 'TopEdits',

            // Defaults that will get overriden if in $params.
            'namespace' => 0,
            'page' => '',
            'username' => '',
            'start' => '',
            'end' => '',
        ], $this->params, ['project' => $this->project]));
    }

    /**
     * Every action in this controller (other than 'index') calls this first.
     * @return TopEdits
     * @codeCoverageIgnore
     */
    public function setUpTopEdits(): TopEdits
    {
        return new TopEdits(
            $this->topEditsRepo,
            $this->autoEditsHelper,
            $this->project,
            $this->user,
            $this->page,
            $this->namespace,
            $this->start,
            $this->end,
            $this->limit,
            (int)$this->request->query->get('pagination', 0)
        );
    }

    /**
     * List top edits by this user for all pages in a particular namespace.
     * @Route("/topedits/{project}/{username}/{namespace}/{start}/{end}",
     *     name="TopEditsResultNamespace",
     *     requirements={
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "namespace"="|all|\d+",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={"namespace" = "all", "start"=false, "end"=false}
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function namespaceTopEditsAction(): Response
    {
        // Max number of rows per namespace to show. `null` here will use the TopEdits default.
        $this->limit = $this->isSubRequest ? 10 : $this->limit;

        $topEdits = $this->setUpTopEdits();
        $topEdits->prepareData();

        $ret = [
            'xtPage' => 'TopEdits',
            'xtTitle' => $this->user->getUsername(),
            'te' => $topEdits,
            'is_sub_request' => $this->isSubRequest,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse('topedits/result_namespace', $ret);
    }

    /**
     * List top edits by this user for a particular page.
     * @Route("/topedits/{project}/{username}/{namespace}/{page}/{start}/{end}",
     *     name="TopEditsResultPage",
     *     requirements={
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "namespace"="|all|\d+",
     *         "page"="(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={"namespace"="all", "start"=false, "end"=false}
     * )
     * @todo Add pagination.
     * @return Response
     * @codeCoverageIgnore
     */
    public function singlePageTopEditsAction(): Response
    {
        $topEdits = $this->setUpTopEdits();
        $topEdits->prepareData();

        // Send all to the template.
        return $this->getFormattedResponse('topedits/result_article', [
            'xtPage' => 'TopEdits',
            'xtTitle' => $this->user->getUsername() . ' - ' . $this->page->getTitle(),
            'te' => $topEdits,
        ]);
    }

    /************************ API endpoints ************************/

    /**
     * List top edits by this user for all pages in a particular namespace.
     * @Route("/api/user/top_edits/{project}/{username}/{namespace}/{start}/{end}",
     *     name="UserApiTopEditsNamespace",
     *     requirements={
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "namespace"="|\d+|all",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={"namespace"="all", "start"=false, "end"=false}
     * )
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function namespaceTopEditsUserApiAction(): JsonResponse
    {
        $this->recordApiUsage('user/topedits');

        $topEdits = $this->setUpTopEdits();
        $topEdits->prepareData();

        return $this->getFormattedApiResponse([
            'top_edits' => (object)$topEdits->getTopEdits(),
        ]);
    }

    /**
     * Get the all edits of a user to a specific page, maximum 1000.
     * @Route("/api/user/top_edits/{project}/{username}/{namespace}/{page}/{start}/{end}",
     *     name="UserApiTopEditsPage",
     *     requirements = {
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "namespace"="|\d+|all",
     *         "page"="(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={"namespace"="all", "start"=false, "end"=false}
     * )
     * @todo Add pagination.
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function singlePageTopEditsUserApiAction(): JsonResponse
    {
        $this->recordApiUsage('user/topedits');

        $topEdits = $this->setUpTopEdits();
        $topEdits->prepareData(false);

        return $this->getFormattedApiResponse([
            'top_edits' => $topEdits->getTopEdits(),
        ]);
    }
}
