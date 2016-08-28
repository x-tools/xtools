<?php

namespace AppBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use \Intuition;

class AppExtension extends \Twig_Extension
{
    private $intuition;
    private $container;
    private $request;
    private $session;
    private $lang;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
        $this->intuition = new Intuition();

        $this->intuition->loadTextdomainFromFile( $this->container->getParameter("kernel.root_dir") . '/i18n', "xtools" );

        $this->request = Request::createFromGlobals();
        $this->session = new Session();

        $useLang = "en";

        $query = $this->request->query->get('uselang');
        $cookie = $this->session->get("lang");

        if ($query != "") {
            $useLang = $query;
        }
        else if ($cookie != "") {
            $useLang = $cookie;
        }
        else {
            // Do nothing... it'll default to en.
        }

        $useLang = strtolower($useLang);

        $this->intuition->setLang($useLang);

        $this->lang = $useLang;

        if ($cookie != $useLang) {
            $this->session->set("lang", $useLang);
        }
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
            new \Twig_SimpleFunction('langName', [$this, 'getLangName'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('allLangs', [$this, 'getAllLangs']),
            new \Twig_SimpleFunction('shortHash', [$this, 'gitShortHash']),
            new \Twig_SimpleFunction('hash', [$this, 'gitHash']),
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

        //$this->lang = strtolower($this->lang);

        //if($this->lang != "en") $page .= "?uselang=" . $this->lang;

        return "<a href='$path/$page' class='$class'>$text</a>";
    }

    public function generateYear() {
        return date('Y');
    }

    public function intuitionMessageExists($message = "") {
        return $this->intuition->msgExists( $message , array("domain"=>"xtools"));
    }

    public function intuitionMessagePrintExists($message = "", $vars=[]) {
        if (is_array($message)) {
            $vars = $message;
            $message = $message[0];
            $vars = array_slice($vars, 1);
        }
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
        $message = $this->intuition->getFooterLine( TSINT_HELP_NONE );
        $message = str_replace("<a class=\"int-dashboardbacklink\" href=\"//tools.wmflabs.org/intuition/?returnto=%2Fapp_dev.php&amp;returntoquery=#tab-settingsform\" title=\"Change the interface language of this tool.\">Change language!</a>", "", $message);
        return $message;
    }

    public function getLang()  {
        return $this->lang;
    }

    public function getLangName()  {
        return in_array( $this->intuition->getLangName(), $this->getAllLangs() ) ? $this->intuition->getLangName() : 'English';
    }

    public function getAllLangs() {
        return $this->intuition->generateLanguageList();
    }

    public function gitShortHash() {
        return exec("git rev-parse --short HEAD");
    }

    public function gitHash() {
        return exec("git rev-parse HEAD");
    }

    public function getName()
    {
        return 'app_extension';
    }
}
