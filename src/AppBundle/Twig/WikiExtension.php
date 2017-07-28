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
}
