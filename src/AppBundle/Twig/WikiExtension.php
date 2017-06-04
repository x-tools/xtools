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
            new Twig_SimpleFunction('user_log_link', [ $this, 'userLogLink' ], $options),
            new Twig_SimpleFunction('group_link', [ $this, 'groupLink' ], $options),
            new Twig_SimpleFunction('wiki_history_link', [ $this, 'wikiHistoryLink' ], $options),
            new Twig_SimpleFunction('wiki_log_link', [ $this, 'wikiLogLink' ], $options),
            new Twig_SimpleFunction('pageviews_links', [ $this, 'pageviewsLinks' ], $options),
            new Twig_SimpleFunction('diff_link', [ $this, 'diffLink' ], $options),
            new Twig_SimpleFunction('perma_link', [ $this, 'permaLink' ], $options),
            new Twig_SimpleFunction('edit_link', [ $this, 'editLink' ], $options),
        ];
    }

    // FIXME: make projectUrl globally accessible so we don't have to keep passing it in to these functions
    //   Also do the same for the project language, and set lang= and dir= attributes accordingly

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
        $projectUrl = rtrim($projectUrl, '/');
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
        $link = str_replace(' ', '_', $link);
        $projectUrl = rtrim($projectUrl, '/');
        return "<a href='$projectUrl/wiki/$link' target='_blank'>$label</a>";
    }

    /**
     * Get a link to the given user's userpage, or to Special:Contribs if $username is an IP
     * @param  string $username   Username
     * @param  string $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string [$label]    The link text, defaults to $username
     * @return string Markup
     */
    public function groupLink($group, $projectUrl, $label = null)
    {
        if (!$label) {
            $label = $group;
        }
        $projectUrl = rtrim($projectUrl, '/');
        // Ignoring this inspection, as we want all of the output on one line.
        // @codingStandardsIgnoreStart
        return "<a href='$projectUrl/w/index.php?title=Special:ListUsers&group=$group&creationSort=1&limit=50' target='_blank'>$label</a>";
        // @codingStandardsIgnoreEnd
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
        $title = str_replace(' ', '_', $title);
        $projectUrl = rtrim($projectUrl, '/');
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
     * Get a link to the logs for given user
     * @param  string $username   Username
     * @param  string $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string [$label]    The link text, defaults to msg('log')
     * @param  string [$type]     Log type (e.g. 'block'), defaults to full log.
     * @return string Markup
     */
    public function userLogLink($username, $projectUrl, $label = null, $type = null)
    {
        if (!isset($label)) {
            $label = $this->intuitionMessage('log');
        }
        $username = str_replace(' ', '_', $username);
        $projectUrl = rtrim($projectUrl, '/');
        $url = "$projectUrl/w/index.php?title=Special:Log&action=view&user=$username";

        if ($type) {
            $url .= "&type=$type";
        }

        return "<a href='$url' target='_blank'>$label</a>";
    }

    /**
     * Get a link to the logs for given page
     * @param  string $title      Title of page
     * @param  string $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string [$label]    The link text, defaults to msg('log')
     * @param  string [$type]     Log type (e.g. 'block'), defaults to full log.
     * @return string Markup
     */
    public function wikiLogLink($title, $projectUrl, $label = null, $type = null)
    {
        if (!isset($label)) {
            $label = $this->intuitionMessage('log');
        }
        $title = str_replace(' ', '_', $title);
        $projectUrl = rtrim($projectUrl, '/');
        // FIXME: should be using the script path
        $url = "$projectUrl/w/index.php?title=Special:Log&action=view&page=$title";

        if ($type) {
            $url .= "&type=$type";
        }

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
        $projectUrl = rtrim($projectUrl, '/');
        return "<a href='$projectUrl/wiki/Special:Diff/$diff' target='_blank'>$label</a>";
    }

    /**
     * Get a permanent link to the given page at given revision
     * @param  number                   $revId      Revision ID
     * @param  string                   $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string|Integer|DateTime  [$label]    The link text, if a number is assumed to be UNIX timestamp,
     *                                              and will be converted to 'Y-m-d H:m'
     * @return string Markup
     */
    public function permaLink($revId, $projectUrl, $label = '')
    {
        if (is_int($label)) {
            $label = date('Y-m-d, H:i', $label);
        } elseif (is_a($label, 'DateTime')) {
            $label = date_format($label, 'Y-m-d, H:i');
        }
        $projectUrl = rtrim($projectUrl, '/');
        return "<a href='$projectUrl/wiki/Special:PermaLink/$revId' target='_blank'>$label</a>";
    }

    /**
     * Get a permanent link to the given page at given revision
     * @param  string $title      Title of page
     * @param  string $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @param  string [$label]    The link text, defaults to 'Edit'
     * @return string Markup
     */
    public function editLink($title, $projectUrl, $label = null)
    {
        if (!isset($label)) {
            $label = $this->intuitionMessage('edit');
        }
        return "<a href='$projectUrl/wiki/$title?action=edit' target='_blank'>$label</a>";
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
            new \Twig_SimpleFilter('diff_format', [ $this, 'diffFormat' ], [ 'is_safe' => [ 'html' ] ]),
            new \Twig_SimpleFilter('wikify_comment', [ $this, 'wikifyComment' ], [ 'is_safe' => [ 'html' ] ]),
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
     * Basic wikification of an edit summary (links, italicize section names)
     * @param  string $wikitext   Wikitext from edit summary
     * @param  string $title      Title of page
     * @param  string $projectUrl Project domain and protocol such as https://en.wikipedia.org
     * @return string             HTML markup
     */
    public function wikifyComment($wikitext, $title, $projectUrl)
    {
        $sectionMatch = null;
        $isSection = preg_match_all("/^\/\* (.*?) \*\//", $wikitext, $sectionMatch);

        if ($isSection) {
            $sectionTitle = $sectionMatch[1][0];
            $sectionTitleLink = str_replace(' ', '_', $sectionTitle);
            $sectionWikitext = "<a target='_blank' href='$projectUrl/wiki/$title#$sectionTitleLink'>&rarr;</a>" .
                "<em class='text-muted'>$sectionTitle:</em> ";
            $wikitext = str_replace($sectionMatch[0][0], $sectionWikitext, $wikitext);
        }

        $linkMatch = null;

        while (preg_match_all("/\[\[(.*?)\]\]/", $wikitext, $linkMatch)) {
            $wikiLinkParts = explode('|', $linkMatch[1][0]);
            $wikiLinkPath = $wikiLinkParts[0];
            $wikiLinkText = isset($wikiLinkParts[1]) ? $wikiLinkParts[1] : $wikiLinkPath;
            $link = "<a target='_blank' href='$projectUrl/wiki/$wikiLinkPath'>$wikiLinkText</a>";
            $wikitext = str_replace($linkMatch[0][0], $link, $wikitext);
        }

        return $wikitext;
    }
}
