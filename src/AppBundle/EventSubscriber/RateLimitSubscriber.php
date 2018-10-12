<?php
/**
 * This file contains only the RateLimitSubscriber class.
 */

declare(strict_types = 1);

namespace AppBundle\EventSubscriber;

use DateInterval;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A RateLimitSubscriber checks to see if users are exceeding usage limitations.
 */
class RateLimitSubscriber implements EventSubscriberInterface
{

    /** @var ContainerInterface The DI container. */
    protected $container;

    /** @var int Number of requests allowed in time period */
    protected $rateLimit;

    /** @var int Number of minutes during which $rateLimit requests are permitted */
    protected $rateDuration;

    /**
     * Save the container for later use.
     * @param ContainerInterface $container The DI container.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
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
        $actionWhitelist = ['indexAction', 'showAction', 'aboutAction'];

        // No rate limits on lightweight pages or if they are logged in.
        if (in_array($controller[1], $actionWhitelist) || $loggedIn) {
            return;
        }

        $request = $event->getRequest();

        $this->checkBlacklist($request);

        // Build and fetch cache key based on session ID and requested URI,
        //   removing any reserved characters from URI.
        $uri = preg_replace('/[^a-zA-Z0-9,\.]/', '', $request->getRequestUri());
        $sessionId = $request->getSession()->getId();
        $cacheKey = 'ratelimit.'.$sessionId.'.'.$uri;
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
                    if (false !== strpos($request->headers->get('User-Agent'), $ua)) {
                        $this->denyAccess($request, "Matched blacklisted user agent `$ua`");
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
     * @throws TooManyRequestsHttpException
     * @todo i18n
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

        throw new TooManyRequestsHttpException(600, 'error-rate-limit', null, $this->rateDuration);
    }
}
