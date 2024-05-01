<?php

declare(strict_types = 1);

namespace App\EventSubscriber;

use App\Controller\XtoolsController;
use App\Helper\I18nHelper;
use DateInterval;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A RateLimitSubscriber checks to see if users are exceeding usage limitations.
 */
class RateLimitSubscriber implements EventSubscriberInterface
{
    /**
     * Rate limiting will not apply to these actions.
     */
    public const ACTION_ALLOWLIST = [
        'aboutAction',
        'indexAction',
        'loginAction',
        'oauthCallbackAction',
        'recordUsageAction',
        'showAction',
    ];

    /**
     * Maximum number of requests to the same URI with different interface languages.
     */
    public const MAX_CRAWLER_COUNT = 3;

    /**
     * Duration in which the max number of requests to the same URI with different interface languages is allowed.
     */
    public const MAX_CRAWLER_TIME = 'PT1M';

    protected CacheItemPoolInterface $cache;
    protected I18nHelper $i18n;
    protected LoggerInterface $crawlerLogger;
    protected LoggerInterface $denylistLogger;
    protected LoggerInterface $rateLimitLogger;
    protected ParameterBagInterface $parameterBag;
    protected Request $request;
    protected SessionInterface $session;

    /** @var int Number of requests allowed in time period */
    protected int $rateLimit;

    /** @var int Number of minutes during which $rateLimit requests are permitted. */
    protected int $rateDuration;

    /** @var string User agent string. */
    protected string $userAgent;

    /** @var string The referer string. */
    protected string $referer;

    /** @var string The URI. */
    protected string $uri;

    /**
     * @param I18nHelper $i18n
     * @param CacheItemPoolInterface $cache
     * @param ParameterBagInterface $parameterBag
     * @param RequestStack $requestStack
     * @param LoggerInterface $crawlerLogger
     * @param LoggerInterface $denylistLogger
     * @param LoggerInterface $rateLimitLogger
     * @param int $rateLimit
     * @param int $rateDuration
     */
    public function __construct(
        I18nHelper $i18n,
        CacheItemPoolInterface $cache,
        ParameterBagInterface $parameterBag,
        RequestStack $requestStack,
        LoggerInterface $crawlerLogger,
        LoggerInterface $denylistLogger,
        LoggerInterface $rateLimitLogger,
        int $rateLimit,
        int $rateDuration
    ) {
        $this->i18n = $i18n;
        $this->cache = $cache;
        $this->parameterBag = $parameterBag;
        $this->session = $requestStack->getSession();
        $this->crawlerLogger = $crawlerLogger;
        $this->denylistLogger = $denylistLogger;
        $this->rateLimitLogger = $rateLimitLogger;
        $this->rateLimit = $rateLimit;
        $this->rateDuration = $rateDuration;
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

        $this->request = $event->getRequest();
        $this->userAgent = (string)$this->request->headers->get('User-Agent');
        $this->referer = (string)$this->request->headers->get('referer');
        $this->uri = $this->request->getRequestUri();

        $this->checkDenylist();

        // Zero values indicate the rate limiting feature should be disabled.
        if (0 === $this->rateLimit || 0 === $this->rateDuration) {
            return;
        }

        $loggedIn = (bool)$this->session->get('logged_in_user');
        $isApi = 'ApiAction' === substr($action, -9);

        // No rate limits on lightweight pages, logged in users, subrequests or API requests.
        if (in_array($action, self::ACTION_ALLOWLIST) || $loggedIn || false === $event->isMainRequest() || $isApi) {
            return;
        }

        $this->logCrawlers();
        $this->xffRateLimit();
    }

    /**
     * Don't let individual users hog up all the resources.
     */
    private function xffRateLimit(): void
    {
        $xff = $this->request->headers->get('x-forwarded-for', '');

        if ('' === $xff) {
            // Happens in local environments, or outside of Cloud Services.
            return;
        }

        $cacheKey = "ratelimit.session.".sha1($xff);
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
        $cacheKey = 'ratelimit.crawler.'.sha1($this->userAgent.$cacheUri);
        $cacheItem = $this->cache->getItem($cacheKey);

        // If increment value already in cache, or start with 1.
        $count = $cacheItem->isHit() ? (int)$cacheItem->get() + 1 : 1;

        // Check if limit has been exceeded, and if so, add a log entry.
        if ($count > 3) {
            $this->crawlerLogger->info('Possible crawler detected');
        }

        // Reset the clock on every request.
        $cacheItem->set($count)
            ->expiresAfter(new DateInterval(self::MAX_CRAWLER_TIME));
        $this->cache->save($cacheItem);

        // If we've got a lot of hits, let's go ahead and assume it's a crawler and give a 429.
        if ($count > self::MAX_CRAWLER_COUNT) {
            $this->denyAccess('Web crawler detected');
        }
    }

    /**
     * Check the request against denylisted URIs and user agents
     */
    private function checkDenylist(): void
    {
        // First check user agent and URI denylists.
        if (!$this->parameterBag->has('request_denylist')) {
            return;
        }

        $denylist = (array)$this->parameterBag->get('request_denylist');

        foreach ($denylist as $name => $item) {
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
                $this->denyAccess("Matched denylist entry `$name`", true);
            }
        }
    }

    /**
     * Throw exception for denied access due to spider crawl or hitting usage limits.
     * @param string $logComment Comment to include with the log entry.
     * @param bool $denylist Changes the messaging to say access was denied due to abuse, rather than rate limiting.
     * @throws TooManyRequestsHttpException
     * @throws AccessDeniedHttpException
     */
    private function denyAccess(string $logComment, bool $denylist = false): void
    {
        // Log the denied request
        $logger = $denylist ? $this->denylistLogger : $this->rateLimitLogger;
        $logger->info($logComment);

        if ($denylist) {
            $message = $this->i18n->msg('error-denied', ['tools.xtools@toolforge.org']);
            throw new AccessDeniedHttpException($message, null, 999);
        }

        $message = $this->i18n->msg('error-rate-limit', [
            $this->rateDuration,
            "<a href='/login'>".$this->i18n->msg('error-rate-limit-login')."</a>",
            "<a href='https://www.mediawiki.org/wiki/Special:MyLanguage/XTools/API' target='_blank'>" .
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
