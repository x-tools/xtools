<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\I18nHelper;
use App\Model\AutoEdits;
use App\Repository\AutoEditsRepository;
use App\Repository\EditRepository;
use App\Repository\PageRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller serves the AutomatedEdits tool.
 */
class AutomatedEditsController extends XtoolsController
{
    protected AutoEdits $autoEdits;
    protected AutoEditsRepository $autoEditsRepo;
    protected EditRepository $editRepo;
    protected PageRepository $pageRepo;

    /** @var array Data that is passed to the view. */
    private array $output;

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'AutoEdits';
    }

    public function __construct(
        RequestStack $requestStack,
        ContainerInterface $container,
        CacheItemPoolInterface $cache,
        Client $guzzle,
        I18nHelper $i18n,
        ProjectRepository $projectRepo,
        UserRepository $userRepo,
        PageRepository $pageRepo,
        AutoEditsRepository $autoEditsRepo,
        EditRepository $editRepo
    ) {
        $this->autoEditsRepo = $autoEditsRepo;
        $this->editRepo = $editRepo;
        $this->pageRepo = $pageRepo;
        parent::__construct($requestStack, $container, $cache, $guzzle, $i18n, $projectRepo, $userRepo, $pageRepo);
    }

    /**
     * This causes the tool to redirect back to the index page, with an error,
     * if the user has too high of an edit count.
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function tooHighEditCountRoute(): string
    {
        return $this->getIndexRoute();
    }

    /**
     * Display the search form.
     * @Route("/autoedits", name="AutoEdits")
     * @Route("/automatededits", name="AutoEditsLong")
     * @Route("/autoedits/index.php", name="AutoEditsIndexPhp")
     * @Route("/automatededits/index.php", name="AutoEditsLongIndexPhp")
     * @Route("/autoedits/{project}", name="AutoEditsProject")
     * @return Response
     */
    public function indexAction(): Response
    {
        // Redirect if at minimum project and username are provided.
        if (isset($this->params['project']) && isset($this->params['username'])) {
            // If 'tool' param is given, redirect to corresponding action.
            $tool = $this->request->query->get('tool');

            if ('all' === $tool) {
                unset($this->params['tool']);
                return $this->redirectToRoute('AutoEditsContributionsResult', $this->params);
            } elseif ('' != $tool && 'none' !== $tool) {
                $this->params['tool'] = $tool;
                return $this->redirectToRoute('AutoEditsContributionsResult', $this->params);
            } elseif ('none' === $tool) {
                unset($this->params['tool']);
            }

            // Otherwise redirect to the normal result action.
            return $this->redirectToRoute('AutoEditsResult', $this->params);
        }

        return $this->render('autoEdits/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-autoedits',
            'xtSubtitle' => 'tool-autoedits-desc',
            'xtPage' => 'AutoEdits',

            // Defaults that will get overridden if in $this->params.
            'username' => '',
            'namespace' => 0,
            'start' => '',
            'end' => '',
        ], $this->params, ['project' => $this->project]));
    }

    /**
     * Set defaults, and instantiate the AutoEdits model. This is called at the top of every view action.
     * @codeCoverageIgnore
     */
    private function setupAutoEdits(): void
    {
        $tool = $this->request->query->get('tool', null);
        $useSandbox = (bool)$this->request->query->get('usesandbox', false);

        if ($useSandbox && !$this->request->getSession()->get('logged_in_user')) {
            $this->addFlashMessage('danger', 'auto-edits-logged-out');
            $useSandbox = false;
        }
        $this->autoEditsRepo->setUseSandbox($useSandbox);

        $misconfigured = $this->autoEditsRepo->getInvalidTools($this->project);
        $helpLink = "https://w.wiki/ppr";
        foreach ($misconfigured as $tool) {
            $this->addFlashMessage('warning', 'auto-edits-misconfiguration', [$tool, $helpLink]);
        }

        // Validate tool.
        // FIXME: instead of redirecting to index page, show result page listing all tools for that project,
        //  clickable to show edits by the user, etc.
        if ($tool && !isset($this->autoEditsRepo->getTools($this->project)[$tool])) {
            $this->throwXtoolsException(
                $this->getIndexRoute(),
                'auto-edits-unknown-tool',
                [$tool],
                'tool'
            );
        }

        $this->autoEdits = new AutoEdits(
            $this->autoEditsRepo,
            $this->editRepo,
            $this->pageRepo,
            $this->userRepo,
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end,
            $tool,
            $this->offset,
            $this->limit
        );

        $this->output = [
            'xtPage' => 'AutoEdits',
            'xtTitle' => $this->user->getUsername(),
            'ae' => $this->autoEdits,
            'is_sub_request' => $this->isSubRequest,
        ];
    }

    /**
     * Display the results.
     * @Route(
     *     "/autoedits/{project}/{username}/{namespace}/{start}/{end}/{offset}", name="AutoEditsResult",
     *     requirements={
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "namespace"="|all|\d+",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *         "offset"="|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}",
     *     },
     *     defaults={"namespace"=0, "start"=false, "end"=false, "offset"=false}
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(): Response
    {
        // Will redirect back to index if the user has too high of an edit count.
        $this->setupAutoEdits();

        if (in_array('bot', $this->user->getUserRights($this->project))) {
            $this->addFlashMessage('warning', 'auto-edits-bot');
        }

        return $this->getFormattedResponse('autoEdits/result', $this->output);
    }

    /**
     * Get non-automated edits for the given user.
     * @Route(
     *   "/nonautoedits-contributions/{project}/{username}/{namespace}/{start}/{end}/{offset}",
     *   name="NonAutoEditsContributionsResult",
     *   requirements={
     *       "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *       "namespace"="|all|\d+",
     *       "start"="|\d{4}-\d{2}-\d{2}",
     *       "end"="|\d{4}-\d{2}-\d{2}",
     *       "offset"="|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}",
     *   },
     *   defaults={"namespace"=0, "start"=false, "end"=false, "offset"=false}
     * )
     * @return Response|RedirectResponse
     * @codeCoverageIgnore
     */
    public function nonAutomatedEditsAction(): Response
    {
        $this->setupAutoEdits();

        return $this->getFormattedResponse('autoEdits/nonautomated_edits', $this->output);
    }

    /**
     * Get automated edits for the given user using the given tool.
     * @Route(
     *   "/autoedits-contributions/{project}/{username}/{namespace}/{start}/{end}/{offset}",
     *   name="AutoEditsContributionsResult",
     *   requirements={
     *       "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *       "namespace"="|all|\d+",
     *       "start"="|\d{4}-\d{2}-\d{2}",
     *       "end"="|\d{4}-\d{2}-\d{2}",
     *       "offset"="|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}",
     *   },
     *   defaults={"namespace"=0, "start"=false, "end"=false, "offset"=false}
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function automatedEditsAction(): Response
    {
        $this->setupAutoEdits();

        return $this->getFormattedResponse('autoEdits/automated_edits', $this->output);
    }

    /************************ API endpoints ************************/

    /**
     * Get a list of the automated tools and their regex/tags/etc.
     * @Route("/api/user/automated_tools/{project}", name="UserApiAutoEditsTools")
     * @Route("/api/project/automated_tools/{project}", name="ProjectApiAutoEditsTools")
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function automatedToolsApiAction(): JsonResponse
    {
        $this->recordApiUsage('user/automated_tools');
        return $this->getFormattedApiResponse($this->autoEditsRepo->getTools($this->project));
    }

    /**
     * Count the number of automated edits the given user has made.
     * @Route(
     *   "/api/user/automated_editcount/{project}/{username}/{namespace}/{start}/{end}/{tools}",
     *   name="UserApiAutoEditsCount",
     *   requirements={
     *       "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *       "namespace"="|all|\d+",
     *       "start"="|\d{4}-\d{2}-\d{2}",
     *       "end"="|\d{4}-\d{2}-\d{2}"
     *   },
     *   defaults={"namespace"="all", "start"=false, "end"=false, "tools"=false}
     * )
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function automatedEditCountApiAction(): JsonResponse
    {
        $this->recordApiUsage('user/automated_editcount');

        $this->setupAutoEdits();

        $ret = [
            'total_editcount' => $this->autoEdits->getEditCount(),
            'automated_editcount' => $this->autoEdits->getAutomatedCount(),
        ];
        $ret['nonautomated_editcount'] = $ret['total_editcount'] - $ret['automated_editcount'];

        if (isset($this->params['tools'])) {
            $tools = $this->autoEdits->getToolCounts();
            $ret['automated_tools'] = $tools;
        }

        return $this->getFormattedApiResponse($ret);
    }

    /**
     * Get non-automated edits for the given user.
     * @Route(
     *   "/api/user/nonautomated_edits/{project}/{username}/{namespace}/{start}/{end}/{offset}",
     *   name="UserApiNonAutoEdits",
     *   requirements={
     *       "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *       "namespace"="|all|\d+",
     *       "start"="|\d{4}-\d{2}-\d{2}",
     *       "end"="|\d{4}-\d{2}-\d{2}",
     *       "offset"="|\d{4}-?\d{2}-?\d{2}T?\d{2}:?\d{2}:?\d{2}"
     *   },
     *   defaults={"namespace"=0, "start"=false, "end"=false, "offset"=false, "limit"=50}
     * )
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function nonAutomatedEditsApiAction(): JsonResponse
    {
        $this->recordApiUsage('user/nonautomated_edits');

        $this->setupAutoEdits();

        $out = $this->addFullPageTitlesAndContinue(
            'nonautomated_edits',
            [],
            $this->autoEdits->getNonAutomatedEdits(true)
        );

        return $this->getFormattedApiResponse($out);
    }

    /**
     * Get (semi-)automated edits for the given user, optionally using the given tool.
     * @Route(
     *   "/api/user/automated_edits/{project}/{username}/{namespace}/{start}/{end}/{offset}",
     *   name="UserApiAutoEdits",
     *   requirements={
     *       "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *       "namespace"="|all|\d+",
     *       "start"="|\d{4}-\d{2}-\d{2}",
     *       "end"="|\d{4}-\d{2}-\d{2}",
     *       "offset"="|\d{4}-?\d{2}-?\d{2}T?\d{2}:?\d{2}:?\d{2}",
     *   },
     *   defaults={"namespace"=0, "start"=false, "end"=false, "offset"=false, "limit"=50}
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function automatedEditsApiAction(): Response
    {
        $this->recordApiUsage('user/automated_edits');

        $this->setupAutoEdits();

        $extras = $this->autoEdits->getTool()
            ? ['tool' => $this->autoEdits->getTool()]
            : [];

        $out = $this->addFullPageTitlesAndContinue(
            'automated_edits',
            $extras,
            $this->autoEdits->getAutomatedEdits(true)
        );

        return $this->getFormattedApiResponse($out);
    }
}
