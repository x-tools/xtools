<?php

declare( strict_types=1 );

namespace App\Controller;

use App\Model\Pages;
use App\Model\Project;
use App\Repository\PagesRepository;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * This controller serves the Pages tool.
 */
class PagesController extends XtoolsController {
	/**
	 * Get the name of the tool's index route.
	 * This is also the name of the associated model.
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getIndexRoute(): string {
		return 'Pages';
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function tooHighEditCountRoute(): string {
		return $this->getIndexRoute();
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function tooHighEditCountActionAllowlist(): array {
		return [ 'countPagesApi' ];
	}

	/**
	 * Display the form.
	 * @Route("/pages", name="Pages")
	 * @Route("/pages/index.php", name="PagesIndexPhp")
	 * @Route("/pages/{project}", name="PagesProject")
	 * @return Response
	 */
	public function indexAction(): Response {
		// Redirect if at minimum project and username are given.
		if ( isset( $this->params['project'] ) && isset( $this->params['username'] ) ) {
			return $this->redirectToRoute( 'PagesResult', $this->params );
		}

		// Otherwise fall through.
		return $this->render( 'pages/index.html.twig', array_merge( [
			'xtPageTitle' => 'tool-pages',
			'xtSubtitle' => 'tool-pages-desc',
			'xtPage' => 'Pages',

			// Defaults that will get overridden if in $params.
			'username' => '',
			'namespace' => 0,
			'redirects' => 'noredirects',
			'deleted' => 'all',
			'start' => '',
			'end' => '',
		], $this->params, [ 'project' => $this->project ] ) );
	}

	/**
	 * Every action in this controller (other than 'index') calls this first.
	 * @param PagesRepository $pagesRepo
	 * @param string $redirects One of the Pages::REDIR_ constants.
	 * @param string $deleted One of the Pages::DEL_ constants.
	 * @return Pages
	 * @codeCoverageIgnore
	 */
	protected function setUpPages( PagesRepository $pagesRepo, string $redirects, string $deleted ): Pages {
		if ( $this->user->isIpRange() ) {
			$this->params['username'] = $this->user->getUsername();
			$this->throwXtoolsException( $this->getIndexRoute(), 'error-ip-range-unsupported' );
		}

		return new Pages(
			$pagesRepo,
			$this->project,
			$this->user,
			$this->namespace,
			$redirects,
			$deleted,
			$this->start,
			$this->end,
			$this->offset
		);
	}

	/**
	 * Display the results.
	 * @Route(
	 *     "/pages/{project}/{username}/{namespace}/{redirects}/{deleted}/{start}/{end}/{offset}",
	 *     name="PagesResult",
	 *     requirements={
	 *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
	 *         "namespace"="|all|\d+",
	 *         "redirects"="|[^/]+",
	 *         "deleted"="|all|live|deleted",
	 *         "start"="|\d{4}-\d{2}-\d{2}",
	 *         "end"="|\d{4}-\d{2}-\d{2}",
	 *         "offset"="|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z?",
	 *     },
	 *     defaults={
	 *         "namespace"=0,
	 *         "start"=false,
	 *         "end"=false,
	 *         "offset"=false,
	 *     }
	 * )
	 * @param PagesRepository $pagesRepo
	 * @param string $redirects One of the Pages::REDIR_ constants.
	 * @param string $deleted One of the Pages::DEL_ constants.
	 * @return RedirectResponse|Response
	 * @codeCoverageIgnore
	 */
	public function resultAction(
		PagesRepository $pagesRepo,
		string $redirects = Pages::REDIR_NONE,
		string $deleted = Pages::DEL_ALL
	) {
		// Check for legacy values for 'redirects', and redirect
		// back with correct values if need be. This could be refactored
		// out to XtoolsController, but this is the only tool in the suite
		// that deals with redirects, so we'll keep it confined here.
		$validRedirects = [ '', Pages::REDIR_NONE, Pages::REDIR_ONLY, Pages::REDIR_ALL ];
		if ( 'none' === $redirects || !in_array( $redirects, $validRedirects ) ) {
			return $this->redirectToRoute( 'PagesResult', array_merge( $this->params, [
				'redirects' => Pages::REDIR_NONE,
				'deleted' => $deleted,
				'offset' => $this->offset,
			] ) );
		}

		$pages = $this->setUpPages( $pagesRepo, $redirects, $deleted );
		$pages->prepareData();

		$ret = [
			'xtPage' => 'Pages',
			'xtTitle' => $this->user->getUsername(),
			'summaryColumns' => $pages->getSummaryColumns(),
			'pages' => $pages,
		];

		if ( 'PagePile' === $this->request->query->get( 'format' ) ) {
			return $this->getPagepileResult( $this->project, $pages );
		}

		// Output the relevant format template.
		return $this->getFormattedResponse( 'pages/result', $ret );
	}

	/**
	 * Create a PagePile for the given pages, and get a Redirect to that PagePile.
	 * @param Project $project
	 * @param Pages $pages
	 * @return RedirectResponse
	 * @throws HttpException
	 * @see https://pagepile.toolforge.org
	 * @codeCoverageIgnore
	 */
	private function getPagepileResult( Project $project, Pages $pages ): RedirectResponse {
		$namespaces = $project->getNamespaces();
		$pageTitles = [];

		foreach ( array_values( $pages->getResults() ) as $pagesData ) {
			foreach ( $pagesData as $page ) {
				if ( 0 === (int)$page['namespace'] ) {
					$pageTitles[] = $page['page_title'];
				} else {
					$pageTitles[] = (
						$namespaces[$page['namespace']] ?? $this->i18n->msg( 'unknown' )
					) . ':' . $page['page_title'];
				}
			}
		}

		$pileId = $this->createPagePile( $project, $pageTitles );

		return new RedirectResponse(
			"https://pagepile.toolforge.org/api.php?id=$pileId&action=get_data&format=html&doit1"
		);
	}

	/**
	 * Create a PagePile with the given titles.
	 * @param Project $project
	 * @param string[] $pageTitles
	 * @return int The PagePile ID.
	 * @throws HttpException
	 * @see https://pagepile.toolforge.org/
	 * @codeCoverageIgnore
	 */
	private function createPagePile( Project $project, array $pageTitles ): int {
		$url = 'https://pagepile.toolforge.org/api.php';

		try {
			$res = $this->guzzle->request( 'GET', $url, [ 'query' => [
				'action' => 'create_pile_with_data',
				'wiki' => $project->getDatabaseName(),
				'data' => implode( "\n", $pageTitles ),
			] ] );
		} catch ( ClientException $e ) {
			throw new HttpException(
				414,
				'error-pagepile-too-large'
			);
		}

		$ret = json_decode( $res->getBody()->getContents(), true );

		if ( !isset( $ret['status'] ) || 'OK' !== $ret['status'] ) {
			throw new HttpException(
				500,
				'Failed to create PagePile. There may be an issue with the PagePile API.'
			);
		}

		return $ret['pile']['id'];
	}

	/************************ API endpoints */

	/**
	 * Count the number of pages created by a user.
	 * @Route(
	 *     "/api/user/pages_count/{project}/{username}/{namespace}/{redirects}/{deleted}/{start}/{end}",
	 *     name="UserApiPagesCount",
	 *     requirements={
	 *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
	 *         "namespace"="|\d+|all",
	 *         "redirects"="|noredirects|onlyredirects|all",
	 *         "deleted"="|all|live|deleted",
	 *         "start"="|\d{4}-\d{2}-\d{2}",
	 *         "end"="|\d{4}-\d{2}-\d{2}",
	 *     },
	 *     defaults={
	 *         "namespace"=0,
	 *         "redirects"="noredirects",
	 *         "deleted"="all",
	 *         "start"=false,
	 *         "end"=false,
	 *     },
	 *     methods={"GET"}
	 * )
	 * @OA\Tag(name="User API")
	 * @OA\Get(description="Get the number of pages created by a user, keyed by namespace.")
	 * @OA\Parameter(ref="#/components/parameters/Project")
	 * @OA\Parameter(ref="#/components/parameters/UsernameOrSingleIp")
	 * @OA\Parameter(ref="#/components/parameters/Namespace")
	 * @OA\Parameter(ref="#/components/parameters/Redirects")
	 * @OA\Parameter(ref="#/components/parameters/Deleted")
	 * @OA\Parameter(ref="#/components/parameters/Start")
	 * @OA\Parameter(ref="#/components/parameters/End")
	 * @OA\Response(
	 *     response=200,
	 *     description="Page counts",
	 * @OA\JsonContent(
	 * @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
	 * @OA\Property(property="username", ref="#/components/parameters/UsernameOrSingleIp/schema"),
	 * @OA\Property(property="namespace", ref="#/components/schemas/Namespace"),
	 * @OA\Property(property="redirects", ref="#/components/parameters/Redirects/schema"),
	 * @OA\Property(property="deleted", ref="#components/parameters/Deleted/schema"),
	 * @OA\Property(property="start", ref="#components/parameters/Start/schema"),
	 * @OA\Property(property="end", ref="#components/parameters/End/schema"),
	 * @OA\Property(property="counts", type="object", example={
	 *             "0": {
	 *                 "count": 5,
	 *                 "total_length": 500,
	 *                 "avg_length": 100
	 *             },
	 *             "2": {
	 *                 "count": 1,
	 *                 "total_length": 200,
	 *                 "avg_length": 200
	 *             }
	 *         }),
	 * @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
	 *     )
	 * )
	 * @OA\Response(response=404, ref="#/components/responses/404")
	 * @OA\Response(response=501, ref="#/components/responses/501")
	 * @OA\Response(response=503, ref="#/components/responses/503")
	 * @OA\Response(response=504, ref="#/components/responses/504")
	 * @param PagesRepository $pagesRepo
	 * @param string $redirects One of 'noredirects', 'onlyredirects' or 'all' for both.
	 * @param string $deleted One of 'live', 'deleted' or 'all' for both.
	 * @return JsonResponse
	 * @codeCoverageIgnore
	 */
	public function countPagesApiAction(
		PagesRepository $pagesRepo,
		string $redirects = Pages::REDIR_NONE,
		string $deleted = Pages::DEL_ALL
	): JsonResponse {
		$this->recordApiUsage( 'user/pages_count' );

		$pages = $this->setUpPages( $pagesRepo, $redirects, $deleted );
		$counts = $pages->getCounts();

		return $this->getFormattedApiResponse( [ 'counts' => (object)$counts ] );
	}

	/**
	 * Get the pages created by by a user.
	 * @Route(
	 *     "/api/user/pages/{project}/{username}/{namespace}/{redirects}/{deleted}/{start}/{end}/{offset}",
	 *     name="UserApiPagesCreated",
	 *     requirements={
	 *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
	 *         "namespace"="|\d+|all",
	 *         "redirects"="|noredirects|onlyredirects|all",
	 *         "deleted"="|all|live|deleted",
	 *         "start"="|\d{4}-\d{2}-\d{2}",
	 *         "end"="|\d{4}-\d{2}-\d{2}",
	 *         "offset"="|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z?",
	 *     },
	 *     defaults={
	 *         "namespace"=0,
	 *         "redirects"="noredirects",
	 *         "deleted"="all",
	 *         "start"=false,
	 *         "end"=false,
	 *         "offset"=false,
	 *     },
	 *     methods={"GET"}
	 * )
	 * @OA\Tag(name="User API")
	 * @OA\Get(description="Get pages created by a user, keyed by namespace.")
	 * @OA\Parameter(ref="#/components/parameters/Project")
	 * @OA\Parameter(ref="#/components/parameters/UsernameOrSingleIp")
	 * @OA\Parameter(ref="#/components/parameters/Namespace")
	 * @OA\Parameter(ref="#/components/parameters/Redirects")
	 * @OA\Parameter(ref="#/components/parameters/Deleted")
	 * @OA\Parameter(ref="#/components/parameters/Start")
	 * @OA\Parameter(ref="#/components/parameters/End")
	 * @OA\Parameter(ref="#/components/parameters/Offset")
	 * @OA\Parameter(name="format", in="query",
	 * @OA\Schema(default="json", type="string", enum={"json","wikitext","pagepile","csv","tsv"})
	 * )
	 * @OA\Response(
	 *     response=200,
	 *     description="Pages created",
	 * @OA\JsonContent(
	 * @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
	 * @OA\Property(property="username", ref="#/components/parameters/UsernameOrSingleIp/schema"),
	 * @OA\Property(property="namespace", ref="#/components/schemas/Namespace"),
	 * @OA\Property(property="redirects", ref="#/components/parameters/Redirects/schema"),
	 * @OA\Property(property="deleted", ref="#components/parameters/Deleted/schema"),
	 * @OA\Property(property="start", ref="#components/parameters/Start/schema"),
	 * @OA\Property(property="end", ref="#components/parameters/End/schema"),
	 * @OA\Property(property="pages", type="object",
	 * @OA\Property(property="namespace ID", ref="#/components/schemas/PageCreation")
	 *         ),
	 * @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
	 *     )
	 * )
	 * @OA\Response(response=404, ref="#/components/responses/404")
	 * @OA\Response(response=501, ref="#/components/responses/501")
	 * @OA\Response(response=503, ref="#/components/responses/503")
	 * @OA\Response(response=504, ref="#/components/responses/504")
	 * @param PagesRepository $pagesRepo
	 * @param string $redirects One of 'noredirects', 'onlyredirects' or 'all' for both.
	 * @param string $deleted One of 'live', 'deleted' or blank for both.
	 * @return JsonResponse
	 * @codeCoverageIgnore
	 */
	public function getPagesApiAction(
		PagesRepository $pagesRepo,
		string $redirects = Pages::REDIR_NONE,
		string $deleted = Pages::DEL_ALL
	): JsonResponse {
		$this->recordApiUsage( 'user/pages' );

		$pages = $this->setUpPages( $pagesRepo, $redirects, $deleted );
		$ret = [ 'pages' => $pages->getResults() ];

		if ( $pages->getNumResults() === $pages->resultsPerPage() ) {
			$ret['continue'] = $pages->getLastTimestamp();
		}

		return $this->getFormattedApiResponse( $ret );
	}

	/**
	 * Get the deletion summary to be shown when hovering over the "Deleted" text in the UI.
	 * @Route(
	 *     "/pages/deletion_summary/{project}/{username}/{namespace}/{pageTitle}/{timestamp}",
	 *     name="PagesApiDeletionSummary"
	 * )
	 * @return JsonResponse
	 * @codeCoverageIgnore
	 * @internal
	 */
	public function getDeletionSummaryApiAction(
		PagesRepository $pagesRepo,
		int $namespace,
		string $pageTitle,
		string $timestamp
	): JsonResponse {
		// Redirect/deleted options actually don't matter here.
		$pages = $this->setUpPages( $pagesRepo, Pages::REDIR_NONE, Pages::DEL_ALL );
		return $this->getFormattedApiResponse( [
			'summary' => $pages->getDeletionSummary( $namespace, $pageTitle, $timestamp ),
		] );
	}
}
