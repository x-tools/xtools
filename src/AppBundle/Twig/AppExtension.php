<?php

namespace AppBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;

class AppExtension extends \Twig_Extension
{
    private $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('request_time', [$this, 'requestTime'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('memory_usage', [$this, 'requestMemory'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('peak_memory', [$this, 'requestPeakMemory'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('link', [$this, 'generateLink'], ['is_safe' => ['html']]),
        ];
    }

    public function requestTime($decimals = 3)
    {
        return number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], $decimals);
    }

    public function requestMemory() {
        return 0;
    }

    public function requestPeakMemory() {
        return 0;
    }

    public function generateLink($page, $text, $class = "") {
        $path = $this->container->getParameter('web.path');
        return "<a href='$path/$page' class='$class'>$text</a>";
    }

    public function getName()
    {
        return 'app_extension';
    }
}