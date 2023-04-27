<?php

declare(strict_types = 1);

namespace App\EventSubscriber;

use App\Exception\XtoolsHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;
use Twig\Environment;
use Twig\Error\RuntimeError;

/**
 * A ExceptionListener ensures Twig exceptions are properly
 * handled, so that a friendly error page is shown to the user.
 */
class ExceptionListener
{
    protected Environment $templateEngine;
    protected LoggerInterface $logger;

    /** @var string The environment. */
    protected string $environment;

    /**
     * Constructor for the ExceptionListener.
     * @param Environment $templateEngine
     * @param LoggerInterface $logger
     * @param string $environment
     */
    public function __construct(Environment $templateEngine, LoggerInterface $logger, string $environment = 'prod')
    {
        $this->templateEngine = $templateEngine;
        $this->logger = $logger;
        $this->environment = $environment;
    }

    /**
     * Capture the exception, check if it's a Twig error and if so
     * throw the previous exception, which should be more meaningful.
     * @param ExceptionEvent $event
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // We only care about the previous (original) exception, not the one Twig put on top of it.
        $prevException = $exception->getPrevious();

        if ($exception instanceof XtoolsHttpException) {
            $response = $this->getXtoolsHttpResponse($exception);
        } elseif ($exception instanceof RuntimeError && null !== $prevException) {
            $response = $this->getTwigErrorResponse($prevException);
        } elseif ($exception instanceof AccessDeniedHttpException) {
            // FIXME: For some reason the automatic error page rendering doesn't work for 403 responses...
            $response = new Response(
                $this->templateEngine->render('bundles/TwigBundle/Exception/error.html.twig', [
                    'status_code' => $exception->getStatusCode(),
                    'status_text' => 'Forbidden',
                    'exception' => $exception,
                ])
            );
        } else {
            return;
        }

        // sends the modified response object to the event
        $event->setResponse($response);
    }

    /**
     * Handle an XtoolsHttpException, either redirecting back to the configured URL,
     * or in the case of API requests, return the error in a JsonResponse.
     * @param XtoolsHttpException $exception
     * @return JsonResponse|RedirectResponse
     */
    private function getXtoolsHttpResponse(XtoolsHttpException $exception)
    {
        if ($exception->isApi()) {
            return new JsonResponse(array_merge(
                ['error' => $exception->getMessage()],
                $exception->getParams()
            ), $exception->getStatusCode());
        }

        return new RedirectResponse($exception->getRedirectUrl());
    }

    /**
     * Handle a Twig runtime exception.
     * @param Throwable $exception
     * @return Response
     * @throws Throwable
     */
    private function getTwigErrorResponse(Throwable $exception): Response
    {
        if ('prod' !== $this->environment) {
            throw $exception;
        }

        // Log the exception, since we're handling it and it won't automatically be logged.
        $file = explode('/', $exception->getFile());
        $this->logger->error(
            '>>> CRITICAL (\''.$exception->getMessage().'\' - '.
            end($file).' - line '.$exception->getLine().')'
        );

        return new Response(
            $this->templateEngine->render('bundles/TwigBundle/Exception/error.html.twig', [
                'status_code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'status_text' => 'Internal Server Error',
                'exception' => $exception,
            ]),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }
}
