<?php

declare(strict_types = 1);

namespace App\Monolog;

use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * WebProcessorMonolog extends information included in error reporting.
 * @codeCoverageIgnore
 */
class WebProcessorMonolog
{
    protected RequestStack $requestStack;

    /**
     * WebProcessorMonolog constructor.
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Adds extra information to the log entry.
     * @param array $record
     * @return array
     */
    public function __invoke(array $record): array
    {
        try {
            $session = $this->requestStack->getSession();
        } catch (SessionNotFoundException $e) {
            return $record;
        }
        if (!$session->isStarted()) {
            return $record;
        }

        $request = $this->requestStack->getCurrentRequest();
        $record['extra']['host'] = $request->getHost();
        $record['extra']['uri'] = $request->getUri();
        $record['extra']['useragent'] = $request->headers->get('User-Agent');
        $record['extra']['referer'] = $request->headers->get('referer');

        // Necessary to combat abuse.
        if (null !== $session->get('logged_in_user')) {
            $record['extra']['username'] = $session->get('logged_in_user')->username;
        } else {
            // Intentionally not included if we have a username, for privacy reasons.
            $record['extra']['xff'] = $request->headers->get('x-forwarded-for', '');
        }

        return $record;
    }
}
