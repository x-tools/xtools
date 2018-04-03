<?php
/**
 * This file contains only the ExceptionListener class.
 */

namespace AppBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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

    /**
     * Constructor for the ExecptionListener.
     * @param EngineInterface $templateEngine
     * @param LoggerInterface $logger
     */
    public function __construct(EngineInterface $templateEngine, LoggerInterface $logger)
    {
        $this->templateEngine = $templateEngine;
        $this->logger = $logger;
    }

    /**
     * Capture the exception, check if it's a Twig error and if so
     * throw the previous exception, which should be more meaningful.
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        // We only care about the previous (original) exception,
        // not the one Twig put on top of it.
        $prevException = $exception->getPrevious();

        if (!($exception instanceof Twig_Error_Runtime && $prevException !== null)) {
            return;
        }

        // Log the exception, since we're handling it and it won't automatically be logged.
        $file = explode('/', $prevException->getFile());
        $this->logger->error(
            '>>> CRITICAL (\''.$prevException->getMessage().'\' - '.
            end($file).' - line '.$prevException->getLine().')'
        );

        $response = new Response(
            $this->templateEngine->render('TwigBundle:Exception:error.html.twig', [
                'status_code' => 500,
                'status_text' => 'testing',
                'exception' => $prevException,
            ])
        );

        // sends the modified response object to the event
        $event->setResponse($response);
    }
}
