<?php

declare( strict_types=1 );

namespace App\Controller;

use App\Exception\XtoolsHttpException;
use App\Helper\AutomatedEditsHelper;
use App\Model\Authorship;
use App\Model\Page;
use App\Model\PageInfo;
use App\Model\Project;
use App\Repository\PageInfoRepository;
use GuzzleHttp\Exception\ServerException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Markup;

/**
 * This controller serves the search form and results for the PageInfo tool
 */
class PageInfoController extends XtoolsController {
	protected PageInfo $pageInfo;

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getIndexRoute(): string {
		return 'PageInfo';
	}

	#[Route( '/pageinfo', name: 'PageInfo' )]
	#[Route( '/pageinfo/{project}', name: 'PageInfoProject' )]
	#[Route( '/articleinfo', name: 'PageInfoLegacy' )]
	#[Route( '/articleinfo/index.php', name: 'PageInfoLegacyPhp' )]
	/**
	 * The search form.
	 */
	public function indexAction(): Response {
		if ( isset( $this->params['project'] ) && isset( $this->params['page'] ) ) {
			return $this->redirectToRoute( 'PageInfoResult', $this->params );
		}

		return $this->render( 'pageInfo/index.html.twig', array_merge( [
			'xtPage' => 'PageInfo',
			'xtPageTitle' => 'tool-pageinfo',
			'xtSubtitle' => 'tool-pageinfo-desc',

			// Defaults that will get overridden if in $params.
			'start' => '',
			'end' => '',
			'page' => '',
		], $this->params, [ 'project' => $this->project ] ) );
	}

	/**
	 * Setup the PageInfo instance and its Repository.
	 * @param PageInfoRepository $pageInfoRepo
	 * @param AutomatedEditsHelper $autoEditsHelper
	 * @codeCoverageIgnore
	 */
	private function setupPageInfo(
		PageInfoRepository $pageInfoRepo,
		AutomatedEditsHelper $autoEditsHelper
	): void {
		if ( isset( $this->pageInfo ) ) {
			return;
		}

		$this->pageInfo = new PageInfo(
			$pageInfoRepo,
			$this->i18n,
			$autoEditsHelper,
			$this->page,
			$this->start,
			$this->end
		);
	}

	#[Route( '/pageinfo-gadget.js', name: 'PageInfoGadget' )]
	/**
	 * Generate PageInfo gadget script for use on-wiki. This automatically points the
	 * script to this installation's API.
	 *
	 * @link https://www.mediawiki.org/wiki/XTools/PageInfo_gadget
	 * @codeCoverageIgnore
	 */
	public function gadgetAction(): Response {
		$rendered = $this->renderView( 'pageInfo/pageinfo.js.twig' );
		$response = new Response( $rendered );
		$response->headers->set( 'Content-Type', 'text/javascript' );
		return $response;
	}

	#[Route(
		'/pageinfo/{project}/{page}/{start}/{end}',
		name: 'PageInfoResult',
		requirements: [
			'page' => '(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$',
			'start' => '|\d{4}-\d{2}-\d{2}',
			'end' => '|\d{4}-\d{2}-\d{2}',
		],
		defaults: [
			'start' => false,
			'end' => false,
		]
	)]
	#[Route(
		'/articleinfo/{project}/{page}/{start}/{end}',
		name: 'PageInfoResultLegacy',
		requirements: [
			'page' => '(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$',
			'start' => '|\d{4}-\d{2}-\d{2}',
			'end' => '|\d{4}-\d{2}-\d{2}',
		],
		defaults: [
			'start' => false,
			'end' => false,
		]
	)]
	/**
	 * Display the results in given date range.
	 * @codeCoverageIgnore
	 */
	public function resultAction(
		PageInfoRepository $pageInfoRepo,
		AutomatedEditsHelper $autoEditsHelper
	): Response {
		if ( !$this->isDateRangeValid( $this->page, $this->start, $this->end ) ) {
			$this->addFlashMessage( 'notice', 'date-range-outside-revisions' );

			return $this->redirectToRoute( 'PageInfo', [
				'project' => $this->request->get( 'project' ),
			] );
		}

		$this->setupPageInfo( $pageInfoRepo, $autoEditsHelper );
		$this->pageInfo->prepareData();

		$maxRevisions = $this->getParameter( 'app.max_page_revisions' );

		// Show message if we hit the max revisions.
		if ( $this->pageInfo->tooManyRevisions() ) {
			$this->addFlashMessage( 'notice', 'too-many-revisions', [
				$this->i18n->numberFormat( $maxRevisions ),
				$maxRevisions,
			] );
		}

		// For when there is very old data (2001 era) which may cause miscalculations.
		if ( $this->pageInfo->getFirstEdit()->getYear() < 2003 ) {
			$this->addFlashMessage( 'warning', 'old-page-notice' );
		}

		// When all username info has been hidden (see T303724).
		if ( $this->pageInfo->getNumEditors() === 0 ) {
			$this->addFlashMessage( 'warning', 'error-usernames-missing' );
		} elseif ( $this->pageInfo->numDeletedRevisions() ) {
			$link = new Markup(
				$this->renderView( 'flashes/deleted_data.html.twig', [
					'numRevs' => $this->pageInfo->numDeletedRevisions(),
				] ),
				'UTF-8'
			);
			$this->addFlashMessage(
				'warning',
				$link,
				[ $this->pageInfo->numDeletedRevisions(), $link ]
			);
		}

		$ret = [
			'xtPage' => 'PageInfo',
			'xtTitle' => $this->page->getTitle(),
			'project' => $this->project,
			'editorlimit' => (int)$this->request->query->get( 'editorlimit', 20 ),
			'botlimit' => $this->request->query->get( 'botlimit', 10 ),
			'pageviewsOffset' => 60,
			'ai' => $this->pageInfo,
			'showAuthorship' => Authorship::isSupportedPage( $this->page ) && $this->pageInfo->getNumEditors() > 0,
		];

		// Output the relevant format template.
		return $this->getFormattedResponse( 'pageInfo/result', $ret );
	}

	/**
	 * Check if there were any revisions of given page in given date range.
	 */
	private function isDateRangeValid( Page $page, false|int $start, false|int $end ): bool {
		return $page->getNumRevisions( null, $start, $end ) > 0;
	}

	/************************ API endpoints */

	#[OA\Get( description: "Get basic information about a page." )]
	#[OA\Tag( name: "Page API" )]
	#[OA\Parameter( ref: "#/components/parameters/Project" )]
	#[OA\Parameter( ref: "#/components/parameters/Page" )]
	#[OA\Parameter(
		name: "format",
		in: "query",
		schema: new OA\Schema(
			type: "string",
			default: "json",
			enum: [ "json", "html" ]
		)
	)]
	#[OA\Response(
		response: 200,
		description: "Basic information about the page.",
		content: new OA\JsonContent(
			properties: [
				new OA\Property( property: "project", ref: "#/components/parameters/Project/schema" ),
				new OA\Property( property: "page", ref: "#/components/parameters/Page/schema" ),
				new OA\Property( property: "watchers", type: "integer" ),
				new OA\Property( property: "pageviews", type: "integer" ),
				new OA\Property( property: "pageviews_offset", type: "integer" ),
				new OA\Property( property: "revisions", type: "integer" ),
				new OA\Property( property: "editors", type: "integer" ),
				new OA\Property( property: "minor_edits", type: "integer" ),
				new OA\Property( property: "creator", type: "string", example: "Jimbo Wales" ),
				new OA\Property( property: "creator_editcount", type: "integer" ),
				new OA\Property( property: "created_at", type: "date" ),
				new OA\Property( property: "created_rev_id", type: "integer" ),
				new OA\Property( property: "modified_at", type: "date" ),
				new OA\Property( property: "secs_since_last_edit", type: "integer" ),
				new OA\Property( property: "modified_rev_id", type: "integer" ),
				new OA\Property(
					property: "assessment",
					type: "object",
					example: [
						"value" => "FA",
						"color" => "#9CBDFF",
						"category" => "Category:FA-Class articles",
						"badge" => "https://upload.wikimedia.org/wikipedia/commons/b/bc/Featured_article_star.svg"
					]
				),
				new OA\Property( property: "elapsed_time", ref: "#/components/schemas/elapsed_time" ),
			]
		)
	)]
	#[OA\Response( ref: "#/components/responses/404", response: 404 )]
	#[OA\Response( ref: "#/components/responses/503", response: 503 )]
	#[OA\Response( ref: "#/components/responses/504", response: 504 )]
	#[Route(
		'/api/page/pageinfo/{project}/{page}',
		name: 'PageApiPageInfo',
		requirements: [ 'page' => '.+' ],
		methods: [ 'GET' ]
	)]
	#[Route(
		'/api/page/articleinfo/{project}/{page}',
		name: 'PageApiPageInfoLegacy',
		requirements: [ 'page' => '.+' ],
		methods: [ 'GET' ]
	)]
	/**
	 * Get basic information about a page.
	 * See also the [pageviews](https://w.wiki/6o9k) and [edit data](https://w.wiki/6o9m) REST APIs.
	 * @codeCoverageIgnore
	 */
	public function pageInfoApiAction(
		PageInfoRepository $pageInfoRepo,
		AutomatedEditsHelper $autoEditsHelper
	): Response|JsonResponse {
		$this->recordApiUsage( 'page/pageinfo' );

		$this->setupPageInfo( $pageInfoRepo, $autoEditsHelper );
		$data = [];

		try {
			$data = $this->pageInfo->getPageInfoApiData( $this->project, $this->page );
		} catch ( ServerException ) {
			// The Wikimedia action API can fail for any number of reasons. To our users
			// any ServerException means the data could not be fetched, so we capture it here
			// to avoid the flood of automated emails when the API goes down, etc.
			$data['error'] = $this->i18n->msg( 'api-error', [ $this->project->getDomain() ] );
		}

		if ( $this->request->query->get( 'format' ) === 'html' ) {
			return $this->getApiHtmlResponse( $this->project, $this->page, $data );
		}

		return $this->getFormattedApiResponse( $data );
	}

	/**
	 * Get the Response for the HTML output of the PageInfo API action.
	 * @param Project $project
	 * @param Page $page
	 * @param string[] $data The pre-fetched data.
	 * @return Response
	 * @codeCoverageIgnore
	 */
	private function getApiHtmlResponse( Project $project, Page $page, array $data ): Response {
		$response = $this->render( 'pageInfo/api.html.twig', [
			'project' => $project,
			'page' => $page,
			'data' => $data,
		] );

		// All /api routes by default respond with a JSON content type.
		$response->headers->set( 'Content-Type', 'text/html' );
		// T381941
		$response->setVary( [ 'Origin' ] );

		// This endpoint is hit constantly and user could be browsing the same page over
		// and over (popular noticeboard, for instance), so offload brief caching to browser.
		$response->setClientTtl( 350 );

		return $response;
	}

	#[OA\Tag( name: "Page API" )]
	#[OA\Get( description:
		"Get statistics about the [prose](https://en.wiktionary.org/wiki/prose) (characters, " .
		"word count, etc.) and referencing of a page. ([more info](https://w.wiki/6oAF))"
	)]
	#[OA\Parameter( ref: "#/components/parameters/Project" )]
	#[OA\Parameter( ref: "#/components/parameters/Page", schema: new OA\Schema( example: "Metallica" ) )]
	#[OA\Response(
		response: 200,
		description: "Prose stats",
		content: new OA\JsonContent(
			properties: [
				new OA\Property( property: "project", ref: "#/components/parameters/Project/schema" ),
				new OA\Property( property: "page", ref: "#/components/parameters/Page/schema" ),
				new OA\Property( property: "bytes", type: "integer" ),
				new OA\Property( property: "characters", type: "integer" ),
				new OA\Property( property: "words", type: "integer" ),
				new OA\Property( property: "references", type: "integer" ),
				new OA\Property( property: "unique_references", type: "integer" ),
				new OA\Property( property: "sections", type: "integer" ),
				new OA\Property( property: "elapsed_time", ref: "#/components/schemas/elapsed_time" ),
			]
		)
	)]
	#[OA\Response( ref: "#/components/responses/404", response: 404 )]
	#[Route(
		'/api/page/prose/{project}/{page}',
		name: 'PageApiProse',
		requirements: [ 'page' => '.+' ],
		methods: [ 'GET' ]
	)]
	/**
	 * Get prose statistics for the given page.
	 * @codeCoverageIgnore
	 */
	public function proseStatsApiAction(
		PageInfoRepository $pageInfoRepo,
		AutomatedEditsHelper $autoEditsHelper
	): JsonResponse {
		$responseCode = Response::HTTP_OK;
		$this->recordApiUsage( 'page/prose' );
		$this->setupPageInfo( $pageInfoRepo, $autoEditsHelper );
		$ret = $this->pageInfo->getProseStats();
		if ( $ret === null ) {
			$this->addFlashMessage( 'error', 'api-error-wikimedia' );
			$responseCode = Response::HTTP_BAD_GATEWAY;
			$ret = [];
		}
		return $this->getFormattedApiResponse( $ret, $responseCode );
	}

	#[OA\Tag( name: "Page API" )]
	#[OA\Get( description:
		"Get [assessment data](https://w.wiki/6oAM) of the given pages, including the overall quality " .
		"classifications, along with a list of the WikiProjects and their classifications and importance levels."
	)]
	#[OA\Parameter( ref: "#/components/parameters/Project" )]
	#[OA\Parameter( ref: "#/components/parameters/Pages" )]
	#[OA\Parameter(
		name: "classonly",
		description: "Return only the overall quality assessment instead of for each applicable WikiProject.",
		in: "query",
		schema: new OA\Schema( type: "boolean" )
	)]
	#[OA\Response(
		response: 200,
		description: "Assessment data",
		content: new OA\JsonContent(
			properties: [
				new OA\Property( property: "project", ref: "#/components/parameters/Project/schema" ),
				new OA\Property( property: "pages", properties: [
					new OA\Property( property: "Page title", type: "object" ),
					new OA\Property( property: "assessment", ref: "#/components/schemas/PageAssessment" ),
					new OA\Property(
						property: "wikiprojects",
						ref: "#/components/schemas/PageAssessmentWikiProject",
						type: "object"
					)
				], type: "object" ),
				new OA\Property( property: "elapsed_time", ref: "#/components/schemas/elapsed_time" ),
			]
		)
	)]
	#[OA\Response( ref: "#/components/responses/404", response: 404 )]
	#[Route(
		'/api/page/assessments/{project}/{pages}',
		name: 'PageApiAssessments',
		requirements: [ 'pages' => '.+' ],
		methods: [ 'GET' ]
	)]
	/**
	 * Get the page assessments of one or more pages, along with various related metadata.
	 * @codeCoverageIgnore
	 */
	public function assessmentsApiAction( string $pages ): JsonResponse {
		$this->recordApiUsage( 'page/assessments' );

		$pages = explode( '|', $pages );
		$out = [
			'pages' => [],
		];

		foreach ( $pages as $pageTitle ) {
			try {
				$page = $this->validatePage( $pageTitle );
				$assessments = $page->getProject()
					->getPageAssessments()
					->getAssessments( $page );

				$out['pages'][$page->getTitle()] = $this->getBoolVal( 'classonly' )
					? $assessments['assessment']
					: $assessments;
			} catch ( XtoolsHttpException $e ) {
				$out['pages'][$pageTitle] = false;
			}
		}

		return $this->getFormattedApiResponse( $out );
	}

	#[OA\Tag( name: "Page API" )]
	#[OA\Parameter( ref: "#/components/parameters/Project" )]
	#[OA\Parameter( ref: "#/components/parameters/Page" )]
	#[OA\Response(
		response: 200,
		description: "Counts of in and outgoing links, external links, and redirects.",
		content: new OA\JsonContent(
			properties: [
				new OA\Property( property: "project", ref: "#/components/parameters/Project/schema" ),
				new OA\Property( property: "page", ref: "#/components/parameters/Page/schema" ),
				new OA\Property( property: "links_ext_count", type: "integer" ),
				new OA\Property( property: "links_out_count", type: "integer" ),
				new OA\Property( property: "links_in_count", type: "integer" ),
				new OA\Property( property: "redirects_count", type: "integer" ),
				new OA\Property( property: "elapsed_time", ref: "#/components/schemas/elapsed_time" ),
			]
		)
	)]
	#[OA\Response( ref: "#/components/responses/404", response: 404 )]
	#[OA\Response( ref: "#/components/responses/503", response: 503 )]
	#[OA\Response( ref: "#/components/responses/504", response: 504 )]
	#[Route(
		'/api/page/links/{project}/{page}',
		name: 'PageApiLinks',
		requirements: [ 'page' => '.+' ],
		methods: [ 'GET' ]
	)]
	/**
	 * Get number of in and outgoing links, external links, and redirects to the given page.
	 * @codeCoverageIgnore
	 */
	public function linksApiAction(): JsonResponse {
		$this->recordApiUsage( 'page/links' );
		return $this->getFormattedApiResponse( $this->page->countLinksAndRedirects() );
	}

	#[OA\Tag( name: "Page API" )]
	#[OA\Parameter( ref: "#/components/parameters/Project" )]
	#[OA\Parameter( ref: "#/components/parameters/Page" )]
	#[OA\Parameter( ref: "#/components/parameters/Start" )]
	#[OA\Parameter( ref: "#/components/parameters/End" )]
	#[OA\Parameter( ref: "#/components/parameters/Limit" )]
	#[OA\Parameter(
		name: "nobots",
		description: "Exclude bots from the results.",
		in: "query",
		schema: new OA\Schema( type: "boolean" )
	)]
	#[OA\Response(
		response: 200,
		description: "List of the top editors, sorted by how many edits they've made to the page.",
		content: new OA\JsonContent(
			properties: [
				new OA\Property( property: "project", ref: "#/components/parameters/Project/schema" ),
				new OA\Property( property: "page", ref: "#/components/parameters/Page/schema" ),
				new OA\Property( property: "start", ref: "#/components/parameters/Start/schema" ),
				new OA\Property( property: "end", ref: "#/components/parameters/End/schema" ),
				new OA\Property( property: "limit", ref: "#/components/parameters/Limit/schema" ),
				new OA\Property(
					property: "top_editors",
					type: "array",
					items: new OA\Items( type: "object" ),
					example: [
						[
							"rank" => 1,
							"username" => "Jimbo Wales",
							"count" => 50,
							"minor" => 15,
							"first_edit" => [
								"id" => 12345,
								"timestamp" => "2020-01-01T12:59:59Z",
							],
							"last_edit" => [
								"id" => 54321,
								"timestamp" => "2020-01-20T12:59:59Z",
							],
						],
					]
				),
				new OA\Property( property: "elapsed_time", ref: "#/components/schemas/elapsed_time" ),
			]
		)
	)]
	#[OA\Response( ref: "#/components/responses/404", response: 404 )]
	#[OA\Response( ref: "#/components/responses/503", response: 503 )]
	#[OA\Response( ref: "#/components/responses/504", response: 504 )]
	#[Route(
		'/api/page/top_editors/{project}/{page}/{start}/{end}/{limit}',
		name: 'PageApiTopEditors',
		requirements: [
			'page' => '(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?(?:\/(\d+))?)?$',
			'start' => '|\d{4}-\d{2}-\d{2}',
			'end' => '|\d{4}-\d{2}-\d{2}',
			'limit' => '\d+',
		],
		defaults: [
			'start' => false,
			'end' => false,
			'limit' => 20,
		],
		methods: [ 'GET' ]
	)]
	/**
	 * Get the top editors (by number of edits) of a page.
	 * @codeCoverageIgnore
	 */
	public function topEditorsApiAction(
		PageInfoRepository $pageInfoRepo,
		AutomatedEditsHelper $autoEditsHelper
	): JsonResponse {
		$this->recordApiUsage( 'page/top_editors' );

		$this->setupPageInfo( $pageInfoRepo, $autoEditsHelper );
		$topEditors = $this->pageInfo->getTopEditorsByEditCount(
			(int)$this->limit,
			$this->getBoolVal( 'nobots' )
		);

		return $this->getFormattedApiResponse( [
			'top_editors' => $topEditors,
		] );
	}

	#[OA\Tag( name: "Page API" )]
	#[OA\Get( description:
		"List bots that have edited a page, with edit counts and whether the account is still in the `bot` user group."
	)]
	#[OA\Parameter( ref: "#/components/parameters/Project" )]
	#[OA\Parameter( ref: "#/components/parameters/Page" )]
	#[OA\Parameter( ref: "#/components/parameters/Start" )]
	#[OA\Parameter( ref: "#/components/parameters/End" )]
	#[OA\Response(
		response: 200,
		description: "List of bots",
		content: new OA\JsonContent(
			properties: [
				new OA\Property( property: "project", ref: "#/components/parameters/Project/schema" ),
				new OA\Property( property: "page", ref: "#/components/parameters/Page/schema" ),
				new OA\Property( property: "start", ref: "#/components/parameters/Start/schema" ),
				new OA\Property( property: "end", ref: "#/components/parameters/End/schema" ),
				new OA\Property(
					property: "bots",
					properties: [
						new OA\Property(
							property: "Page title",
							properties: [
								new OA\Property(
									property: "count",
									description: "Number of edits to the page.",
									type: "integer"
								),
								new OA\Property(
									property: "current",
									description: "Whether the account currently has the bot flag",
									type: "boolean"
								),
							],
							type: "object"
						),
					],
					type: "object"
				),
				new OA\Property( property: "elapsed_time", ref: "#/components/schemas/elapsed_time" ),
			]
		)
	)]
	#[OA\Response( ref: "#/components/responses/404", response: 404 )]
	#[OA\Response( ref: "#/components/responses/503", response: 503 )]
	#[OA\Response( ref: "#/components/responses/504", response: 504 )]
	#[Route(
		'/api/page/bot_data/{project}/{page}/{start}/{end}',
		name: 'PageApiBotData',
		requirements: [
			'page' => '(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$',
			'start' => '|\d{4}-\d{2}-\d{2}',
			'end' => '|\d{4}-\d{2}-\d{2}',
		],
		defaults: [
			'start' => false,
			'end' => false,
		],
		methods: [ 'GET' ]
	)]
	/**
	 * Get data about bots that have edited a page.
	 * @codeCoverageIgnore
	 */
	public function botDataApiAction(
		PageInfoRepository $pageInfoRepo,
		AutomatedEditsHelper $autoEditsHelper
	): JsonResponse {
		$this->recordApiUsage( 'page/bot_data' );

		$this->setupPageInfo( $pageInfoRepo, $autoEditsHelper );
		$bots = $this->pageInfo->getBots();

		return $this->getFormattedApiResponse( [
			'bots' => $bots,
		] );
	}

	#[OA\Tag( name: "Page API" )]
	#[OA\Get( description:
		"Get counts of the number of times known (semi-)automated tools were used to edit the page."
	)]
	#[OA\Parameter( ref: "#/components/parameters/Project" )]
	#[OA\Parameter( ref: "#/components/parameters/Page" )]
	#[OA\Parameter( ref: "#/components/parameters/Start" )]
	#[OA\Parameter( ref: "#/components/parameters/End" )]
	#[OA\Response(
		response: 200,
		description: "List of tools",
		content: new OA\JsonContent(
			properties: [
				new OA\Property( property: "project", ref: "#/components/parameters/Project/schema" ),
				new OA\Property( property: "page", ref: "#/components/parameters/Page/schema" ),
				new OA\Property( property: "start", ref: "#/components/parameters/Start/schema" ),
				new OA\Property( property: "end", ref: "#/components/parameters/End/schema" ),
				new OA\Property( property: "automated_tools", ref: "#/components/schemas/AutomatedTools" ),
				new OA\Property( property: "elapsed_time", ref: "#/components/schemas/elapsed_time" ),
			]
		)
	)]
	#[OA\Response( ref: "#/components/responses/404", response: 404 )]
	#[OA\Response( ref: "#/components/responses/503", response: 503 )]
	#[OA\Response( ref: "#/components/responses/504", response: 504 )]
	#[Route(
		'/api/page/automated_edits/{project}/{page}/{start}/{end}',
		name: 'PageApiAutoEdits',
		requirements: [
			'page' => '(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$',
			'start' => '|\d{4}-\d{2}-\d{2}',
			'end' => '|\d{4}-\d{2}-\d{2}',
		],
		defaults: [
			'start' => false,
			'end' => false,
		],
		methods: [ 'GET' ]
	)]
	/**
	 * Get counts of (semi-)automated tools that were used to edit the page.
	 * @codeCoverageIgnore
	 */
	public function getAutoEdits(
		PageInfoRepository $pageInfoRepo,
		AutomatedEditsHelper $autoEditsHelper
	): JsonResponse {
		$this->recordApiUsage( 'page/auto_edits' );

		$this->setupPageInfo( $pageInfoRepo, $autoEditsHelper );
		return $this->getFormattedApiResponse( [
			'automated_tools' => $this->pageInfo->getAutoEditsCounts(),
		] );
	}
}
