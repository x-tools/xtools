<?php

declare( strict_types=1 );

namespace App\Controller;

use App\Model\AutoEdits;
use App\Repository\AutoEditsRepository;
use App\Repository\EditRepository;
use DateTime;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller serves the AutomatedEdits tool.
 */
class AutomatedEditsController extends XtoolsController {
	protected AutoEdits $autoEdits;

	/** @var array Data that is passed to the view. */
	private array $output;

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getIndexRoute(): string {
		return 'AutoEdits';
	}

	/**
	 * This causes the tool to redirect back to the index page, with an error,
	 * if the user has too high of an edit count.
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function tooHighEditCountRoute(): string {
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
	public function indexAction(): Response {
		// Redirect if at minimum project and username are provided.
		if ( isset( $this->params['project'] ) && isset( $this->params['username'] ) ) {
			// If 'tool' param is given, redirect to corresponding action.
			$tool = $this->request->query->get( 'tool' );

			if ( 'all' === $tool ) {
				unset( $this->params['tool'] );
				return $this->redirectToRoute( 'AutoEditsContributionsResult', $this->params );
			} elseif ( '' != $tool && 'none' !== $tool ) {
				$this->params['tool'] = $tool;
				return $this->redirectToRoute( 'AutoEditsContributionsResult', $this->params );
			} elseif ( 'none' === $tool ) {
				unset( $this->params['tool'] );
			}

			// Otherwise redirect to the normal result action.
			return $this->redirectToRoute( 'AutoEditsResult', $this->params );
		}

		return $this->render( 'autoEdits/index.html.twig', array_merge( [
			'xtPageTitle' => 'tool-autoedits',
			'xtSubtitle' => 'tool-autoedits-desc',
			'xtPage' => 'AutoEdits',

			// Defaults that will get overridden if in $this->params.
			'username' => '',
			'namespace' => 0,
			'start' => '',
			'end' => '',
		], $this->params, [ 'project' => $this->project ] ) );
	}

	/**
	 * Set defaults, and instantiate the AutoEdits model. This is called at the top of every view action.
	 * @param AutoEditsRepository $autoEditsRepo
	 * @param EditRepository $editRepo
	 * @codeCoverageIgnore
	 */
	private function setupAutoEdits( AutoEditsRepository $autoEditsRepo, EditRepository $editRepo ): void {
		$tool = $this->request->query->get( 'tool', null );
		$useSandbox = (bool)$this->request->query->get( 'usesandbox', false );

		if ( $useSandbox && !$this->request->getSession()->get( 'logged_in_user' ) ) {
			$this->addFlashMessage( 'danger', 'auto-edits-logged-out' );
			$useSandbox = false;
		}
		$autoEditsRepo->setUseSandbox( $useSandbox );

		$misconfigured = $autoEditsRepo->getInvalidTools( $this->project );
		$helpLink = "https://w.wiki/ppr";
		foreach ( $misconfigured as $tool ) {
			$this->addFlashMessage( 'warning', 'auto-edits-misconfiguration', [ $tool, $helpLink ] );
		}

		// Validate tool.
		// FIXME: instead of redirecting to index page, show result page listing all tools for that project,
		//  clickable to show edits by the user, etc.
		if ( $tool && !isset( $autoEditsRepo->getTools( $this->project )[$tool] ) ) {
			$this->throwXtoolsException(
				$this->getIndexRoute(),
				'auto-edits-unknown-tool',
				[ $tool ],
				'tool'
			);
		}

		$this->autoEdits = new AutoEdits(
			$autoEditsRepo,
			$editRepo,
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

		if ( $useSandbox ) {
			$this->output['usesandbox'] = 1;
		}
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
	 *         "offset"="|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z?",
	 *     },
	 *     defaults={"namespace"=0, "start"=false, "end"=false, "offset"=false}
	 * )
	 * @param AutoEditsRepository $autoEditsRepo
	 * @param EditRepository $editRepo
	 * @return Response
	 * @codeCoverageIgnore
	 */
	public function resultAction( AutoEditsRepository $autoEditsRepo, EditRepository $editRepo ): Response {
		// Will redirect back to index if the user has too high of an edit count.
		$this->setupAutoEdits( $autoEditsRepo, $editRepo );

		if ( in_array( 'bot', $this->user->getUserRights( $this->project ) ) ) {
			$this->addFlashMessage( 'warning', 'auto-edits-bot' );
		}

		return $this->getFormattedResponse( 'autoEdits/result', $this->output );
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
	 *       "offset"="|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z?",
	 *   },
	 *   defaults={"namespace"=0, "start"=false, "end"=false, "offset"=false}
	 * )
	 * @param AutoEditsRepository $autoEditsRepo
	 * @param EditRepository $editRepo
	 * @return Response|RedirectResponse
	 * @codeCoverageIgnore
	 */
	public function nonAutomatedEditsAction( AutoEditsRepository $autoEditsRepo, EditRepository $editRepo ): Response {
		$this->setupAutoEdits( $autoEditsRepo, $editRepo );
		return $this->getFormattedResponse( 'autoEdits/nonautomated_edits', $this->output );
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
	 *       "offset"="|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z?",
	 *   },
	 *   defaults={"namespace"=0, "start"=false, "end"=false, "offset"=false}
	 * )
	 * @param AutoEditsRepository $autoEditsRepo
	 * @param EditRepository $editRepo
	 * @return Response
	 * @codeCoverageIgnore
	 */
	public function automatedEditsAction( AutoEditsRepository $autoEditsRepo, EditRepository $editRepo ): Response {
		$this->setupAutoEdits( $autoEditsRepo, $editRepo );

		return $this->getFormattedResponse( 'autoEdits/automated_edits', $this->output );
	}

	/************************ API endpoints */

	/**
	 * Get a list of the known automated tools for a project along with their regex/tags/etc.
	 * @Route("/api/project/automated_tools/{project}", name="ProjectApiAutoEditsTools", methods={"GET"})
	 * @OA\Tag(name="Project API")
	 * @OA\ExternalDocumentation(url="https://www.mediawiki.org/wiki/XTools/API/Project#Automated_tools")
	 * @OA\Parameter(ref="#/components/parameters/Project")
	 * @OA\Response(
	 *     response=200,
	 *     description="List of known (semi-)automated tools.",
	 * @OA\JsonContent(
	 * @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
	 * @OA\Property(property="tools", type="object", example={
	 *             "My tool": {
	 *                 "regex": "\\(using My tool",
	 *                 "link": "Project:My tool",
	 *                 "label": "MyTool",
	 *                 "namespaces": {0, 2, 4},
	 *                 "tags": {"mytool"}
	 *             }
	 *         }),
	 * @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
	 *     )
	 * )
	 * @OA\Response(response=404, ref="#/components/responses/404")
	 * @param AutoEditsRepository $autoEditsRepo
	 * @return JsonResponse
	 * @codeCoverageIgnore
	 */
	public function automatedToolsApiAction( AutoEditsRepository $autoEditsRepo ): JsonResponse {
		$this->recordApiUsage( 'user/automated_tools' );
		return $this->getFormattedApiResponse(
			[ 'tools' => $autoEditsRepo->getTools( $this->project ) ],
		);
	}

	/**
	 * Get the number of automated edits a user has made.
	 * @Route(
	 *   "/api/user/automated_editcount/{project}/{username}/{namespace}/{start}/{end}/{tools}",
	 *   name="UserApiAutoEditsCount",
	 *   requirements={
	 *       "username"="(ipr-.+\/\d+[^\/])|([^\/]+)",
	 *       "namespace"="|all|\d+",
	 *       "start"="|\d{4}-\d{2}-\d{2}",
	 *       "end"="|\d{4}-\d{2}-\d{2}"
	 *   },
	 *   defaults={"namespace"="all", "start"=false, "end"=false, "tools"=false},
	 *   methods={"GET"}
	 * )
	 * @OA\Tag(name="User API")
	 * @OA\Get(description="Get the number of edits a user has made using
	 * [known semi-automated tools](https://w.wiki/6oKQ), and optionally how many times each tool was used.")
	 * @OA\Parameter(ref="#/components/parameters/Project")
	 * @OA\Parameter(ref="#/components/parameters/UsernameOrIp")
	 * @OA\Parameter(ref="#/components/parameters/Namespace")
	 * @OA\Parameter(ref="#/components/parameters/Start")
	 * @OA\Parameter(ref="#/components/parameters/End")
	 * @OA\Parameter(ref="#/components/parameters/Tools")
	 * @OA\Response(
	 *     response=200,
	 *     description="Count of edits made using [known (semi-)automated tools](https://w.wiki/6oKQ).",
	 * @OA\JsonContent(
	 * @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
	 * @OA\Property(property="username", ref="#/components/parameters/Username/schema"),
	 * @OA\Property(property="namespace", ref="#/components/schemas/Namespace"),
	 * @OA\Property(property="start", ref="#/components/parameters/Start/schema"),
	 * @OA\Property(property="end", ref="#/components/parameters/End/schema"),
	 * @OA\Property(property="tools", ref="#/components/parameters/Tools/schema"),
	 * @OA\Property(property="total_editcount", type="integer"),
	 * @OA\Property(property="automated_editcount", type="integer"),
	 * @OA\Property(property="nonautomated_editcount", type="integer"),
	 * @OA\Property(property="automated_tools", ref="#/components/schemas/AutomatedTools"),
	 * @OA\Property(property="elapsed_time", type="float")
	 *     )
	 * )
	 * @OA\Response(response=404, ref="#/components/responses/404")
	 * @OA\Response(response=501, ref="#/components/responses/501")
	 * @OA\Response(response=503, ref="#/components/responses/503")
	 * @OA\Response(response=504, ref="#/components/responses/504")
	 * @param AutoEditsRepository $autoEditsRepo
	 * @param EditRepository $editRepo
	 * @return JsonResponse
	 * @codeCoverageIgnore
	 */
	public function automatedEditCountApiAction(
		AutoEditsRepository $autoEditsRepo,
		EditRepository $editRepo
	): JsonResponse {
		$this->recordApiUsage( 'user/automated_editcount' );

		$this->setupAutoEdits( $autoEditsRepo, $editRepo );

		$ret = [
			'total_editcount' => $this->autoEdits->getEditCount(),
			'automated_editcount' => $this->autoEdits->getAutomatedCount(),
		];
		$ret['nonautomated_editcount'] = $ret['total_editcount'] - $ret['automated_editcount'];

		if ( $this->getBoolVal( 'tools' ) ) {
			$tools = $this->autoEdits->getToolCounts();
			$ret['automated_tools'] = $tools;
		}

		return $this->getFormattedApiResponse( $ret );
	}

	/**
	 * Get non-automated contributions for a user.
	 * @Route(
	 *   "/api/user/nonautomated_edits/{project}/{username}/{namespace}/{start}/{end}/{offset}",
	 *   name="UserApiNonAutoEdits",
	 *   requirements={
	 *       "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
	 *       "namespace"="|all|\d+",
	 *       "start"="|\d{4}-\d{2}-\d{2}",
	 *       "end"="|\d{4}-\d{2}-\d{2}",
	 *       "offset"="|\d{4}-?\d{2}-?\d{2}T?\d{2}:?\d{2}:?\d{2}Z?"
	 *   },
	 *   defaults={"namespace"=0, "start"=false, "end"=false, "offset"=false, "limit"=50},
	 *   methods={"GET"}
	 * )
	 * @OA\Tag(name="User API")
	 * @OA\Get(description="Get a list of contributions a user has made without the use of any
	 * [known (semi-)automated tools](https://w.wiki/6oKQ). If more results are available than the `limit`, a
	 * `continue` property is returned with the value that can passed as the `offset` in another API request
	 * to paginate through the results.")
	 * @OA\Parameter(ref="#/components/parameters/Project")
	 * @OA\Parameter(ref="#/components/parameters/UsernameOrIp")
	 * @OA\Parameter(ref="#/components/parameters/Namespace")
	 * @OA\Parameter(ref="#/components/parameters/Start")
	 * @OA\Parameter(ref="#/components/parameters/End")
	 * @OA\Parameter(ref="#/components/parameters/Offset")
	 * @OA\Parameter(ref="#/components/parameters/LimitQuery")
	 * @OA\Response(
	 *     response=200,
	 *     description="List of contributions made without [known (semi-)automated tools](https://w.wiki/6oKQ).",
	 * @OA\JsonContent(
	 * @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
	 * @OA\Property(property="username", ref="#/components/parameters/UsernameOrIp/schema"),
	 * @OA\Property(property="namespace", ref="#/components/schemas/Namespace"),
	 * @OA\Property(property="start", ref="#/components/parameters/Start/schema"),
	 * @OA\Property(property="end", ref="#/components/parameters/End/schema"),
	 * @OA\Property(property="offset", ref="#/components/parameters/Offset/schema"),
	 * @OA\Property(property="limit", ref="#/components/parameters/Limit/schema"),
	 * @OA\Property(property="nonautomated_edits", type="array", @OA\Items(ref="#/components/schemas/Edit")),
	 * @OA\Property(property="continue", type="date-time", example="2020-01-31T12:59:59Z"),
	 * @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
	 *     )
	 * )
	 * @OA\Response(response=404, ref="#/components/responses/404")
	 * @OA\Response(response=501, ref="#/components/responses/501")
	 * @OA\Response(response=503, ref="#/components/responses/503")
	 * @OA\Response(response=504, ref="#/components/responses/504")
	 * @param AutoEditsRepository $autoEditsRepo
	 * @param EditRepository $editRepo
	 * @return JsonResponse
	 * @codeCoverageIgnore
	 */
	public function nonAutomatedEditsApiAction(
		AutoEditsRepository $autoEditsRepo,
		EditRepository $editRepo
	): JsonResponse {
		$this->recordApiUsage( 'user/nonautomated_edits' );

		$this->setupAutoEdits( $autoEditsRepo, $editRepo );

		$results = $this->autoEdits->getNonAutomatedEdits( true );
		$out = $this->addFullPageTitlesAndContinue( 'nonautomated_edits', [], $results );
		if ( count( $results ) === $this->limit ) {
			$out['continue'] = ( new DateTime( end( $results )['timestamp'] ) )->format( 'Y-m-d\TH:i:s\Z' );
		}

		return $this->getFormattedApiResponse( $out );
	}

	/**
	 * Get (semi-)automated contributions made by a user.
	 * @Route(
	 *   "/api/user/automated_edits/{project}/{username}/{namespace}/{start}/{end}/{offset}",
	 *   name="UserApiAutoEdits",
	 *   requirements={
	 *       "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
	 *       "namespace"="|all|\d+",
	 *       "start"="|\d{4}-\d{2}-\d{2}",
	 *       "end"="|\d{4}-\d{2}-\d{2}",
	 *       "offset"="|\d{4}-?\d{2}-?\d{2}T?\d{2}:?\d{2}:?\d{2}Z?",
	 *   },
	 *   defaults={"namespace"=0, "start"=false, "end"=false, "offset"=false, "limit"=50},
	 *   methods={"GET"}
	 * )
	 * @OA\Tag(name="User API")
	 * @OA\Get(description="Get a list of contributions a user has made using of any of the
	 * [known (semi-)automated tools](https://w.wiki/6oKQ). If more results are available than the `limit`, a
	 * `continue` property is returned with the value that can passed as the `offset` in another API request
	 * to paginate through the results.")
	 * @OA\Parameter(ref="#/components/parameters/Project")
	 * @OA\Parameter(ref="#/components/parameters/UsernameOrIp")
	 * @OA\Parameter(ref="#/components/parameters/Namespace")
	 * @OA\Parameter(ref="#/components/parameters/Start")
	 * @OA\Parameter(ref="#/components/parameters/End")
	 * @OA\Parameter(ref="#/components/parameters/Offset")
	 * @OA\Parameter(ref="#/components/parameters/LimitQuery")
	 * @OA\Parameter(name="tool", in="query", description="Get only contributions using this tool.
	 * Use the [automated tools](#/Project%20API/get_ProjectApiAutoEditsTools) endpoint to list available tools.")
	 * @OA\Response(
	 *     response=200,
	 *     description="List of contributions made using [known (semi-)automated tools](https://w.wiki/6oKQ).",
	 * @OA\JsonContent(
	 * @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
	 * @OA\Property(property="username", ref="#/components/parameters/UsernameOrIp/schema"),
	 * @OA\Property(property="namespace", ref="#/components/schemas/Namespace"),
	 * @OA\Property(property="start", ref="#/components/parameters/Start/schema"),
	 * @OA\Property(property="end", ref="#/components/parameters/End/schema"),
	 * @OA\Property(property="offset", ref="#/components/parameters/Offset/schema"),
	 * @OA\Property(property="limit", ref="#/components/parameters/Limit/schema"),
	 * @OA\Property(property="tool", type="string", example="Twinkle"),
	 * @OA\Property(property="automated_edits", type="array", @OA\Items(ref="#/components/schemas/Edit")),
	 * @OA\Property(property="continue", type="date-time", example="2020-01-31T12:59:59Z"),
	 * @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
	 *     )
	 * )
	 * @OA\Response(response=404, ref="#/components/responses/404")
	 * @OA\Response(response=501, ref="#/components/responses/501")
	 * @OA\Response(response=503, ref="#/components/responses/503")
	 * @OA\Response(response=504, ref="#/components/responses/504")
	 * @param AutoEditsRepository $autoEditsRepo
	 * @param EditRepository $editRepo
	 * @return Response
	 * @codeCoverageIgnore
	 */
	public function automatedEditsApiAction( AutoEditsRepository $autoEditsRepo, EditRepository $editRepo ): Response {
		$this->recordApiUsage( 'user/automated_edits' );

		$this->setupAutoEdits( $autoEditsRepo, $editRepo );

		$extras = $this->autoEdits->getTool()
			? [ 'tool' => $this->autoEdits->getTool() ]
			: [];

		$results = $this->autoEdits->getAutomatedEdits( true );
		$out = $this->addFullPageTitlesAndContinue( 'automated_edits', $extras, $results );
		if ( count( $results ) === $this->limit ) {
			$out['continue'] = ( new DateTime( end( $results )['timestamp'] ) )->format( 'Y-m-d\TH:i:s\Z' );
		}

		return $this->getFormattedApiResponse( $out );
	}
}
