<?php
/**
 * This file contains only the WikiExtension class.
 */

namespace AppBundle\Twig;

use Twig_SimpleFunction;

/**
 * Twig extension filters and functions for MediaWiki project links.
 */
class WikiExtension extends Extension
{

    /**
     * Get the name of this extension.
     * @return string
     */
    public function getName()
    {
        return 'wiki_extension';
    }

    /**
     * Get a i18n message.
     * @param string $message
     * @param array $vars
     * @return mixed|null|string
     */
    public function intuitionMessage($message = "", $vars = [])
    {
        return $this->getIntuition()->msg($message, [ "domain" => "xtools", "variables" => $vars ]);
    }

    /*********************************** FUNCTIONS ***********************************/

    /**
     * Get all functions provided by this extension.
     * @return array
     */
    public function getFunctions()
    {
        $options = [ 'is_safe' => [ 'html']];
        return [];
    }

    /*********************************** FILTERS ***********************************/

    /**
     * Get all functions provided by this extension.
     * @return array
     */
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
