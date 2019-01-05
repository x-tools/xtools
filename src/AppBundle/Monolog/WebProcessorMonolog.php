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

        if ($request) {
            $record['extra']['host'] = $request->getHost();
            $record['extra']['uri'] = $request->getUri();
            $record['extra']['useragent'] = $request->headers->get('User-Agent');

            $session = $request->getSession();

            if (null === $session || !$session->isStarted()) {
                return $record;
            }

            if (!$this->sessionId) {
                $this->sessionId = substr($session->getId(), 0, 8) ?: '????????';
            }

            $record['extra']['token'] = $this->sessionId.'-'.substr(uniqid('', true), -8);
        }

        return $record;
    }
}
