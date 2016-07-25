<?php

namespace AppBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use \Intuition;

class AppExtension extends \Twig_Extension
{
    private $intuition;
    private $container;
    private $request;
    private $lang;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
        $this->intuition = new Intuition();

        //$this->intuition->refreshLang();

        $this->intuition->loadTextdomainFromFile( $this->container->getParameter("kernel.root_dir") . '/i18n', "xtools" );

        /*dump(
            $this->intuition->getDomainInfo( 'xtools' )
        );

        dump($this->intuition->listMsgs('xtools'));*/

        $this->request = Request::createFromGlobals();

        $useLang = $this->request->query->get('uselang');

        $useLang = strtolower($useLang);

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
            new \Twig_SimpleFunction('msgPrintExists', [$this, 'intuitionMessagePrintExists'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('msgExists', [$this, 'intuitionMessageExists'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('msg', [$this, 'intuitionMessage'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('msg_footer', [$this, 'intuitionMessageFooter'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('lang', [$this, 'getLang'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('allLangs', [$this, 'getAllLangs']),
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

        $this->lang = strtolower($this->lang);

        if($this->lang != "en") $page .= "?uselang=" . $this->lang;

        return "<a href='$path/$page' class='$class'>$text</a>";
    }

    public function generateYear() {
        return date('Y');
    }

    public function intuitionMessageExists($message = "") {
        return $this->intuition->msgExists( $message , array("domain"=>"xtools"));
    }

    public function intuitionMessagePrintExists($message = "", $vars=[]) {
        if ($this->intuitionMessageExists($message)) {
            return $this->intuitionMessage($message, $vars);
        }
        else {
            return $message;
        }
    }

    public function intuitionMessage($message = "", $vars=[]) {
        return $this->intuition->msg( $message , array("domain"=>"xtools", "variables"=>$vars));
    }

    public function intuitionMessageFooter() {
        return $this->intuition->getFooterLine( TSINT_HELP_NONE );
    }

    public function getLang()  {
        return $this->lang;
    }

    public function getAllLangs() {
        return $this->intuition->generateLanguageList();
    }

    public function getName()
    {
        return 'app_extension';
    }
}