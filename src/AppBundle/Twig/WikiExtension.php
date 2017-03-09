<?php

namespace AppBundle\Twig;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use \Intuition;

class WikiExtension extends \Twig_Extension {
    private $intuition;
    private $container;
    private $request;
    private $session;
    private $lang;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;

        $path = $this->container->getParameter("kernel.root_dir") . '/../i18n';

        if ( !file_exists( "$path/en.json" ) ) {
            throw new Exception("Language directory doesn't exist: $path");
        }

        $this->intuition = new Intuition( 'xtools' );
        $this->intuition->registerDomain( 'xtools', $path );

        $this->request = Request::createFromGlobals();
        $this->session = new Session();

        $useLang = "en";

        $query = $this->request->query->get('uselang');
        $cookie = $this->session->get("lang");

        if ( $query !== "" ) {
            $useLang = $query;
        }
        else if ( $cookie !== "" ) {
            $useLang = $cookie;
        }
        else {
            // Do nothing... it'll default to en.
        }

        $useLang = strtolower( $useLang );

        $this->intuition->setLang( $useLang );

        $this->lang = $useLang;

        if ( $cookie !== $useLang ) {
            $this->session->set( "lang", $useLang );
        }
    }

    public function getName() {
        return 'wiki_extension';
    }

    public function intuitionMessage( $message = "", $vars = [] ) {
        return $this->intuition->msg( $message , [ "domain" => "xtools", "variables" => $vars ] );
    }

    /*********************************** FUNCTIONS ***********************************/

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction( 'wiki_link', [$this, 'wiki_link'], ['is_safe' => ['html']] ),
            new \Twig_SimpleFunction( 'user_link', [$this, 'user_link'], ['is_safe' => ['html']] ),
            new \Twig_SimpleFunction( 'wiki_history_link', [$this, 'wiki_history_link'], ['is_safe' => ['html']] ),
            new \Twig_SimpleFunction( 'wiki_log_link', [$this, 'wiki_log_link'], ['is_safe' => ['html']] ),
            new \Twig_SimpleFunction( 'pageviews_links', [$this, 'pageviews_links'], ['is_safe' => ['html']] ),
            new \Twig_SimpleFunction( 'diff_link', [$this, 'diff_link'], ['is_safe' => ['html']] ),
        ];
    }

    /**
     * Get a link to the given page
     * @param  string $title      Title of page
     * @param  string $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string [$label]    The link text, defaults to $title
     * @return string Markup
     */
    public function wiki_link( $title, $projectUrl, $label = null ) {
        if (!$label) {
            $label = $title;
        }
        return "<a href='$projectUrl/wiki/$title' target='_blank'>$label</a>";
    }

    /**
     * Get a link to the given user's userpage, or to Special:Contribs if $username is an IP
     * @param  string $username   Username
     * @param  string $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string [$label]    The link text, defaults to $username
     * @return string Markup
     */
    public function user_link( $username, $projectUrl, $label = null ) {
        if (!$label) {
            $label = $username;
        }
        if (filter_var( $username, FILTER_VALIDATE_IP) ) {
            $link = "Special:Contributions/$username";
        } else {
            $link = "User:$username";
        }
        return "<a href='$projectUrl/wiki/$link' target='_blank'>$label</a>";
    }

    /**
     * Get a link to the revision history for given page
     * @param  string $title      Title of page
     * @param  string $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string [$label]    The link text, defaults to msg('history')
     * @return string Markup
     */
    public function wiki_history_link( $title, $projectUrl, $label = null ) {
        if ( !isset( $label ) ) {
            $label = $this->intuitionMessage( 'history' );
        }
        return "<a href='$projectUrl/w/index.php?title=$title&action=history' target='_blank'>$label</a>";
    }

    /**
     * Get a link to the logs for given page
     * @param  string $title      Title of page
     * @param  string $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string [$label]    The link text, defaults to msg('log')
     * @return string Markup
     */
    public function wiki_log_link( $title, $projectUrl, $label = null ) {
        if ( !isset( $label ) ) {
            $label = $this->intuitionMessage( 'log' );
        }
        return "<a href='$projectUrl/w/index.php?title=Special:Log&action=view&page=$title' target='_blank'>$label</a>";
    }

    /**
     * Get a link to the given diff, optionally with a timestamp
     * @param  number                   $diff       Revision ID
     * @param  string                   $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string|Integer|DateTime  [$label]    The link text, if a number is assumed to be UNIX timestamp,
     *                                              and will be converted to 'Y-m-d H:m'
     * @return string Markup
     */
    public function diff_link( $diff, $projectUrl, $label = '' ) {
        if ( is_int( $label ) ) {
            $label = date( 'Y-m-d, H:i', $label );
        } elseif ( is_a($label, 'DateTime') ) {
            $label = date_format( $label, 'Y-m-d, H:i' );
        }
        return "<a href='$projectUrl/wiki/Special:Diff/$diff' target='_blank'>$label</a>";
    }

    /**
     * Get links to pageviews tools for the given page
     * @param  string $title      Title of page
     * @param  string $projectUrl Project domain such as en.wikipedia.org
     * @return string Markup
     */
    public function pageviews_links( $title, $project ) {
        $title = str_replace( ' ', '_', $title );
        // FIXME: i18n
        $markup = "<a target='_blank' href='http://tools.wmflabs.org/pageviews/?project=$project&pages=$title'>Pageviews</a>";
        $markup .= " (";
        $markup .= "<a target='_blank' href='http://tools.wmflabs.org/langviews/?project=$project&page=$title'>all languages</a>";
        $markup .= " &middot ";
        $markup .= "<a target='_blank' href='http://tools.wmflabs.org/redirectviews/?project=$project&page=$title'>redirects</a>)";
        return $markup;
    }

    /*********************************** FILTERS ***********************************/

    public function getFilters() {
        return [
            new \Twig_SimpleFilter( 'percent_format', [$this, 'percent_format'] ),
            new \Twig_SimpleFilter( 'diff_format', [$this, 'diff_format'], ['is_safe' => ['html']] ),
        ];
    }

    /**
     * Format a given number as a diff, colouring it green if it's postive, red if negative, gary if zero
     * @param  number $size Diff size
     * @return string       Markup with formatted number
     */
    public function diff_format( $size ) {
        if ( $size < 0 ) {
            $class = 'diff-neg';
        } else if ( $size > 0 ) {
            $class = 'diff-pos';
        } else {
            $class = 'diff-zero';
        }

        $size = number_format( $size );

        return "<span class='$class'>$size</span>";
    }

    /**
     * Format a given number or fraction as a percentage
     * @param  number  $numerator     Numerator or single fraction if denominator is ommitted
     * @param  number  [$denominator] Denominator
     * @param  integer [$precision]   Number of decimal places to show
     * @return string                 Formatted percentage
     */
    public function percent_format( $numerator, $denominator = null, $precision = 1 ) {
        if ( !$denominator ) {
            $quotient = 0;
        } else {
            $quotient = ( $numerator / $denominator ) * 100;
        }

        return round( $quotient, $precision ) . '%';
    }
}
