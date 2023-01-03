<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\I18nHelper;
use App\Model\EditSummary;
use App\Repository\EditSummaryRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller handles the Simple Edit Counter tool.
 */
class EditSummaryController extends XtoolsController
{
    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'EditSummary';
    }

    /**
     * The Edit Summary search form.
     * @Route("/editsummary", name="EditSummary")
     * @Route("/editsummary/index.php", name="EditSummaryIndexPhp")
     * @Route("/editsummary/{project}", name="EditSummaryProject")
     * @return Response
     */
    public function indexAction(): Response
    {
        // If we've got a project, user, and namespace, redirect to results.
        if (isset($this->params['project']) && isset($this->params['username'])) {
            return $this->redirectToRoute('EditSummaryResult', $this->params);
        }

        // Show the form.
        return $this->render('editSummary/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-editsummary',
            'xtSubtitle' => 'tool-editsummary-desc',
            'xtPage' => 'EditSummary',

            // Defaults that will get overridden if in $params.
            'username' => '',
            'namespace' => 0,
            'start' => '',
            'end' => '',
        ], $this->params, ['project' => $this->project]));
    }

    /**
     * Display the Edit Summary results
     * @Route(
     *     "/editsummary/{project}/{username}/{namespace}/{start}/{end}", name="EditSummaryResult",
     *     requirements={
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "namespace"="|all|\d+",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={"namespace"="all", "start"=false, "end"=false}
     * )
     * @param EditSummaryRepository $editSummaryRepo
     * @param I18nHelper $i18n
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(EditSummaryRepository $editSummaryRepo, I18nHelper $i18n): Response
    {
        // Instantiate an EditSummary, treating the past 150 edits as 'recent'.
        $editSummary = new EditSummary(
            $editSummaryRepo,
            $this->project,
            $this->user,
            $i18n,
            $this->namespace,
            $this->start,
            $this->end,
            150
        );
        $editSummary->prepareData();

        return $this->getFormattedResponse('editSummary/result', [
            'xtPage' => 'EditSummary',
            'xtTitle' => $this->user->getUsername(),
            'es' => $editSummary,
        ]);
    }

    /************************ API endpoints ************************/

    /**
     * Get basic stats on the edit summary usage of a user.
     * @Route(
     *     "/api/user/edit_summaries/{project}/{username}/{namespace}/{start}/{end}", name="UserApiEditSummaries",
     *     requirements={
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "namespace"="|all|\d+",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={"namespace"="all", "start"=false, "end"=false}
     * )
     * @param EditSummaryRepository $editSummaryRepo
     * @param I18nHelper $i18n
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function editSummariesApiAction(EditSummaryRepository $editSummaryRepo, I18nHelper $i18n): JsonResponse
    {
        $this->recordApiUsage('user/edit_summaries');

        // Instantiate an EditSummary, treating the past 150 edits as 'recent'.
        $editSummary = new EditSummary(
            $editSummaryRepo,
            $this->project,
            $this->user,
            $i18n,
            $this->namespace,
            $this->start,
            $this->end,
            150
        );
        $editSummary->prepareData();

        return $this->getFormattedApiResponse($editSummary->getData());
    }
}
