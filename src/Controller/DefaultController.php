<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Edit;
use App\Repository\ProjectRepository;
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Exception;
use MediaWiki\OAuthClient\Token;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The DefaultController handles the homepage, about pages, and user authentication.
 */
class DefaultController extends XtoolsController
{
    /** @var Client The Oauth HTTP client. */
    protected Client $oauthClient;

    /**
     * Required to be defined by XtoolsController, though here it is unused.
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'homepage';
    }

    /**
     * Display the homepage.
     * @Route("/", name="homepage")
     * @Route("/index.php", name="homepageIndexPhp")
     * @return Response
     */
    public function indexAction(): Response
    {
        return $this->render('default/index.html.twig', [
            'xtPage' => 'home',
        ]);
    }

    /**
     * Redirect to the default project (or Meta) for Oauth authentication.
     * @Route("/login", name="login")
     * @param Request $request
     * @param SessionInterface $session
     * @param ProjectRepository $projectRepo
     * @param string $centralAuthProject
     * @return RedirectResponse
     * @throws Exception If initialization fails.
     */
    public function loginAction(
        Request $request,
        SessionInterface $session,
        ProjectRepository $projectRepo,
        string $centralAuthProject
    ): RedirectResponse {
        try {
            [ $next, $token ] = $this->getOauthClient($request, $projectRepo, $centralAuthProject)->initiate();
        } catch (Exception $oauthException) {
            throw $oauthException;
            // @TODO Make this work.
            //$this->addFlash('error', $oauthException->getMessage());
            //return $this->redirectToRoute('homepage');
        }

        // Save the request token to the session.
        $session->set('oauth_request_token', $token);
        return new RedirectResponse($next);
    }

    /**
     * Receive authentication credentials back from the Oauth wiki.
     * @Route("/oauth_callback", name="oauth_callback")
     * @Route("/oauthredirector.php", name="old_oauth_callback")
     * @param Request $request The HTTP request.
     * @param SessionInterface $session
     * @param ProjectRepository $projectRepo
     * @param string $centralAuthProject
     * @return RedirectResponse
     */
    public function oauthCallbackAction(
        Request $request,
        SessionInterface $session,
        ProjectRepository $projectRepo,
        string $centralAuthProject
    ): RedirectResponse {
        // Give up if the required GET params don't exist.
        if (!$request->get('oauth_verifier')) {
            throw $this->createNotFoundException('No OAuth verifier given.');
        }

        // Complete authentication.
        $client = $this->getOauthClient($request, $projectRepo, $centralAuthProject);
        $token = $session->get('oauth_request_token');

        if (!is_a($token, Token::class)) {
            $this->addFlashMessage('notice', 'error-login');
            return $this->redirectToRoute('homepage');
        }

        $verifier = $request->get('oauth_verifier');
        $accessToken = $client->complete($token, $verifier);

        // Store access token, and remove request token.
        $session->set('oauth_access_token', $accessToken);
        $session->remove('oauth_request_token');

        // Store user identity.
        $ident = $client->identify($accessToken);
        $session->set('logged_in_user', $ident);

        // Store reference to the client.
        $session->set('oauth_client', $this->oauthClient);

        // Redirect to callback, if given.
        if ($request->query->get('redirect')) {
            return $this->redirect($request->query->get('redirect'));
        }

        // Send back to homepage.
        return $this->redirectToRoute('homepage');
    }

    /**
     * Get an OAuth client, configured to the default project.
     * (This shouldn't really be in this class, but oh well.)
     * @param Request $request
     * @param ProjectRepository $projectRepo
     * @param string $centralAuthProject
     * @return Client
     * @codeCoverageIgnore
     */
    protected function getOauthClient(
        Request $request,
        ProjectRepository $projectRepo,
        string $centralAuthProject
    ): Client {
        if (isset($this->oauthClient)) {
            return $this->oauthClient;
        }
        $defaultProject = $projectRepo->getProject($centralAuthProject);
        $endpoint = $defaultProject->getUrl(false)
                    . $defaultProject->getScript()
                    . '?title=Special:OAuth';
        $conf = new ClientConfig($endpoint);
        $consumerKey = $this->getParameter('oauth_key');
        $consumerSecret =  $this->getParameter('oauth_secret');
        $conf->setConsumer(new Consumer($consumerKey, $consumerSecret));
        $this->oauthClient = new Client($conf);

        // Set the callback URL if given. Used to redirect back to target page after logging in.
        if ($request->query->get('callback')) {
            $this->oauthClient->setCallback($request->query->get('callback'));
        }

        return $this->oauthClient;
    }

    /**
     * Log out the user and return to the homepage.
     * @Route("/logout", name="logout")
     * @param SessionInterface $session
     * @return RedirectResponse
     */
    public function logoutAction(SessionInterface $session): RedirectResponse
    {
        $session->invalidate();
        return $this->redirectToRoute('homepage');
    }

    /************************ API endpoints ************************/

    /**
     * Get domain name, URL, and API URL of the given project.
     * @Route("/api/project/normalize/{project}", name="ProjectApiNormalize")
     * @return JsonResponse
     */
    public function normalizeProjectApiAction(): JsonResponse
    {
        return $this->getFormattedApiResponse([
            'domain' => $this->project->getDomain(),
            'url' => $this->project->getUrl(),
            'api' => $this->project->getApiUrl(),
            'database' => $this->project->getDatabaseName(),
        ]);
    }

    /**
     * Get all namespaces of the given project. This endpoint also does the same thing
     * as the /project/normalize endpoint, returning other basic info about the project.
     * @Route("/api/project/namespaces/{project}", name="ProjectApiNamespaces")
     * @return JsonResponse
     */
    public function namespacesApiAction(): JsonResponse
    {
        return $this->getFormattedApiResponse([
            'domain' => $this->project->getDomain(),
            'url' => $this->project->getUrl(),
            'api' => $this->project->getApiUrl(),
            'database' => $this->project->getDatabaseName(),
            'namespaces' => $this->project->getNamespaces(),
        ]);
    }

    /**
     * Get assessment data for a given project.
     * @Route("/api/project/assessments/{project}", name="ProjectApiAssessments")
     * @return JsonResponse
     */
    public function projectAssessmentsApiAction(): JsonResponse
    {
        return $this->getFormattedApiResponse([
            'project' => $this->project->getDomain(),
            'assessments' => $this->project->getPageAssessments()->getConfig(),
        ]);
    }

    /**
     * Get assessment data for all projects.
     * @Route("/api/project/assessments", name="ApiAssessmentsConfig")
     * @return JsonResponse
     */
    public function assessmentsConfigApiAction(): JsonResponse
    {
        // Here there is no Project, so we don't use XtoolsController::getFormattedApiResponse().
        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);
        $response->setStatusCode(Response::HTTP_OK);
        $response->setData([
            'projects' => array_keys($this->getParameter('assessments')),
            'config' => $this->getParameter('assessments'),
        ]);

        return $response;
    }

    /**
     * Transform given wikitext to HTML using the XTools parser. Wikitext must be passed in as the query 'wikitext'.
     * @Route("/api/project/parser/{project}")
     * @return JsonResponse Safe HTML.
     */
    public function wikifyApiAction(): JsonResponse
    {
        return new JsonResponse(
            Edit::wikifyString($this->request->query->get('wikitext', ''), $this->project)
        );
    }
}
