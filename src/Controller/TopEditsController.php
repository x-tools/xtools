<?php

declare( strict_types=1 );

namespace App\Controller;

use App\Helper\AutomatedEditsHelper;
use App\Model\Edit;
use App\Model\TopEdits;
use App\Repository\TopEditsRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The Top Edits tool.
 */
class TopEditsController extends XtoolsController {
	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getIndexRoute(): string {
		return 'TopEdits';
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function tooHighEditCountRoute(): string {
		return $this->getIndexRoute();
	}

	/**
	 * The Top Edits by page action is exempt from the edit count limitation.
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function tooHighEditCountActionAllowlist(): array {
		return [ 'singlePageTopEdits' ];
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function restrictedApiActions(): array {
		return [ 'namespaceTopEditsUserApi' ];
	}

	#[Route( '/topedits', name: 'topedits' )]
	#[Route( '/topedits', name: 'TopEdits' )]
	#[Route( '/topedits/index.php', name: 'TopEditsIndex' )]
	#[Route( '/topedits/{project}', name: 'TopEditsProject' )]
	/**
	 * Display the form.
	 */
	public function indexAction(): Response {
		// Redirect if at minimum project and username are provided.
		if ( isset( $this->params['project'] ) && isset( $this->params['username'] ) ) {
			if ( empty( $this->params['page'] ) ) {
				return $this->redirectToRoute( 'TopEditsResultNamespace', $this->params );
			}
			return $this->redirectToRoute( 'TopEditsResultPage', $this->params );
		}

		return $this->render( 'topedits/index.html.twig', array_merge( [
			'xtPageTitle' => 'tool-topedits',
			'xtSubtitle' => 'tool-topedits-desc',
			'xtPage' => 'TopEdits',

			// Defaults that will get overriden if in $params.
			'namespace' => 0,
			'page' => '',
			'username' => '',
			'start' => '',
			'end' => '',
		], $this->params, [ 'project' => $this->project ] ) );
	}

	/**
	 * Every action in this controller (other than 'index') calls this first.
	 * @param TopEditsRepository $topEditsRepo
	 * @param AutomatedEditsHelper $autoEditsHelper
	 * @return TopEdits
	 * @codeCoverageIgnore
	 */
	public function setUpTopEdits( TopEditsRepository $topEditsRepo, AutomatedEditsHelper $autoEditsHelper ): TopEdits {
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
			(int)$this->request->query->get( 'pagination', 0 )
		);
	}

	#[Route(
		'/topedits/{project}/{username}/{namespace}/{start}/{end}',
		name: 'TopEditsResultNamespace',
		requirements: [
			'username' => '(ipr-.+\/\d+[^\/])|([^\/]+)',
			'namespace' => '|all|\d+',
			'start' => '|\d{4}-\d{2}-\d{2}',
			'end' => '|\d{4}-\d{2}-\d{2}',
		],
		defaults: [ 'namespace' => 'all', 'start' => false, 'end' => false ]
	)]
	/**
	 * List top edits by this user for all pages in a particular namespace.
	 * @codeCoverageIgnore
	 */
	public function namespaceTopEditsAction(
		TopEditsRepository $topEditsRepo,
		AutomatedEditsHelper $autoEditsHelper
	): Response {
		// Max number of rows per namespace to show. `null` here will use the TopEdits default.
		$this->limit = $this->isSubRequest ? 10 : ( $this->params['limit'] ?? null );

		$topEdits = $this->setUpTopEdits( $topEditsRepo, $autoEditsHelper );
		$topEdits->prepareData();

		$ret = [
			'xtPage' => 'TopEdits',
			'xtTitle' => $this->user->getUsername(),
			'te' => $topEdits,
			'is_sub_request' => $this->isSubRequest,
		];

		// Output the relevant format template.
		return $this->getFormattedResponse( 'topedits/result_namespace', $ret );
	}

	#[Route(
		'/topedits/{project}/{username}/{namespace}/{page}/{start}/{end}',
		name: 'TopEditsResultPage',
		requirements: [
			'username' => '(ipr-.+\/\d+[^\/])|([^\/]+)',
			'namespace' => '|all|\d+',
			'page' => '(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$',
			'start' => '|\d{4}-\d{2}-\d{2}',
			'end' => '|\d{4}-\d{2}-\d{2}',
		],
		defaults: [ 'namespace' => 'all', 'start' => false, 'end' => false ]
	)]
	/**
	 * List top edits by this user for a particular page.
	 * @codeCoverageIgnore
	 * @todo Add pagination.
	 */
	public function singlePageTopEditsAction(
		TopEditsRepository $topEditsRepo,
		AutomatedEditsHelper $autoEditsHelper
	): Response {
		$topEdits = $this->setUpTopEdits( $topEditsRepo, $autoEditsHelper );
		$topEdits->prepareData();

		// Send all to the template.
		return $this->getFormattedResponse( 'topedits/result_page', [
			'xtPage' => 'TopEdits',
			'xtTitle' => $this->user->getUsername() . ' - ' . $this->page->getTitle(),
			'te' => $topEdits,
		] );
	}

	/************************ API endpoints */

	#[
		OA\Tag( name: "User API" ),
		OA\Get( description: "List the most-edited pages by a user in one or all namespaces." ),
		OA\Parameter( ref: "#/components/parameters/Project" ),
		OA\Parameter( ref: "#/components/parameters/UsernameOrIp" ),
		OA\Parameter( ref: "#/components/parameters/Namespace" ),
		OA\Parameter( ref: "#/components/parameters/Start" ),
		OA\Parameter( ref: "#/components/parameters/End" ),
		OA\Parameter( ref: "#/components/parameters/Pagination" ),
		OA\Response(
			response: 200,
			description: "Most-edited pages, keyed by namespace.",
			content: new OA\JsonContent(
				properties: [
					new OA\Property( property: "project", ref: "#/components/parameters/Project/schema" ),
					new OA\Property( property: "username", ref: "#/components/parameters/UsernameOrIp/schema" ),
					new OA\Property( property: "namespace", ref: "#/components/schemas/Namespace" ),
					new OA\Property( property: "start", ref: "#/components/parameters/Start/schema" ),
					new OA\Property( property: "end", ref: "#/components/parameters/End/schema" ),
					new OA\Property(
						property: "top_edits",
						properties: [
							new OA\Property( property: "namespace ID" ),
							new OA\Property( property: "namespace", ref: "#/components/schemas/Namespace" ),
							new OA\Property(
								property: "page_title", ref: "#/components/schemas/Page/properties/page_title"
							),
							new OA\Property(
								property: "full_page_title", ref: "#/components/schemas/Page/properties/full_page_title"
							),
							new OA\Property(
								property: "redirect", ref: "#/components/schemas/Page/properties/redirect"
							),
							new OA\Property( property: "count", type: "integer" ),
							new OA\Property( property: "assessment", ref: "#/components/schemas/PageAssessment" ),
						],
						type: "object"
					),
				]
			)
		),
		OA\Response( ref: "#/components/responses/404", response: 404 ),
		OA\Response( ref: "#/components/responses/501", response: 501 ),
		OA\Response( ref: "#/components/responses/503", response: 503 ),
		OA\Response( ref: "#/components/responses/504", response: 504 )
	]
	#[Route(
		'/api/user/top_edits/{project}/{username}/{namespace}/{start}/{end}',
		name: 'UserApiTopEditsNamespace',
		requirements: [
			'username' => '(ipr-.+\/\d+[^\/])|([^\/]+)',
			'namespace' => '|all|\d+',
			'start' => '|\d{4}-\d{2}-\d{2}',
			'end' => '|\d{4}-\d{2}-\d{2}',
		],
		defaults: [ 'namespace' => 'all', 'start' => false, 'end' => false ],
		methods: [ 'GET' ]
	)]
	/**
	 * Get the most-edited pages by a user.
	 * @codeCoverageIgnore
	 */
	public function namespaceTopEditsUserApiAction(
		TopEditsRepository $topEditsRepo,
		AutomatedEditsHelper $autoEditsHelper
	): JsonResponse {
		$this->recordApiUsage( 'user/topedits' );

		$topEdits = $this->setUpTopEdits( $topEditsRepo, $autoEditsHelper );
		$topEdits->prepareData();

		return $this->getFormattedApiResponse( [
			'top_edits' => (object)$topEdits->getTopEdits(),
		] );
	}

	#[OA\Tag( name: "User API" )]
	#[OA\Get( description: "Get all edits made by a user to a specific page." )]
	#[OA\Parameter( ref: "#/components/parameters/Project" )]
	#[OA\Parameter( ref: "#/components/parameters/UsernameOrIp" )]
	#[OA\Parameter( ref: "#/components/parameters/Namespace" )]
	#[OA\Parameter( ref: "#/components/parameters/PageWithoutNamespace" )]
	#[OA\Parameter( ref: "#/components/parameters/Start" )]
	#[OA\Parameter( ref: "#/components/parameters/End" )]
	#[OA\Parameter( ref: "#/components/parameters/Pagination" )]
	#[OA\Response(
		response: 200,
		description: "Edits to the page",
		content: new OA\JsonContent(
			properties: [
				new OA\Property( property: "project", ref: "#/components/parameters/Project/schema" ),
				new OA\Property( property: "username", ref: "#/components/parameters/UsernameOrIp/schema" ),
				new OA\Property( property: "namespace", ref: "#/components/schemas/Namespace" ),
				new OA\Property( property: "start", ref: "#/components/parameters/Start/schema" ),
				new OA\Property( property: "end", ref: "#/components/parameters/End/schema" ),
				new OA\Property(
					property: "top_edits",
					properties: [
						new OA\Property( property: "namespace ID" ),
						new OA\Property( property: "namespace", ref: "#/components/schemas/Namespace" ),
						new OA\Property(
							property: "page_title", ref: "#/components/schemas/Page/properties/page_title"
						),
						new OA\Property(
							property: "full_page_title", ref: "#/components/schemas/Page/properties/full_page_title"
						),
						new OA\Property( property: "redirect", ref: "#/components/schemas/Page/properties/redirect" ),
						new OA\Property( property: "count", type: "integer" ),
						new OA\Property( property: "assessment", ref: "#/components/schemas/PageAssessment" ),
					],
					type: "object"
				),
			]
		)
	)]
	#[OA\Response( ref: "#/components/responses/404", response: 404 )]
	#[OA\Response( ref: "#/components/responses/501", response: 501 )]
	#[OA\Response( ref: "#/components/responses/503", response: 503 )]
	#[OA\Response( ref: "#/components/responses/504", response: 504 )]
	#[Route(
		'/api/user/top_edits/{project}/{username}/{namespace}/{page}/{start}/{end}',
		name: 'UserApiTopEditsPage',
		requirements: [
			'username' => '(ipr-.+\/\d+[^\/])|([^\/]+)',
			'namespace' => '|all|\d+',
			'page' => '(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$',
			'start' => '|\d{4}-\d{2}-\d{2}',
			'end' => '|\d{4}-\d{2}-\d{2}',
		],
		defaults: [ 'namespace' => 'all', 'start' => false, 'end' => false ],
		methods: [ 'GET' ]
	)]
	/**
	 * Get the all edits made by a user to a specific page.
	 * @todo Add pagination.
	 * @codeCoverageIgnore
	 */
	public function singlePageTopEditsUserApiAction(
		TopEditsRepository $topEditsRepo,
		AutomatedEditsHelper $autoEditsHelper
	): JsonResponse {
		$this->recordApiUsage( 'user/topedits' );

		$topEdits = $this->setUpTopEdits( $topEditsRepo, $autoEditsHelper );
		$topEdits->prepareData();

		return $this->getFormattedApiResponse( [
			'top_edits' => array_map( static function ( Edit $edit ) {
				return $edit->getForJson();
			}, $topEdits->getTopEdits() ),
		] );
	}
}
