<?php
/**
 * This file contains only the AutomatedEditsController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Xtools\AutoEdits;
use Xtools\AutoEditsRepository;

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
    public function getIndexRoute()
    {
        return 'AutoEdits';
    }

    /**
     * AutomatedEditsController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container)
    {
        // This will cause the tool to redirect back to the index page, with an error,
        // if the user has too high of an edit count.
        $this->tooHighEditCountAction = $this->getIndexRoute();

        parent::__construct($requestStack, $container);
    }

    /**
     * Display the search form.
     * @Route("/autoedits", name="AutoEdits")
     * @Route("/autoedits/", name="AutoEditsSlash")
     * @Route("/automatededits", name="AutoEditsLong")
     * @Route("/automatededits/", name="AutoEditsLongSlash")
     * @Route("/autoedits/index.php", name="AutoEditsIndexPhp")
     * @Route("/automatededits/index.php", name="AutoEditsLongIndexPhp")
     * @Route("/autoedits/{project}", name="AutoEditsProject")
     * @return Response
     */
    public function indexAction()
    {
        // Redirect if at minimum project and username are provided.
        if (isset($this->params['project']) && isset($this->params['username'])) {
            return $this->redirectToRoute('AutoEditsResult', $this->params);
        }

        return $this->render('autoEdits/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-autoedits',
            'xtSubtitle' => 'tool-autoedits-desc',
            'xtPage' => 'autoedits',

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
    private function setupAutoEdits()
    {
        $tool = $this->request->query->get('tool', null);

        $autoEditsRepo = new AutoEditsRepository();
        $autoEditsRepo->setContainer($this->container);

        // Validate tool.
        // FIXME: instead of redirecting to index page, show result page listing all tools for that project,
        //  clickable to show edits by the user, etc.
        if ($tool && !isset($autoEditsRepo->getTools($this->project)[$tool])) {
            $docUrl = $this->generateUrl('ProjectApiAutoEditsTools', ['project' => $this->params['project']]);
            $this->throwXtoolsException(
                $this->getIndexRoute(),
                "No known tool with name '$tool'. For available tools, use $docUrl",
                ['no-result', $tool],
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
            'xtPage' => 'autoedits',
            'xtTitle' => $this->user->getUsername(),
            'project' => $this->project,
            'user' => $this->user,
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
    public function resultAction()
    {
        // Will redirect back to index if the user has too high of an edit count.
        $this->setupAutoEdits();

        // Render the view with all variables set.
        return $this->render('autoEdits/result.html.twig', $this->output);
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
    public function nonAutomatedEditsAction()
    {
        $this->setupAutoEdits();

        return $this->render('autoEdits/nonautomated_edits.html.twig', $this->output);
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
     * @return Response|RedirectResponse
     * @codeCoverageIgnore
     */
    public function automatedEditsAction()
    {
        $this->setupAutoEdits();

        return $this->render('autoEdits/automated_edits.html.twig', $this->output);
    }

    /************************ API endpoints ************************/

    /**
     * Get a list of the automated tools and their regex/tags/etc.
     * @Route("/api/user/automated_tools/{project}", name="UserApiAutoEditsTools")
     * @Route("/api/project/automated_tools/{project}", name="ProjectApiAutoEditsTools")
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function automatedToolsApiAction()
    {
        $this->recordApiUsage('user/automated_tools');

        $aeh = $this->container->get('app.automated_edits_helper');
        return new JsonResponse($aeh->getTools($this->project));
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
    public function automatedEditCountApiAction($tools = '')
    {
        $this->recordApiUsage('user/automated_editcount');

        $this->setupAutoEdits();

        $ret = [
            'total_editcount' => $this->autoEdits->getEditCount(),
            'automated_editcount' => $this->autoEdits->getAutomatedCount(),
        ];
        $ret['nonautomated_editcount'] = $ret['total_editcount'] - $ret['automated_editcount'];

        if ($tools != '') {
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
    public function nonAutomatedEditsApiAction()
    {
        $this->recordApiUsage('user/nonautomated_edits');

        $this->setupAutoEdits();

        $ret = array_map(function ($rev) {
            return array_merge([
                'full_page_title' => $this->getPageFromNsAndTitle($rev['page_namespace'], $rev['page_title'], true),
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
    public function automatedEditsApiAction()
    {
        $this->recordApiUsage('user/automated_edits');

        $this->setupAutoEdits();

        $ret = [];

        if ($this->autoEdits->getTool()) {
            $ret['tool'] = $this->autoEdits->getTool();
        }

        $ret['automated_edits'] = array_map(function ($rev) {
            return array_merge([
                'full_page_title' => $this->getPageFromNsAndTitle($rev['page_namespace'], $rev['page_title'], true),
            ], $rev);
        }, $this->autoEdits->getAutomatedEdits(true));

        return $this->getFormattedApiResponse($ret);
    }
}
