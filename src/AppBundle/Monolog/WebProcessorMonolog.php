<?php
/**
 * This file contains only the WebProcessorMonolog class.
 */

declare(strict_types = 1);

namespace AppBundle\Monolog;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * WebProcessorMonolog extends information included in error reporting.
 */
class WebProcessorMonolog
{
    /** @var RequestStack The request stack. */
    private $requestStack;

    /** @var string The unique identifier for the session. */
    private $sessionId;

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
    public function processRecord(array $record): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request && $request->hasSession()) {
            $record['extra']['host'] = $request->getHost();
            $record['extra']['uri'] = $request->getUri();
            $record['extra']['useragent'] = $request->headers->get('User-Agent');
            $record['extra']['referer'] = $request->headers->get('referer');

            $session = $request->getSession();

            // Necessary to combat abuse.
            if (null !== $session->get('logged_in_user')) {
                $record['extra']['username'] = $session->get('logged_in_user')->username;
            } else {
                // Intentionally not included if we have a username, for privacy reasons.
                $record['extra']['xff'] = $request->headers->get('x-forwarded-for', '');
            }
        }

        return $record;
    }
}
