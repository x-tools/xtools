<?php
/**
 * This file contains only the DefaultController class.
 */

namespace AppBundle\Controller;

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xtools\ProjectRepository;

/**
 * The DefaultController handles the homepage, about pages, and user authentication.
 */
class DefaultController extends XtoolsController
{
    /** @var Client The Oauth HTTP client. */
    protected $oauthClient;

    /**
     * Display the homepage.
     * @Route("/", name="homepage")
     * @Route("/index.php", name="homepageIndexPhp")
     */
    public function indexAction()
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            'xtPage' => 'home',
        ]);
    }

    /**
     * Diplay XTools' about page.
     * @Route("/about", name="aboutPage")
     * @Route("/info.php", name="info")
     */
    public function aboutAction()
    {
        return $this->render('default/about.html.twig', [
            'xtPage' => 'about',
        ]);
    }

    /**
     * Display some configuration details, when in development mode.
     * @Route("/config", name="configPage")
     * @codeCoverageIgnore
     */
    public function configAction()
    {

        if ($this->container->getParameter('kernel.environment') !== 'dev') {
            throw new NotFoundHttpException();
        }

        $params = $this->container->getParameterBag()->all();

        foreach ($params as $key => $value) {
            if (strpos($key, 'password') !== false) {
                $params[$key] = '<REDACTED>';
            }
        }

        // replace this example code with whatever you need
        return $this->render('default/config.html.twig', [
            'xtTitle' => 'Config',
            'xtPageTitle' => 'Config',
            'xtPage' => 'index',
            'dump' => print_r($params, true),
        ]);
    }

    /**
     * Redirect to the default project (or Meta) for Oauth authentication.
     * @Route("/login", name="login")
     * @return RedirectResponse
     * @throws Exception If initialization fails.
     */
    public function loginAction()
    {
        try {
            list( $next, $token ) = $this->getOauthClient()->initiate();
        } catch (Exception $oauthException) {
            throw $oauthException;
            // @TODO Make this work.
            //$this->addFlash('error', $oauthException->getMessage());
            //return $this->redirectToRoute('homepage');
        }

        // Save the request token to the session.
        /** @var Session $session */
        $session = $this->get('session');
        $session->set('oauth_request_token', $token);
        return new RedirectResponse($next);
    }

    /**
     * Receive authentication credentials back from the Oauth wiki.
     * @Route("/oauth_callback", name="oauth_callback")
     * @Route("/oauthredirector.php", name="old_oauth_callback")
     * @param Request $request The HTTP request.
     * @return RedirectResponse
     */
    public function oauthCallbackAction(Request $request)
    {
        // Give up if the required GET params don't exist.
        if (!$request->get('oauth_verifier')) {
            throw $this->createNotFoundException('No OAuth verifier given.');
        }

        /** @var Session $session */
        $session = $this->get('session');

        // Complete authentication.
        $client = $this->getOauthClient();
        $token = $session->get('oauth_request_token');
        $verifier = $request->get('oauth_verifier');
        $accessToken = $client->complete($token, $verifier);

        // Store access token, and remove request token.
        $session->set('oauth_access_token', $accessToken);
        $session->remove('oauth_request_token');

        // Store user identity.
        $ident = $client->identify($accessToken);
        $session->set('logged_in_user', $ident);

        // Send back to homepage.
        return $this->redirectToRoute('homepage');
    }

    /**
     * Get an OAuth client, configured to the default project.
     * (This shouldn't really be in this class, but oh well.)
     * @return Client
     * @codeCoverageIgnore
     */
    protected function getOauthClient()
    {
        if ($this->oauthClient instanceof Client) {
            return $this->oauthClient;
        }
        $defaultProject = ProjectRepository::getDefaultProject($this->container);
        $endpoint = $defaultProject->getUrl(false)
                    . $defaultProject->getScript()
                    . '?title=Special:OAuth';
        $conf = new ClientConfig($endpoint);
        $consumerKey = $this->getParameter('oauth_key');
        $consumerSecret =  $this->getParameter('oauth_secret');
        $conf->setConsumer(new Consumer($consumerKey, $consumerSecret));
        $this->oauthClient = new Client($conf);
        // Callback URL is hardcoded in the consumer registration.
        $this->oauthClient->setCallback('oob');
        return $this->oauthClient;
    }

    /**
     * Log out the user and return to the homepage.
     * @Route("/logout", name="logout")
     */
    public function logoutAction()
    {
        $this->get('session')->invalidate();
        return $this->redirectToRoute('homepage');
    }
}
