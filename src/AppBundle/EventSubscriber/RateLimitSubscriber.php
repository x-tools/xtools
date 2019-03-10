<?php
/**
 * This file contains only the RateLimitSubscriber class.
 */

declare(strict_types = 1);

namespace AppBundle\EventSubscriber;

use AppBundle\Helper\I18nHelper;
use DateInterval;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A RateLimitSubscriber checks to see if users are exceeding usage limitations.
 */
class RateLimitSubscriber implements EventSubscriberInterface
{

    /** @var ContainerInterface The DI container. */
    protected $container;

    /** @var I18nHelper For i18n and l10n. */
    protected $i18n;

    /** @var int Number of requests allowed in time period */
    protected $rateLimit;

    /** @var int Number of minutes during which $rateLimit requests are permitted */
    protected $rateDuration;

    /**
     * Save the container for later use.
     * @param ContainerInterface $container The DI container.
     * @param I18nHelper $i18n
     */
    public function __construct(ContainerInterface $container, I18nHelper $i18n)
    {
        $this->container = $container;
        $this->i18n = $i18n;
    }

    /**
     * Register our interest in the kernel.controller event.
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    /**
     * Check if the current user has exceeded the configured usage limitations.
     * @param FilterControllerEvent $event The event.
     * @throws TooManyRequestsHttpException|\Exception If rate limits have been exceeded.
     */
    public function onKernelController(FilterControllerEvent $event): void
    {
        $this->rateLimit = (int) $this->container->getParameter('app.rate_limit_count');
        $this->rateDuration = (int) $this->container->getParameter('app.rate_limit_time');
        $request = $event->getRequest();

        // Zero values indicate the rate limiting feature should be disabled.
        if (0 === $this->rateLimit || 0 === $this->rateDuration) {
            return;
        }

        $controller = $event->getController();
        $loggedIn = (bool) $this->container->get('session')->get('logged_in_user');

        /**
         * Rate limiting will not apply to these actions
         * @var array
         */
        $actionWhitelist = [
            'indexAction', 'showAction', 'aboutAction', 'recordUsage', 'loginAction', 'oauthCallbackAction',
        ];

        // No rate limits on lightweight pages, logged in users, or subrequests.
        if (in_array($controller[1], $actionWhitelist) || $loggedIn || false === $event->isMasterRequest()) {
            return;
        }

        $this->checkBlacklist($request);

        // Build and fetch cache key based on session ID.
        $sessionId = $request->getSession()->getId();
        $cacheKey = 'ratelimit.'.$sessionId;

        /** @var \Symfony\Component\Cache\Adapter\TraceableAdapter $cache */
        $cache = $this->container->get('cache.app');
        $cacheItem = $cache->getItem($cacheKey);

        // If increment value already in cache, or start with 1.
        $count = $cacheItem->isHit() ? (int) $cacheItem->get() + 1 : 1;

        // Check if limit has been exceeded, and if so, throw an error.
        if ($count > $this->rateLimit) {
            $this->denyAccess($request, 'Exceeded rate limitation');
        }

        // Reset the clock on every request.
        $cacheItem->set($count)
            ->expiresAfter(new DateInterval('PT'.$this->rateDuration.'M'));
        $cache->save($cacheItem);
    }

    /**
     * Check the request against blacklisted URIs and user agents
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    private function checkBlacklist(\Symfony\Component\HttpFoundation\Request $request): void
    {
        // First check user agent and URI blacklists
        if ($this->container->hasParameter('request_blacklist')) {
            $blacklist = $this->container->getParameter('request_blacklist');
            // User agents
            if (is_array($blacklist['user_agent'])) {
                foreach ($blacklist['user_agent'] as $ua) {
                    if (false !== strpos((string)$request->headers->get('User-Agent'), $ua)) {
                        $logComment = "Matched blacklisted user agent `$ua`";

                        // Log the denied request
                        $logger = $this->container->get('monolog.logger.rate_limit');
                        $logger->info(
                            "<URI>: " . $request->getRequestUri() .
                            ('' != $logComment ? "\t<Reason>: $logComment" : '') .
                            "\t<User agent>: " . $request->headers->get('User-Agent')
                        );

                        throw new HttpException(
                            403,
                            'Your access to XTools has been revoked due to possible abuse. '.
                            'Please contact tools.xtools@tools.wmflabs.org'
                        );
                    }
                }
            }
            // URIs
            if (is_array($blacklist['uri'])) {
                foreach ($blacklist['uri'] as $uri) {
                    if (false !== strpos($request->getRequestUri(), $uri)) {
                        $this->denyAccess($request, "Matched blacklisted URI `$uri`");
                    }
                }
            }
        }
    }

    /**
     * Throw exception for denied access due to spider crawl or hitting usage limits.
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string $logComment Comment to include with the log entry.
     */
    private function denyAccess(\Symfony\Component\HttpFoundation\Request $request, string $logComment = ''): void
    {
        // Log the denied request
        $logger = $this->container->get('monolog.logger.rate_limit');
        $logger->info(
            "<URI>: " . $request->getRequestUri() .
            ('' != $logComment ? "\t<Reason>: $logComment" : '') .
            "\t<User agent>: " . $request->headers->get('User-Agent')
        );

        $message = $this->i18n->msg('error-rate-limit', [
            $this->rateDuration,
            "<a href='/login'>".$this->i18n->msg('error-rate-limit-login')."</a>",
            "<a href='https://xtools.readthedocs.io/en/stable/api' target='_blank'>" .
                $this->i18n->msg('api') .
            "</a>",
        ]);

        /**
         * TODO: Find a better way to do this.
         * 999 is a random, complete hack to tell error.html.twig file to treat these exceptions as having
         * fully safe messages that can be display with |raw. (In this case we authored the message).
         */
        throw new TooManyRequestsHttpException(600, $message, null, 999);
    }
}
