<?php

declare( strict_types=1 );

namespace App\Controller;

use App\Model\Edit;
use App\Repository\ProjectRepository;
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Exception;
use MediaWiki\OAuthClient\Token;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The DefaultController handles the homepage, about pages, and user authentication.
 */
class DefaultController extends XtoolsController {
	/** @var Client The Oauth HTTP client. */
	protected Client $oauthClient;

	/**
	 * Required to be defined by XtoolsController, though here it is unused.
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getIndexRoute(): string {
		return 'homepage';
	}

	#[Route( '/', name: 'homepage' )]
	#[Route( '/index.php', name: 'homepageIndexPhp' )]
	public function indexAction(): Response {
		return $this->render( 'default/index.html.twig', [
			'xtPage' => 'home',
		] );
	}

	#[Route( '/login', name: 'login' )]
	/**
	 * Redirect to the default project (or Meta) for Oauth authentication.
	 */
	public function loginAction(
		Request $request,
		RequestStack $requestStack,
		ProjectRepository $projectRepo,
		UrlGeneratorInterface $urlGenerator,
		string $centralAuthProject
	): RedirectResponse {
		try {
			[ $next, $token ] = $this->getOauthClient( $request, $projectRepo, $urlGenerator, $centralAuthProject )
				->initiate();
		} catch ( Exception $oauthException ) {
			$this->addFlashMessage( 'notice', 'error-login' );
			return $this->redirectToRoute( 'homepage' );
		}

		// Save the request token to the session.
		$requestStack->getSession()->set( 'oauth_request_token', $token );
		return new RedirectResponse( $next );
	}

	#[Route( '/oauth_callback', name: 'oauth_callback' )]
	#[Route( '/oauthredirector.php', name: 'old_oauth_callback' )]
	/**
	 * Receive authentication credentials back from the Oauth wiki.
	 */
	public function oauthCallbackAction(
		RequestStack $requestStack,
		ProjectRepository $projectRepo,
		UrlGeneratorInterface $urlGenerator,
		string $centralAuthProject
	): RedirectResponse {
		$request = $requestStack->getCurrentRequest();
		$session = $requestStack->getSession();
		// Give up if the required GET params don't exist.
		if ( !$request->get( 'oauth_verifier' ) ) {
			throw $this->createNotFoundException( 'No OAuth verifier given.' );
		}

		// Complete authentication.
		$client = $this->getOauthClient( $request, $projectRepo, $urlGenerator, $centralAuthProject );
		$token = $requestStack->getSession()->get( 'oauth_request_token' );

		if ( !is_a( $token, Token::class ) ) {
			$this->addFlashMessage( 'notice', 'error-login' );
			return $this->redirectToRoute( 'homepage' );
		}

		$verifier = $request->get( 'oauth_verifier' );
		$accessToken = $client->complete( $token, $verifier );

		// Store access token, and remove request token.
		$session->set( 'oauth_access_token', $accessToken );
		$session->remove( 'oauth_request_token' );

		// Store user identity.
		$ident = $client->identify( $accessToken );
		$session->set( 'logged_in_user', $ident );

		// Store reference to the client.
		$session->set( 'oauth_client', $this->oauthClient );

		// Redirect to callback, if given.
		if ( $request->query->get( 'redirect' ) ) {
			return $this->redirect( $request->query->get( 'redirect' ) );
		}

		// Send back to homepage.
		return $this->redirectToRoute( 'homepage' );
	}

	/**
	 * Get an OAuth client, configured to the default project.
	 * (This shouldn't really be in this class, but oh well.)
	 * @codeCoverageIgnore
	 */
	protected function getOauthClient(
		Request $request,
		ProjectRepository $projectRepo,
		UrlGeneratorInterface $urlGenerator,
		string $centralAuthProject
	): Client {
		if ( isset( $this->oauthClient ) ) {
			return $this->oauthClient;
		}
		$defaultProject = $projectRepo->getProject( $centralAuthProject );
		$endpoint = $defaultProject->getUrl( false )
					. $defaultProject->getScript()
					. '?title=Special:OAuth';
		$conf = new ClientConfig( $endpoint );
		$consumerKey = $this->getParameter( 'oauth_key' );
		$consumerSecret = $this->getParameter( 'oauth_secret' );
		$conf->setConsumer( new Consumer( $consumerKey, $consumerSecret ) );
		$conf->setUserAgent(
			'XTools/' . $this->getParameter( 'app.version' ) . ' (' .
			rtrim(
				$urlGenerator->generate( $this->getIndexRoute(), [], UrlGeneratorInterface::ABSOLUTE_URL ),
				'/'
			) . ' ' . $this->getParameter( 'mailer.to_email' ) . ')'
		);
		$this->oauthClient = new Client( $conf );

		// Set the callback URL if given. Used to redirect back to target page after logging in.
		if ( $request->query->get( 'callback' ) ) {
			$this->oauthClient->setCallback( $request->query->get( 'callback' ) );
		}

		return $this->oauthClient;
	}

	#[Route( '/logout', name: 'logout' )]
	/**
	 * Log out the user and return to the homepage.
	 */
	public function logoutAction( RequestStack $requestStack ): RedirectResponse {
		$requestStack->getSession()->invalidate();
		return $this->redirectToRoute( 'homepage' );
	}

	/************************ API endpoints */

	#[OA\Tag( name: "Project API" )]
	#[OA\Parameter( ref: "#/components/parameters/Project" )]
	#[OA\Response(
		response: 200,
		description: "The domain, URL, API path and database name.",
		content: new OA\JsonContent(
			properties: [
				new OA\Property( property: "project", ref: "#/components/parameters/Project/schema" ),
				new OA\Property( property: "domain", type: "string", example: "en.wikipedia.org" ),
				new OA\Property( property: "url", type: "string", example: "https://en.wikipedia.org" ),
				new OA\Property( property: "api", type: "string", example: "https://en.wikipedia.org/w/api.php" ),
				new OA\Property( property: "database", type: "string", example: "enwiki" ),
				new OA\Property( property: "elapsed_time", ref: "#/components/schemas/elapsed_time" ),
			]
		)
	)]
	#[OA\Response( ref: "#/components/responses/404", response: 404 )]
	#[OA\Response( ref: "#/components/responses/503", response: 503 )]
	#[OA\Response( ref: "#/components/responses/504", response: 504 )]
	#[Route( '/api/project/normalize/{project}', name: 'ProjectApiNormalize', methods: [ 'GET' ] )]
	/**
	 * Get domain name, URL, API path and database name for the given project.
	 */
	public function normalizeProjectApiAction(): JsonResponse {
		return $this->getFormattedApiResponse( [
			'domain' => $this->project->getDomain(),
			'url' => $this->project->getUrl(),
			'api' => $this->project->getApiUrl(),
			'database' => $this->project->getDatabaseName(),
		] );
	}

	#[OA\Tag( name: "Project API" )]
	#[OA\Parameter( ref: "#/components/parameters/Project" )]
	#[OA\Response(
		response: 200,
		description: "List of localized namespaces keyed by their ID.",
		content: new OA\JsonContent(
			properties: [
				new OA\Property( property: "project", ref: "#/components/parameters/Project/schema" ),
				new OA\Property( property: "url", type: "string", example: "https://en.wikipedia.org" ),
				new OA\Property( property: "api", type: "string", example: "https://en.wikipedia.org/w/api.php" ),
				new OA\Property( property: "database", type: "string", example: "enwiki" ),
				new OA\Property( property: "namespaces", type: "object", example: [ '0' => '', '3' => 'User talk' ] ),
				new OA\Property( property: "elapsed_time", ref: "#/components/schemas/elapsed_time" ),
			]
		)
	)]
	#[OA\Response( ref: "#/components/responses/404", response: 404 )]
	#[OA\Response( ref: "#/components/responses/503", response: 503 )]
	#[OA\Response( ref: "#/components/responses/504", response: 504 )]
	#[Route( '/api/project/namespaces/{project}', name: 'ProjectApiNamespaces', methods: [ 'GET' ] )]
	/**
	 * Get the localized names for each namespaces of the given project.
	 */
	public function namespacesApiAction(): JsonResponse {
		return $this->getFormattedApiResponse( [
			'domain' => $this->project->getDomain(),
			'url' => $this->project->getUrl(),
			'api' => $this->project->getApiUrl(),
			'database' => $this->project->getDatabaseName(),
			'namespaces' => $this->project->getNamespaces(),
		] );
	}

	#[OA\Tag( name: "Project API" )]
	#[OA\Parameter( ref: "#/components/parameters/Project" )]
	#[OA\Response(
		response: 200,
		description: "List of classifications and importance levels, along with their associated colours and badges.",
		content: new OA\JsonContent(
			properties: [
				new OA\Property( property: "project", ref: "#/components/parameters/Project/schema" ),
				new OA\Property(
					property: "assessments",
					type: "object",
					example: [
						"wikiproject_prefix" => "Wikipedia:WikiProject ",
						"class" => [
							"FA" => [
								"badge" => "b/bc/Featured_article_star.svg",
								"color" => "#9CBDFF",
								"category" => "Category:FA-Class articles",
							],
						],
						"importance" => [
							"Top" => [
								"color" => "#FF97FF",
								"category" => "Category:Top-importance articles",
								"weight" => 5,
							],
						],
					]
				),
				new OA\Property( property: "elapsed_time", ref: "#/components/schemas/elapsed_time" ),
			]
		)
	)]
	#[OA\Response( ref: "#/components/responses/404", response: 404 )]
	#[Route( '/api/project/assessments/{project}', name: 'ProjectApiAssessments', methods: [ 'GET' ] )]
	/**
	 * Get page assessment metadata for a project.
	 */
	public function projectAssessmentsApiAction(): JsonResponse {
		return $this->getFormattedApiResponse( [
			'project' => $this->project->getDomain(),
			'assessments' => $this->project->getPageAssessments()->getConfig(),
		] );
	}

	#[OA\Tag( name: "Project API" )]
	#[OA\Response(
		response: 200,
		description: "Page assessment metadata for all projects that have\n" .
			"<a href='https://w.wiki/6o9c'>PageAssessments</a> installed.",
		content: new OA\JsonContent(
			properties: [
				new OA\Property(
					property: "projects",
					type: "array",
					items: new OA\Items( type: "string" ),
					example: [ "en.wikipedia.org", "fr.wikipedia.org" ]
				),
				new OA\Property(
					property: "config",
					type: "object",
					example: [
						"en.wikipedia.org" => [
							"wikiproject_prefix" => "Wikipedia:WikiProject ",
							"class" => [
								"FA" => [
									"badge" => "b/bc/Featured_article_star.svg",
									"color" => "#9CBDFF",
									"category" => "Category:FA-Class articles",
								],
							],
							"importance" => [
								"Top" => [
									"color" => "#FF97FF",
									"category" => "Category:Top-importance articles",
									"weight" => 5,
								],
							],
						],
					]
				),
				new OA\Property( property: "elapsed_time", ref: "#/components/schemas/elapsed_time" ),
			]
		)
	)]
	#[Route( '/api/project/assessments', name: 'ApiAssessmentsConfig', methods: [ 'GET' ] )]
	/**
	 * Get assessment metadata for all projects.
	 */
	public function assessmentsConfigApiAction(): JsonResponse {
		// Here there is no Project, so we don't use XtoolsController::getFormattedApiResponse().
		$response = new JsonResponse();
		$response->setEncodingOptions( JSON_NUMERIC_CHECK );
		$response->setStatusCode( Response::HTTP_OK );
		$response->setData( [
			'projects' => array_keys( $this->getParameter( 'assessments' ) ),
			'config' => $this->getParameter( 'assessments' ),
		] );

		return $response;
	}

	#[Route( '/api/project/parser/{project}' )]
	/**
	 * Transform given wikitext to HTML using the XTools parser. Wikitext must be passed in as the query 'wikitext'.
	 * @return JsonResponse Safe HTML.
	 */
	public function wikifyApiAction(): JsonResponse {
		return new JsonResponse(
			Edit::wikifyString( $this->request->query->get( 'wikitext', '' ), $this->project )
		);
	}
}
