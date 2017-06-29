<?php
/**
 * This file contains only the DisabledToolSubscriber class.
 */

namespace AppBundle\EventSubscriber;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A DisabledToolSubscriber checks to see
 */
class DisabledToolSubscriber implements EventSubscriberInterface
{

    /** @var Container The DI container. */
    protected $container;

    /**
     * Save the container for later use.
     * @param Container $container The DI container.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register our interest in the kernel.controller event.
     * @return string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    /**
     * Check to see if the current tool is enabled.
     * @param FilterControllerEvent $event The event.
     * @throws NotFoundHttpException If the tool is not enabled.
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController();
        if (method_exists($controller[0], 'getToolShortname')) {
            $tool = $controller[0]->getToolShortname();
            if (!$this->container->getParameter("enable.$tool")) {
                throw new NotFoundHttpException('This tool is disabled');
            }
        }
    }
}
