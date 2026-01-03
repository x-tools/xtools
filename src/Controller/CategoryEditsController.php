<?php

declare( strict_types=1 );

namespace App\Controller;

use App\Exception\XtoolsHttpException;
use App\Model\CategoryEdits;
use App\Repository\CategoryEditsRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * This controller serves the Category Edits tool.
 */
class CategoryEditsController extends XtoolsController {
	protected CategoryEdits $categoryEdits;

	/** @var string[] The categories, with or without namespace. */
	protected array $categories;

	/** @var array Data that is passed to the view. */
	private array $output;

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getIndexRoute(): string {
		return 'CategoryEdits';
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function tooHighEditCountRoute(): string {
		return $this->getIndexRoute();
	}

	#[Route( path: '/categoryedits', name: 'CategoryEdits' )]
	#[Route( path: '/categoryedits/{project}', name: 'CategoryEditsProject' )]
	/**
	 * Display the search form.
	 * @codeCoverageIgnore
	 */
	public function indexAction(): Response {
		// Redirect if at minimum project, username and categories are provided.
		if ( isset( $this->params['project'] )
			&& isset( $this->params['username'] )
			&& isset( $this->params['categories'] )
		) {
			return $this->redirectToRoute( 'CategoryEditsResult', $this->params );
		}

		return $this->render( 'categoryEdits/index.html.twig', array_merge( [
			'xtPageTitle' => 'tool-categoryedits',
			'xtSubtitle' => 'tool-categoryedits-desc',
			'xtPage' => 'CategoryEdits',

			// Defaults that will get overridden if in $params.
			'namespace' => 0,
			'start' => '',
			'end' => '',
			'username' => '',
			'categories' => '',
		], $this->params, [ 'project' => $this->project ] ) );
	}

	/**
	 * Set defaults, and instantiate the CategoryEdits model. This is called at the top of every view action.
	 * @codeCoverageIgnore
	 */
	private function setupCategoryEdits( CategoryEditsRepository $categoryEditsRepo ): void {
		$this->extractCategories();

		$this->categoryEdits = new CategoryEdits(
			$categoryEditsRepo,
			$this->project,
			$this->user,
			$this->categories,
			$this->start,
			$this->end,
			$this->offset
		);

		$this->output = [
			'xtPage' => 'CategoryEdits',
			'xtTitle' => $this->user->getUsername(),
			'project' => $this->project,
			'user' => $this->user,
			'ce' => $this->categoryEdits,
			'is_sub_request' => $this->isSubRequest,
		];
	}

	/**
	 * Go through the categories and normalize values, and set them on class properties.
	 * @codeCoverageIgnore
	 */
	private function extractCategories(): void {
		// Split categories by pipe.
		$categories = explode( '|', $this->request->get( 'categories' ) );

		// Loop through the given categories, stripping out the namespace.
		// If a namespace was removed, it is flagged it as normalize
		// We look for the wiki's category namespace name, and the MediaWiki default
		// 'Category:', which sometimes is used cross-wiki (because it still works).
		$normalized = false;
		$nsName = $this->project->getNamespaces()[14] . ':';
		$this->categories = array_map( static function ( $category ) use ( $nsName, &$normalized ) {
			if ( str_starts_with( $category, $nsName ) || str_starts_with( $category, 'Category:' ) ) {
				$normalized = true;
			}
			return preg_replace( '/^' . $nsName . '/', '', $category );
		}, $categories );

		// Redirect if normalized, since we don't want the Category: prefix in the URL.
		if ( $normalized ) {
			throw new XtoolsHttpException(
				'',
				$this->generateUrl( $this->request->get( '_route' ), array_merge(
					$this->request->attributes->get( '_route_params' ),
					[ 'categories' => implode( '|', $this->categories ) ]
				) )
			);
		}
	}

	#[Route(
		"/categoryedits/{project}/{username}/{categories}/{start}/{end}/{offset}",
		name: "CategoryEditsResult",
		requirements: [
			"username" => "(ipr-.+\/\d+[^\/])|([^\/]+)",
			"categories" => "(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$",
			"start" => "|\d{4}-\d{2}-\d{2}",
			"end" => "|\d{4}-\d{2}-\d{2}",
			"offset" => "|\d{4}-?\d{2}-?\d{2}T?\d{2}:?\d{2}:?\d{2}Z?",
		],
		defaults: [ "start" => false, "end" => false, "offset" => false ]
	)]
	/**
	 * Display the results.
	 * @codeCoverageIgnore
	 */
	public function resultAction( CategoryEditsRepository $categoryEditsRepo ): Response {
		$this->setupCategoryEdits( $categoryEditsRepo );

		return $this->getFormattedResponse( 'categoryEdits/result', $this->output );
	}

	#[Route(
		"/categoryedits-contributions/{project}/{username}/{categories}/{start}/{end}/{offset}",
		name: "CategoryContributionsResult",
		requirements: [
			"username" => "(ipr-.+\/\d+[^\/])|([^\/]+)",
			"categories" => "(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2}))?",
			"start" => "|\d{4}-\d{2}-\d{2}",
			"end" => "|\d{4}-\d{2}-\d{2}",
			"offset" => "|\d{4}-?\d{2}-?\d{2}T?\d{2}:?\d{2}:?\d{2}Z?",
		],
		defaults: [ "start" => false, "end" => false, "offset" => false ]
	)]
	/**
	 * Get edits by a user to pages in given categories.
	 * @codeCoverageIgnore
	 */
	public function categoryContributionsAction( CategoryEditsRepository $categoryEditsRepo ): Response {
		$this->setupCategoryEdits( $categoryEditsRepo );

		return $this->render( 'categoryEdits/contributions.html.twig', $this->output );
	}

	/************************ API endpoints */

	#[OA\Tag( name: "User API" )]
	#[OA\Get( description:
		"Count the number of edits a user has made to pages in any of the given [categories](https://w.wiki/6oKx)."
	)]
	#[OA\Parameter( ref: "#/components/parameters/Project" )]
	#[OA\Parameter( ref: "#/components/parameters/UsernameOrIp" )]
	#[OA\Parameter(
		name: "categories",
		description: "Pipe-separated list of category names, without the namespace prefix.",
		in: "path",
		schema: new OA\Schema( type: "array", items: new OA\Items( type: "string" ), example: [ "Living people" ] ),
		style: "pipeDelimited"
	)]
	#[OA\Parameter( ref: "#/components/parameters/Start" )]
	#[OA\Parameter( ref: "#/components/parameters/End" )]
	#[OA\Response(
		response: 200,
		description: "Count of edits made to any of the given categories.",
		content: new OA\JsonContent(
			properties: [
				new OA\Property( property: "project", ref: "#/components/parameters/Project/schema" ),
				new OA\Property( property: "username", ref: "#/components/parameters/UsernameOrIp/schema" ),
				new OA\Property(
					property: "categories",
					type: "array",
					items: new OA\Items( type: "string" ),
					example: [ "Living people" ]
				),
				new OA\Property( property: "start", ref: "#/components/parameters/Start/schema" ),
				new OA\Property( property: "end", ref: "#/components/parameters/End/schema" ),
				new OA\Property( property: "total_editcount", type: "integer" ),
				new OA\Property( property: "category_editcount", type: "integer" ),
				new OA\Property( property: "elapsed_time", ref: "#/components/schemas/elapsed_time" ),
			]
		)
	)]
	#[OA\Response( ref: "#/components/responses/404", response: 404 )]
	#[OA\Response( ref: "#/components/responses/501", response: 501 )]
	#[OA\Response( ref: "#/components/responses/503", response: 503 )]
	#[OA\Response( ref: "#/components/responses/504", response: 504 )]
	#[Route(
		"/api/user/category_editcount/{project}/{username}/{categories}/{start}/{end}",
		name: "UserApiCategoryEditCount",
		requirements: [
			"username" => "(ipr-.+\/\d+[^\/])|([^\/]+)",
			"categories" => "(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$",
			"start" => "|\d{4}-\d{2}-\d{2}",
			"end" => "|\d{4}-\d{2}-\d{2}",
		],
		defaults: [ "start" => false, "end" => false ],
		methods: [ "GET" ]
	)]
	/**
	 * Count the number of edits a user has made in a category.
	 * @codeCoverageIgnore
	 */
	public function categoryEditCountApiAction( CategoryEditsRepository $categoryEditsRepo ): JsonResponse {
		$this->recordApiUsage( 'user/category_editcount' );

		$this->setupCategoryEdits( $categoryEditsRepo );

		$ret = [
			// Ensure `categories` is always treated as an array, even if one element.
			// (XtoolsController would otherwise see it as a single value from the URL query string).
			'categories' => $this->categories,
			'total_editcount' => $this->categoryEdits->getEditCount(),
			'category_editcount' => $this->categoryEdits->getCategoryEditCount(),
		];

		return $this->getFormattedApiResponse( $ret );
	}
}
