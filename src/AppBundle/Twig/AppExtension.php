<?php

namespace AppBundle\Twig;

class AppExtension extends \Twig_Extension
{
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('request_time', [$this, 'requestTime'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('memory_usage', [$this, 'requestMemory'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('peak_memory', [$this, 'requestPeakMemory'], ['is_safe' => ['html']]),
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

    public function getName()
    {
        return 'app_extension';
    }
}