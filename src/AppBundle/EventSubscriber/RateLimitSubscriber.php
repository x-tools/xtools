<?php
/**
 * This file contains only the RateLimitSubscriber class.
 */

declare(strict_types = 1);

namespace AppBundle\EventSubscriber;

use AppBundle\Controller\XtoolsController;
use AppBundle\Helper\I18nHelper;
use DateInterval;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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

    /** @var int Number of minutes during which $rateLimit requests are permitted. */
    protected $rateDuration;

    /** @var \Symfony\Component\Cache\Adapter\TraceableAdapter Cache adapter. */
    protected $cache;

    /** @var Request The Request object. */
    protected $request;

    /** @var string User agent string. */
    protected $userAgent;

    /** @var string The referer string. */
    protected $referer;

    /** @var string The URI. */
    protected $uri;

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
     * @param ControllerEvent $event The event.
     */
    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        $action = null;

        // when a controller class defines multiple action methods, the controller
        // is returned as [$controllerInstance, 'methodName']
        if (is_array($controller)) {
            [$controller, $action] = $controller;
        }

        if (!$controller instanceof XtoolsController) {
            return;
        }

        $this->cache = $this->container->get('cache.app');
        $this->rateLimit = (int)$this->container->getParameter('app.rate_limit_count');
        $this->rateDuration = (int)$this->container->getParameter('app.rate_limit_time');
        $this->request = $event->getRequest();
        $this->userAgent = (string)$this->request->headers->get('User-Agent');
        $this->referer = (string)$this->request->headers->get('referer');
        $this->uri = $this->request->getRequestUri();

        $this->checkBlacklist();

        // Zero values indicate the rate limiting feature should be disabled.
        if (0 === $this->rateLimit || 0 === $this->rateDuration) {
            return;
        }

        $loggedIn = (bool)$this->container->get('session')->get('logged_in_user');
        $isApi = 'ApiAction' === substr($action, -9);

        /**
         * Rate limiting will not apply to these actions
         * @var array
         */
        $actionWhitelist = [
            'indexAction', 'showAction', 'aboutAction', 'loginAction', 'recordUsageAction', 'oauthCallbackAction',
        ];

        // No rate limits on lightweight pages, logged in users, subrequests or API requests.
        if (in_array($action, $actionWhitelist) || $loggedIn || false === $event->isMasterRequest() || $isApi) {
            return;
        }

        $this->logCrawlers();
        $this->sessionRateLimit();
    }

    /**
     * Don't let individual users hog up all the resources.
     */
    private function sessionRateLimit(): void
    {
        $sessionId = $this->request->getSession()->getId();
        $cacheKey = "ratelimit.session.$sessionId";
        $cacheItem = $this->cache->getItem($cacheKey);

        // If increment value already in cache, or start with 1.
        $count = $cacheItem->isHit() ? (int) $cacheItem->get() + 1 : 1;

        // Check if limit has been exceeded, and if so, throw an error.
        if ($count > $this->rateLimit) {
            $this->denyAccess('Exceeded rate limitation');
        }

        // Reset the clock on every request.
        $cacheItem->set($count)
            ->expiresAfter(new DateInterval('PT'.$this->rateDuration.'M'));
        $this->cache->save($cacheItem);
    }

    /**
     * Detect possible web crawlers and log the requests, and log them to /var/logs/crawlers.log.
     * Crawlers typically click on every visible link on the page, so we check for rapid requests to the same URI
     * but with a different interface language, as happens when it is crawling the language dropdown in the UI.
     */
    private function logCrawlers(): void
    {
        $useLangMatches = [];
        $hasMatch = preg_match('/\?uselang=(.*)/', $this->uri, $useLangMatches);

        if (1 !== $hasMatch) {
            return;
        }

        $useLang = $useLangMatches[1];

        // Requesting a language that's different than that of the target project.
        if (1 === preg_match("/[=\/]$useLang.wik/", $this->uri)) {
            return;
        }

        // We're trying to check if everything BUT the uselang has remained unchanged.
        $cacheUri = str_replace('uselang='.$useLang, '', $this->uri);
        $cacheKey = 'ratelimit.crawler.'.md5($this->userAgent.$cacheUri);
        $cacheItem = $this->cache->getItem($cacheKey);

        // If increment value already in cache, or start with 1.
        $count = $cacheItem->isHit() ? (int)$cacheItem->get() + 1 : 1;

        // Check if limit has been exceeded, and if so, add a log entry.
        if ($count > 3) {
            $logger = $this->container->get('monolog.logger.crawler');
            $logger->info('Possible crawler detected');
        }

        // Reset the clock on every request.
        $cacheItem->set($count)
            ->expiresAfter(new DateInterval('PT1M'));
        $this->cache->save($cacheItem);
    }

    /**
     * Check the request against blacklisted URIs and user agents
     */
    private function checkBlacklist(): void
    {
        // First check user agent and URI blacklists
        if (!$this->container->hasParameter('request_blacklist')) {
            return;
        }

        $blacklist = (array)$this->container->getParameter('request_blacklist');

        foreach ($blacklist as $name => $item) {
            $matches = [];

            if (isset($item['user_agent'])) {
                $matches[] = $item['user_agent'] === $this->userAgent;
            }
            if (isset($item['user_agent_pattern'])) {
                $matches[] = 1 === preg_match('/'.$item['user_agent_pattern'].'/', $this->userAgent);
            }
            if (isset($item['referer'])) {
                $matches[] = $item['referer'] === $this->referer;
            }
            if (isset($item['referer_pattern'])) {
                $matches[] = 1 === preg_match('/'.$item['referer_pattern'].'/', $this->referer);
            }
            if (isset($item['uri'])) {
                $matches[] = $item['uri'] === $this->uri;
            }
            if (isset($item['uri_pattern'])) {
                $matches[] = 1 === preg_match('/'.$item['uri_pattern'].'/', $this->uri);
            }

            if (count($matches) > 0 && count($matches) === count(array_filter($matches))) {
                $this->denyAccess("Matched blacklist entry `$name`", true);
            }
        }
    }

    /**
     * Throw exception for denied access due to spider crawl or hitting usage limits.
     * @param string $logComment Comment to include with the log entry.
     * @param bool $blacklist Changes the messaging to say access was denied due to abuse, rather than rate limiting.
     * @throws TooManyRequestsHttpException
     * @throws AccessDeniedHttpException
     */
    private function denyAccess(string $logComment, bool $blacklist = false): void
    {
        // Log the denied request
        $logger = $this->container->get($blacklist ? 'monolog.logger.blacklist' : 'monolog.logger.rate_limit');
        $logger->info($logComment);

        if ($blacklist) {
            $message = $this->i18n->msg('error-denied', ['tools.xtools@tools.wmflabs.org']);
            throw new AccessDeniedHttpException($message, null, 999);
        }

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
