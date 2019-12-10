<?php
/**
 * This file contains only the TopEditsController class.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Helper\I18nHelper;
use AppBundle\Model\TopEdits;
use AppBundle\Repository\TopEditsRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The Top Edits tool.
 */
class TopEditsController extends XtoolsController
{
    /**
     * Get the name of the tool's index route. This is also the name of the associated model.
     * @return string
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
     * @param I18nHelper $i18n
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container, I18nHelper $i18n)
    {
        $this->tooHighEditCountAction = $this->getIndexRoute();

        // The Top Edits by page action is exempt from the edit count limitation.
        $this->tooHighEditCountActionBlacklist = ['singlePageTopEdits'];

        $this->restrictedActions = ['namespaceTopEditsUserApi'];

        parent::__construct($requestStack, $container, $i18n);
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
        $topEdits = new TopEdits(
            $this->project,
            $this->user,
            $this->page,
            $this->namespace,
            $this->start,
            $this->end,
            $this->limit,
            $this->offset
        );

        $topEditsRepo = new TopEditsRepository();
        $topEditsRepo->setContainer($this->container);
        $topEdits->setRepository($topEditsRepo);

        return $topEdits;
    }

    /**
     * List top edits by this user for all pages in a particular namespace.
     * @Route("/topedits/{project}/{username}/{namespace}/{start}/{end}",
     *     name="TopEditsResultNamespace",
     *     requirements={
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
        /**
         * Max number of rows per namespace to show. `null` here will use the TopEdits default.
         * @var int
         */
        $this->limit = $this->isSubRequest ? 10 : $this->limit;

        $topEdits = $this->setUpTopEdits();
        $topEdits->prepareData();

        $ret = [
            'xtPage' => 'TopEdits',
            'xtTitle' => $this->user->getUsername(),
            'namespace' => $this->namespace,
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
     * @Route("/api/user/top_edits/{project}/{username}/{namespace}/{start}/{end}",
     *     name="UserApiTopEditsNamespace",
     *     requirements={
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
        $topEdits->prepareData(!isset($this->page));

        return $this->getFormattedApiResponse([
            'top_edits' => $topEdits->getTopEdits(),
        ]);
    }

    /**
     * Get the all edits of a user to a specific page, maximum 1000.
     * @Route("/api/user/top_edits/{project}/{username}/{namespace}/{page}/{start}/{end}",
     *     name="UserApiTopEditsPage",
     *     requirements = {
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
        $topEdits->prepareData(!isset($this->page));

        return $this->getFormattedApiResponse([
            'top_edits' => $topEdits->getTopEdits(),
        ]);
    }
}
