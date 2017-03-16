<?php

namespace AppBundle\Twig;

use Twig_SimpleFunction;

class WikiExtension extends Extension
{

    public function getName()
    {
        return 'wiki_extension';
    }

    public function intuitionMessage($message = "", $vars = [])
    {
        return $this->getIntuition()->msg($message, [ "domain" => "xtools", "variables" => $vars ]);
    }

    /*********************************** FUNCTIONS ***********************************/

    public function getFunctions()
    {
        $options = [ 'is_safe' => [ 'html']];
        return [
            new Twig_SimpleFunction('wiki_link', [ $this, 'wikiLink' ], $options),
            new Twig_SimpleFunction('user_link', [ $this, 'userLink' ], $options),
            new Twig_SimpleFunction('wiki_history_link', [ $this, 'wikiHistoryLink' ], $options),
            new Twig_SimpleFunction('wiki_log_link', [ $this, 'wikiLogLink' ], $options),
            new Twig_SimpleFunction('pageviews_links', [ $this, 'pageviewsLinks' ], $options),
            new Twig_SimpleFunction('diff_link', [ $this, 'diffLink' ], $options),
        ];
    }

    /**
     * Get a link to the given page
     * @param  string $title      Title of page
     * @param  string $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string [$label]    The link text, defaults to $title
     * @return string Markup
     */
    public function wikiLink($title, $projectUrl, $label = null)
    {
        if (!$label) {
            $label = $title;
        }
        $title = str_replace(' ', '_', $title);
        return "<a href='$projectUrl/wiki/$title' target='_blank'>$label</a>";
    }

    /**
     * Get a link to the given user's userpage, or to Special:Contribs if $username is an IP
     * @param  string $username   Username
     * @param  string $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string [$label]    The link text, defaults to $username
     * @return string Markup
     */
    public function userLink($username, $projectUrl, $label = null)
    {
        if (!$label) {
            $label = $username;
        }
        if (filter_var($username, FILTER_VALIDATE_IP)) {
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
     * @param  string [$offset]   Will to edits on or before this timestamp
     * @param  int    [$limit]    Show this number of results
     * @return string Markup
     */
    public function wikiHistoryLink($title, $projectUrl, $label = null, $offset = null, $limit = null)
    {
        if (!isset($label)) {
            $label = $this->intuitionMessage('history');
        }
        $url = "$projectUrl/w/index.php?title=$title&action=history";

        if ($offset) {
            $url .= "&offset=$offset";
        }
        if ($limit) {
            $url .= "&limit=$limit";
        }

        return "<a href='$url' target='_blank'>$label</a>";
    }

    /**
     * Get a link to the logs for given page
     * @param  string $title      Title of page
     * @param  string $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string [$label]    The link text, defaults to msg('log')
     * @return string Markup
     */
    public function wikiLogLink($title, $projectUrl, $label = null)
    {
        if (!isset($label)) {
            $label = $this->intuitionMessage('log');
        }
        $url = "$projectUrl/w/index.php?title=Special:Log&action=view&page=$title";
        return "<a href='$url' target='_blank'>$label</a>";
    }

    /**
     * Get a link to the given diff, optionally with a timestamp
     * @param  number                   $diff       Revision ID
     * @param  string                   $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string|Integer|DateTime  [$label]    The link text, if a number is assumed to be UNIX timestamp,
     *                                              and will be converted to 'Y-m-d H:m'
     * @return string Markup
     */
    public function diffLink($diff, $projectUrl, $label = '')
    {
        if (is_int($label)) {
            $label = date('Y-m-d, H:i', $label);
        } elseif (is_a($label, 'DateTime')) {
            $label = date_format($label, 'Y-m-d, H:i');
        }
        return "<a href='$projectUrl/wiki/Special:Diff/$diff' target='_blank'>$label</a>";
    }

    /**
     * Get links to pageviews tools for the given page
     * @param  string $title      Title of page
     * @param  string $projectUrl Project domain such as en.wikipedia.org
     * @return string Markup
     */
    public function pageviewsLinks($title, $project)
    {
        $title = str_replace(' ', '_', $title);
        // FIXME: i18n
        $pageviewsUrl = "http://tools.wmflabs.org/pageviews/?project=$project&pages=$title";
        $langviewsUrl = "http://tools.wmflabs.org/langviews/?project=$project&page=$title";
        $redirectsUrl = "http://tools.wmflabs.org/redirectviews/?project=$project&page=$title";
        $markup = "<a target='_blank' href='$pageviewsUrl'>Pageviews</a>";
        $markup .= " (";
        $markup .= "<a target='_blank' href='$langviewsUrl'>all languages</a>";
        $markup .= " &middot ";
        $markup .= "<a target='_blank' href='$redirectsUrl'>redirects</a>)";
        return $markup;
    }

    /*********************************** FILTERS ***********************************/

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('percent_format', [ $this, 'percentFormat' ]),
            new \Twig_SimpleFilter('diff_format', [ $this, 'diffFormat' ], [ 'is_safe' => [ 'html' ] ]),
        ];
    }

    /**
     * Format a given number as a diff, colouring it green if it's postive, red if negative, gary if zero
     * @param  number $size Diff size
     * @return string       Markup with formatted number
     */
    public function diffFormat($size)
    {
        if ($size < 0) {
            $class = 'diff-neg';
        } elseif ($size > 0) {
            $class = 'diff-pos';
        } else {
            $class = 'diff-zero';
        }

        $size = number_format($size);

        return "<span class='$class'>$size</span>";
    }

    /**
     * Format a given number or fraction as a percentage
     * @param  number  $numerator     Numerator or single fraction if denominator is ommitted
     * @param  number  [$denominator] Denominator
     * @param  integer [$precision]   Number of decimal places to show
     * @return string                 Formatted percentage
     */
    public function percentFormat($numerator, $denominator = null, $precision = 1)
    {
        if (!$denominator) {
            $quotient = 0;
        } else {
            $quotient = ( $numerator / $denominator ) * 100;
        }

        return round($quotient, $precision) . '%';
    }
}
