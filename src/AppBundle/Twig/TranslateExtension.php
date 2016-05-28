<?php

namespace AppBundle\Twig;

class TranslateExtension extends \Twig_Extension
{
    public function getGlobals()
    {
        
    }

    public function getFunctions()
    {
        return [
            //new \Twig_SimpleFunction('request_time', [$this, 'requestTime'], ['is_safe' => ['html']]),
            // Register the below functions here
        ];
    }

    // Function to return translation strings here

    public function getName()
    {
        return 'translate_extension';
    }
}