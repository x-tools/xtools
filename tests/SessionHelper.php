<?php

declare( strict_types = 1 );

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;

/**
 * Use this trait whenever session data is required in a test.
 *
 * Usage:
 *      use SessionHelper;
 *      …
 *      $client = static::createClient();
 *      $session = $this->createSession($client);
 *
 * If you need to work a RequestStack, the session must also be set on the Request:
 *      $requestStack = $this->getRequestStack($session);
 */
trait SessionHelper {
	/**
	 * Create and get a new session object.
	 * Code courtesy of marien-probesys on GitHub. Unlicensed but used with permission.
	 * @see https://github.com/symfony/symfony/discussions/45662
	 * @param KernelBrowser $client
	 * @return Session
	 */
	public function createSession( KernelBrowser $client ): Session {
		$container = $client->getContainer();
		$sessionSavePath = $container->getParameter( 'session.save_path' );
		$sessionStorage = new MockFileSessionStorage( $sessionSavePath );

		$session = new Session( $sessionStorage );
		$session->start();
		$session->save();

		$sessionCookie = new Cookie(
			$session->getName(),
			$session->getId(),
			null,
			null,
			'localhost',
		);
		$client->getCookieJar()->set( $sessionCookie );

		return $session;
	}

	/**
	 * Get a RequestStack with the Session object set.
	 * @param Session $session
	 * @param array $requestParams
	 * @return RequestStack
	 */
	public function getRequestStack( Session $session, array $requestParams = [] ): RequestStack {
		/** @var RequestStack $requestStack */
		$requestStack = static::getContainer()->get( 'request_stack' );
		$request = new Request( $requestParams );
		$request->setSession( $session );
		$requestStack->push( $request );
		return $requestStack;
	}
}
