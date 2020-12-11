<?php
/**
 * This file contains only the AutomatedEditsController class.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Helper\I18nHelper;
use AppBundle\Model\AutoEdits;
use AppBundle\Repository\AutoEditsRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
    /** @var AutoEdits The AutoEdits instance. */
    protected $autoEdits;

    /** @var array Data that is passed to the view. */
    private $output;

    /**
     * Get the name of the tool's index route.
     * This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'AutoEdits';
    }

    /**
     * AutomatedEditsController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     * @param I18nHelper $i18n
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container, I18nHelper $i18n)
    {
        // This will cause the tool to redirect back to the index page, with an error,
        // if the user has too high of an edit count.
        $this->tooHighEditCountAction = $this->getIndexRoute();

        parent::__construct($requestStack, $container, $i18n);
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

        $autoEditsRepo = new AutoEditsRepository($useSandbox);
        $autoEditsRepo->setContainer($this->container);

        $misconfigured = $autoEditsRepo->getInvalidTools($this->project);
        foreach ($misconfigured as $tool) {
            $helpLink = "https://w.wiki/ppr";
            $this->addFlashMessage('warning', 'auto-edits-misconfiguration', [$tool, $helpLink]);
        }

        // Validate tool.
        // FIXME: instead of redirecting to index page, show result page listing all tools for that project,
        //  clickable to show edits by the user, etc.
        if ($tool && !isset($autoEditsRepo->getTools($this->project)[$tool])) {
            $this->throwXtoolsException(
                $this->getIndexRoute(),
                'auto-edits-unknown-tool',
                [$tool],
                'tool'
            );
        }

        $this->autoEdits = new AutoEdits(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end,
            $tool,
            $this->offset
        );
        $this->autoEdits->setRepository($autoEditsRepo);

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
     *     "/autoedits/{project}/{username}/{namespace}/{start}/{end}", name="AutoEditsResult",
     *     requirements={
     *         "namespace"="|all|\d+",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={"namespace"=0, "start"=false, "end"=false}
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
     *       "namespace"="|all|\d+",
     *       "start"="|\d{4}-\d{2}-\d{2}",
     *       "end"="|\d{4}-\d{2}-\d{2}",
     *       "offset"="\d*"
     *   },
     *   defaults={"namespace"=0, "start"=false, "end"=false, "offset"=0}
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
     *       "namespace"="|all|\d+",
     *       "start"="|\d{4}-\d{2}-\d{2}",
     *       "end"="|\d{4}-\d{2}-\d{2}",
     *       "offset"="\d*"
     *   },
     *   defaults={"namespace"=0, "start"=false, "end"=false, "offset"=0}
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

        $aeh = $this->container->get('app.automated_edits_helper');
        return $this->getFormattedApiResponse($aeh->getTools($this->project));
    }

    /**
     * Count the number of automated edits the given user has made.
     * @Route(
     *   "/api/user/automated_editcount/{project}/{username}/{namespace}/{start}/{end}/{tools}",
     *   name="UserApiAutoEditsCount",
     *   requirements={
     *       "namespace"="|all|\d+",
     *       "start"="|\d{4}-\d{2}-\d{2}",
     *       "end"="|\d{4}-\d{2}-\d{2}"
     *   },
     *   defaults={"namespace"="all", "start"=false, "end"=false}
     * )
     * @param string $tools Non-blank to show which tools were used and how many times.
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function automatedEditCountApiAction(string $tools = ''): JsonResponse
    {
        $this->recordApiUsage('user/automated_editcount');

        $this->setupAutoEdits();

        $ret = [
            'total_editcount' => $this->autoEdits->getEditCount(),
            'automated_editcount' => $this->autoEdits->getAutomatedCount(),
        ];
        $ret['nonautomated_editcount'] = $ret['total_editcount'] - $ret['automated_editcount'];

        if ('' != $tools) {
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
     *       "namespace"="|all|\d+",
     *       "start"="|\d{4}-\d{2}-\d{2}",
     *       "end"="|\d{4}-\d{2}-\d{2}",
     *       "offset"="\d*"
     *   },
     *   defaults={"namespace"=0, "start"=false, "end"=false, "offset"=0}
     * )
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function nonAutomatedEditsApiAction(): JsonResponse
    {
        $this->recordApiUsage('user/nonautomated_edits');

        $this->setupAutoEdits();

        $ret = array_map(function ($rev) {
            return array_merge([
                'full_page_title' => $this->getPageFromNsAndTitle(
                    (int)$rev['page_namespace'],
                    $rev['page_title'],
                    true
                ),
            ], $rev);
        }, $this->autoEdits->getNonAutomatedEdits(true));

        return $this->getFormattedApiResponse(['nonautomated_edits' => $ret]);
    }

    /**
     * Get (semi-)automated edits for the given user, optionally using the given tool.
     * @Route(
     *   "/api/user/automated_edits/{project}/{username}/{namespace}/{start}/{end}/{offset}",
     *   name="UserApiAutoEdits",
     *   requirements={
     *       "namespace"="|all|\d+",
     *       "start"="|\d{4}-\d{2}-\d{2}",
     *       "end"="|\d{4}-\d{2}-\d{2}",
     *       "offset"="\d*"
     *   },
     *   defaults={"namespace"=0, "start"=false, "end"=false, "offset"=0}
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function automatedEditsApiAction(): Response
    {
        $this->recordApiUsage('user/automated_edits');

        $this->setupAutoEdits();

        $ret = [];

        if ($this->autoEdits->getTool()) {
            $ret['tool'] = $this->autoEdits->getTool();
        }

        $ret['automated_edits'] = array_map(function ($rev) {
            return array_merge([
                'full_page_title' => $this->getPageFromNsAndTitle(
                    (int)$rev['page_namespace'],
                    $rev['page_title'],
                    true
                ),
            ], $rev);
        }, $this->autoEdits->getAutomatedEdits(true));

        return $this->getFormattedApiResponse($ret);
    }
}
