<?php

declare( strict_types=1 );

namespace App\Controller;

use App\Model\EditSummary;
use App\Repository\EditSummaryRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller handles the Simple Edit Counter tool.
 */
class EditSummaryController extends XtoolsController {
	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getIndexRoute(): string {
		return 'EditSummary';
	}

	/**
	 * The Edit Summary search form.
	 * @Route("/editsummary", name="EditSummary")
	 * @Route("/editsummary/index.php", name="EditSummaryIndexPhp")
	 * @Route("/editsummary/{project}", name="EditSummaryProject")
	 * @return Response
	 */
	public function indexAction(): Response {
		// If we've got a project, user, and namespace, redirect to results.
		if ( isset( $this->params['project'] ) && isset( $this->params['username'] ) ) {
			return $this->redirectToRoute( 'EditSummaryResult', $this->params );
		}

		// Show the form.
		return $this->render( 'editSummary/index.html.twig', array_merge( [
			'xtPageTitle' => 'tool-editsummary',
			'xtSubtitle' => 'tool-editsummary-desc',
			'xtPage' => 'EditSummary',

			// Defaults that will get overridden if in $params.
			'username' => '',
			'namespace' => 0,
			'start' => '',
			'end' => '',
		], $this->params, [ 'project' => $this->project ] ) );
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
	 * @return Response
	 * @codeCoverageIgnore
	 */
	public function resultAction( EditSummaryRepository $editSummaryRepo ): Response {
		// Instantiate an EditSummary, treating the past 150 edits as 'recent'.
		$editSummary = new EditSummary(
			$editSummaryRepo,
			$this->project,
			$this->user,
			$this->namespace,
			$this->start,
			$this->end,
			150
		);
		$editSummary->prepareData();

		return $this->getFormattedResponse( 'editSummary/result', [
			'xtPage' => 'EditSummary',
			'xtTitle' => $this->user->getUsername(),
			'es' => $editSummary,
		] );
	}

	/************************ API endpoints */

	/**
	 * Get statistics on how many times a user has used edit summaries.
	 * @Route(
	 *     "/api/user/edit_summaries/{project}/{username}/{namespace}/{start}/{end}", name="UserApiEditSummaries",
	 *     requirements={
	 *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
	 *         "namespace"="|all|\d+",
	 *         "start"="|\d{4}-\d{2}-\d{2}",
	 *         "end"="|\d{4}-\d{2}-\d{2}",
	 *     },
	 *     defaults={"namespace"="all", "start"=false, "end"=false},
	 *     methods={"GET"}
	 * )
	 * @OA\Tag(name="User API")
	 * @OA\Get(description="Get edit summage usage statistics for the user, with a month-by-month breakdown.")
	 * @OA\Parameter(ref="#/components/parameters/Project")
	 * @OA\Parameter(ref="#/components/parameters/UsernameOrIp")
	 * @OA\Parameter(ref="#/components/parameters/Namespace")
	 * @OA\Parameter(ref="#/components/parameters/Start")
	 * @OA\Parameter(ref="#/components/parameters/End")
	 * @OA\Response(
	 *     response=200,
	 *     description="Edit summary usage statistics",
	 * @OA\JsonContent(
	 * @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
	 * @OA\Property(property="username", ref="#/components/parameters/UsernameOrIp/schema"),
	 * @OA\Property(property="namespace", ref="#/components/schemas/Namespace"),
	 * @OA\Property(property="start", ref="#/components/parameters/Start/schema"),
	 * @OA\Property(property="end", ref="#/components/parameters/End/schema"),
	 * @OA\Property(property="recent_edits_minor", type="integer",
	 *             description="Number of minor edits within the last 150 edits"),
	 * @OA\Property(property="recent_edits_major", type="integer",
	 *             description="Number of non-minor edits within the last 150 edits"),
	 * @OA\Property(property="total_edits_minor", type="integer",
	 *             description="Total number of minor edits"),
	 * @OA\Property(property="total_edits_major", type="integer",
	 *             description="Total number of non-minor edits"),
	 * @OA\Property(property="total_edits", type="integer", description="Total number of edits"),
	 * @OA\Property(property="recent_summaries_minor", type="integer",
	 *             description="Number of minor edits with summaries within the last 150 edits"),
	 * @OA\Property(property="recent_summaries_major", type="integer",
	 *             description="Number of non-minor edits with summaries within the last 150 edits"),
	 *     )
	 * )
	 * @OA\Response(response=404, ref="#/components/responses/404")
	 * @OA\Response(response=501, ref="#/components/responses/501")
	 * @OA\Response(response=503, ref="#/components/responses/503")
	 * @OA\Response(response=504, ref="#/components/responses/504")
	 * @param EditSummaryRepository $editSummaryRepo
	 * @return JsonResponse
	 * @codeCoverageIgnore
	 */
	public function editSummariesApiAction( EditSummaryRepository $editSummaryRepo ): JsonResponse {
		$this->recordApiUsage( 'user/edit_summaries' );

		// Instantiate an EditSummary, treating the past 150 edits as 'recent'.
		$editSummary = new EditSummary(
			$editSummaryRepo,
			$this->project,
			$this->user,
			$this->namespace,
			$this->start,
			$this->end,
			150
		);
		$editSummary->prepareData();

		return $this->getFormattedApiResponse( $editSummary->getData() );
	}
}
