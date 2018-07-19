<?php
/**
 * This file contains only the ExceptionListener class.
 */

namespace AppBundle\EventSubscriber;

use AppBundle\Exception\XtoolsHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Templating\EngineInterface;
use Twig_Error_Runtime;

/**
 * A ExceptionListener ensures Twig exceptions are properly
 * handled, so that a friendly error page is shown to the user.
 */
class ExceptionListener
{
    /** @var EngineInterface For rendering the view. */
    private $templateEngine;

    /** @var LoggerInterface For logging the exception. */
    private $logger;

    /** @var string The environment. */
    private $environment;

    /**
     * Constructor for the ExceptionListener.
     * @param EngineInterface $templateEngine
     * @param LoggerInterface $logger
     * @param string $environment
     */
    public function __construct(EngineInterface $templateEngine, LoggerInterface $logger, $environment = 'prod')
    {
        $this->templateEngine = $templateEngine;
        $this->logger = $logger;
        $this->environment = $environment;
    }

    /**
     * Capture the exception, check if it's a Twig error and if so
     * throw the previous exception, which should be more meaningful.
     * @param GetResponseForExceptionEvent $event
     * @throws \Exception
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        // We only care about the previous (original) exception,
        // not the one Twig put on top of it.
        $prevException = $exception->getPrevious();

        if ($exception instanceof XtoolsHttpException) {
            $response = $this->getXtoolsHttpResponse($exception);
        } elseif ($exception instanceof Twig_Error_Runtime && $prevException !== null) {
            $response = $this->getTwigErrorReponse($prevException);
        } else {
            throw $exception;
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
            ), Response::HTTP_NOT_FOUND);
        }

        return new RedirectResponse($exception->getRedirectUrl());
    }

    /**
     * Handle a Twig runtime exception.
     * @param Twig_Error_Runtime $exception
     * @return Response
     * @throws Twig_Error_Runtime
     */
    private function getTwigErrorReponse($exception)
    {
        if ($this->environment !== 'prod') {
            throw $exception;
        }

        // Log the exception, since we're handling it and it won't automatically be logged.
        $file = explode('/', $exception->getFile());
        $this->logger->error(
            '>>> CRITICAL (\''.$exception->getMessage().'\' - '.
            end($file).' - line '.$exception->getLine().')'
        );

        return new Response(
            $this->templateEngine->render('TwigBundle:Exception:error.html.twig', [
                'status_code' => 500,
                'status_text' => 'Internal Server Error',
                'exception' => $exception,
            ])
        );
    }
}
