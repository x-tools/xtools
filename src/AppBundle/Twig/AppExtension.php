<?php

namespace AppBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class AppExtension extends \Twig_Extension
{
    private $intuition;
    private $container;
    private $request;
    private $lang;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
        $this->intuition = new \Intuition();
        $this->intuition->loadTextdomainFromFile( $this->container->getParameter("kernel.root_dir") . '/i18n', "xtools" );

        /*dump(
            $this->intuition->getDomainInfo( 'xtools' )
        );

        dump($this->intuition->listMsgs('xtools'));*/

        $this->request = Request::createFromGlobals();

        $useLang = $this->request->query->get('uselang');

        if ($useLang == "") $useLang = "en";

        $this->lang = $useLang;

        $this->intuition->setLang($useLang);
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('request_time', [$this, 'requestTime'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('memory_usage', [$this, 'requestMemory'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('link', [$this, 'generateLink'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('year', [$this, 'generateYear'], ['is_save' => ['html']]),
            new \Twig_SimpleFunction('msg', [$this, 'intuitionMessage'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('msg_footer', [$this, 'intuitionMessageFooter'], ['is_safe' => ['html']]),
        ];
    }

    public function requestTime($decimals = 3)
    {
        return number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], $decimals);
    }

    public function requestMemory() {
        return 0;
    }

    public function generateLink($page, $text, $class = "") {
        $path = $this->container->getParameter('web.path');

        if($this->lang != "en") $page .= "?uselang=" . $this->lang;

        return "<a href='$path/$page' class='$class'>$text</a>";
    }

    public function generateYear() {
        return date('Y');
    }

    public function intuitionMessage($message = "", $vars=[]) {
        return $this->intuition->msg( $message , array("domain"=>"xtools", "variables"=>$vars));
    }

    public function intuitionMessageFooter() {
        return $this->intuition->getFooterLine( TSINT_HELP_NONE );
    }

    public function getName()
    {
        return 'app_extension';
    }
}