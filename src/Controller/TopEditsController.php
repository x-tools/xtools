<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\AutomatedEditsHelper;
use App\Model\TopEdits;
use App\Repository\TopEditsRepository;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The Top Edits tool.
 */
class TopEditsController extends XtoolsController
{
    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'TopEdits';
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
     * @param TopEditsRepository $topEditsRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     * @return TopEdits
     * @codeCoverageIgnore
     */
    public function setUpTopEdits(TopEditsRepository $topEditsRepo, AutomatedEditsHelper $autoEditsHelper): TopEdits
    {
        return new TopEdits(
            $topEditsRepo,
            $autoEditsHelper,
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
     * @param TopEditsRepository $topEditsRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     * @return Response
     * @codeCoverageIgnore
     */
    public function namespaceTopEditsAction(
        TopEditsRepository $topEditsRepo,
        AutomatedEditsHelper $autoEditsHelper
    ): Response {
        // Max number of rows per namespace to show. `null` here will use the TopEdits default.
        $this->limit = $this->isSubRequest ? 10 : ($this->params['limit'] ?? null);

        $topEdits = $this->setUpTopEdits($topEditsRepo, $autoEditsHelper);
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
     * @param TopEditsRepository $topEditsRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     * @return Response
     * @codeCoverageIgnore
     * @todo Add pagination.
     */
    public function singlePageTopEditsAction(
        TopEditsRepository $topEditsRepo,
        AutomatedEditsHelper $autoEditsHelper
    ): Response {
        $topEdits = $this->setUpTopEdits($topEditsRepo, $autoEditsHelper);
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
     * Get the most-edited pages by a user.
     * @Route("/api/user/top_edits/{project}/{username}/{namespace}/{start}/{end}",
     *     name="UserApiTopEditsNamespace",
     *     requirements={
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "namespace"="|\d+|all",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={"namespace"="all", "start"=false, "end"=false},
     *     methods={"GET"}
     * )
     * @OA\Tag(name="User API")
     * @OA\Get(description="List the most-edited pages by a user in one or all namespaces.")
     * @OA\Parameter(ref="#/components/parameters/Project")
     * @OA\Parameter(ref="#/components/parameters/UsernameOrIp")
     * @OA\Parameter(ref="#/components/parameters/Namespace")
     * @OA\Parameter(ref="#/components/parameters/Start")
     * @OA\Parameter(ref="#/components/parameters/End")
     * @OA\Parameter(ref="#/components/parameters/Pagination")
     * @OA\Response(
     *     response=200,
     *     description="Most-edited pages, keyed by namespace.",
     *     @OA\JsonContent(
     *         @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
     *         @OA\Property(property="username", ref="#/components/parameters/UsernameOrIp/schema"),
     *         @OA\Property(property="namespace", ref="#/components/schemas/Namespace"),
     *         @OA\Property(property="start", ref="#/components/parameters/Start/schema"),
     *         @OA\Property(property="end", ref="#/components/parameters/End/schema"),
     *         @OA\Property(property="top_edits", type="object",
     *             @OA\Property(property="namespace ID",
     *                 @OA\Property(property="namespace", ref="#/components/schemas/Namespace"),
     *                 @OA\Property(property="page_title", ref="#/components/schemas/Page/properties/page_title"),
     *                 @OA\Property(property="full_page_title",
     *                     ref="#/components/schemas/Page/properties/full_page_title"),
     *                 @OA\Property(property="redirect", ref="#/components/schemas/Page/properties/redirect"),
     *                 @OA\Property(property="count", type="integer"),
     *                 @OA\Property(property="assessment", ref="#/components/schemas/PageAssessment")
     *             )
     *         )
     *     )
     * )
     * @OA\Response(response=404, ref="#/components/responses/404")
     * @OA\Response(response=501, ref="#/components/responses/501")
     * @OA\Response(response=503, ref="#/components/responses/503")
     * @OA\Response(response=504, ref="#/components/responses/504")
     * @param TopEditsRepository $topEditsRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function namespaceTopEditsUserApiAction(
        TopEditsRepository $topEditsRepo,
        AutomatedEditsHelper $autoEditsHelper
    ): JsonResponse {
        $this->recordApiUsage('user/topedits');

        $topEdits = $this->setUpTopEdits($topEditsRepo, $autoEditsHelper);
        $topEdits->prepareData();

        $this->addApiWarningAboutPageTitles();
        return $this->getFormattedApiResponse([
            'top_edits' => (object)$topEdits->getTopEdits(),
        ]);
    }

    /**
     * Get the all edits made by a user to a specific page.
     * @Route("/api/user/top_edits/{project}/{username}/{namespace}/{page}/{start}/{end}",
     *     name="UserApiTopEditsPage",
     *     requirements = {
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "namespace"="|\d+|all",
     *         "page"="(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={"namespace"="all", "start"=false, "end"=false},
     *     methods={"GET"}
     * )
     * @OA\Tag(name="User API")
     * @OA\Get(description="Get all edits made by a user to a specific page.")
     * @OA\Parameter(ref="#/components/parameters/Project")
     * @OA\Parameter(ref="#/components/parameters/UsernameOrIp")
     * @OA\Parameter(ref="#/components/parameters/Namespace")
     * @OA\Parameter(ref="#/components/parameters/PageWithoutNamespace")
     * @OA\Parameter(ref="#/components/parameters/Start")
     * @OA\Parameter(ref="#/components/parameters/End")
     * @OA\Parameter(ref="#/components/parameters/Pagination")
     * @OA\Response(
     *     response=200,
     *     description="Edits to the page",
     *     @OA\JsonContent(
     *         @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
     *         @OA\Property(property="username", ref="#/components/parameters/UsernameOrIp/schema"),
     *         @OA\Property(property="namespace", ref="#/components/schemas/Namespace"),
     *         @OA\Property(property="start", ref="#/components/parameters/Start/schema"),
     *         @OA\Property(property="end", ref="#/components/parameters/End/schema"),
     *         @OA\Property(property="top_edits", type="object",
     *             @OA\Property(property="namespace ID",
     *                 @OA\Property(property="namespace", ref="#/components/schemas/Namespace"),
     *                 @OA\Property(property="page_title", ref="#/components/schemas/Page/properties/page_title"),
     *                 @OA\Property(property="full_page_title",
     *                     ref="#/components/schemas/Page/properties/full_page_title"),
     *                 @OA\Property(property="redirect", ref="#/components/schemas/Page/properties/redirect"),
     *                 @OA\Property(property="count", type="integer"),
     *                 @OA\Property(property="assessment", ref="#/components/schemas/PageAssessment")
     *             )
     *         )
     *     )
     * )
     * @OA\Response(response=404, ref="#/components/responses/404")
     * @OA\Response(response=501, ref="#/components/responses/501")
     * @OA\Response(response=503, ref="#/components/responses/503")
     * @OA\Response(response=504, ref="#/components/responses/504")
     * @param TopEditsRepository $topEditsRepo
     * @param AutomatedEditsHelper $autoEditsHelper
     * @return JsonResponse
     * @codeCoverageIgnore
     * @todo Add pagination.
     */
    public function singlePageTopEditsUserApiAction(
        TopEditsRepository $topEditsRepo,
        AutomatedEditsHelper $autoEditsHelper
    ): JsonResponse {
        $this->recordApiUsage('user/topedits');

        $topEdits = $this->setUpTopEdits($topEditsRepo, $autoEditsHelper);
        $topEdits->prepareData(false);

        $this->addApiWarningAboutDates(['timestamp']);
        if (false !== strpos($this->page->getTitle(true), '_')) {
            $this->addFlash('warning', 'In XTools 3.20, the page property will be returned ' .
                'with spaces instead of underscores.');
        }
        return $this->getFormattedApiResponse([
            'top_edits' => $topEdits->getTopEdits(),
        ]);
    }
}
