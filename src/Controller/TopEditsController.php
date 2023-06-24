<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\AutomatedEditsHelper;
use App\Model\TopEdits;
use App\Repository\TopEditsRepository;
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

        $this->addFlash(
            'warning',
            'This API endpoint will soon have a different response format. ' .
            'See https://w.wiki/6sMx for more information.'
        );

        $topEdits = $this->setUpTopEdits($topEditsRepo, $autoEditsHelper);
        $topEdits->prepareData(false);

        return $this->getFormattedApiResponse([
            'top_edits' => $topEdits->getTopEdits(),
        ]);
    }
}
