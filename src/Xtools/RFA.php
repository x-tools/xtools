<?php

/**
 * An RFA object contains the parsed information for an RFA
 *
 * @category RFA
 * @package  Xtools
 * @author   Xtools Team <xtools@lists.wikimedia.org>
 * @license  GPL 3.0
 * @link     http://xtools.wmflabs.org/rfa
 */

namespace Xtools;

/**
 * Class RFA
 *
 * @category RFA
 * @package  Xtools
 * @author   Xtools Team <xtools@lists.wikimedia.org>
 * @license  GPL 3.0
 * @link     http://xtools.wmflabs.org/rfa
 */
class RFA
{
    private $sections;
    private $data;
    private $duplicates;
    private $user_looking_for;
    private $userSectionFound;
    private $endDate;

    /**
     * Attempts to find a signature in $input using the default regex.
     * Returns matches.
     *
     * @param string $input   The line we're looking for
     * @param array  $matches Pointer to an array where we stash results
     *
     * @TODO: Make this cleaner
     *
     * @return int
     */
    protected function findSig($input, &$matches)
    {
        //Supports User: and User talk: wikilinks, {{fullurl}},
        // unsubsted {{unsigned}}, unsubsted {{unsigned2}},
        // anything that looks like a custom sig template
        // TODO: Cross-wiki this sucker
        $regexp
            = //1: Normal [[User:XX]] and [[User talk:XX]]
            "/\[\[[Uu]ser(?:[\s_][Tt]alk)?\:([^\]\|\/]*)(?:\|[^\]]*)?\]\]"
            //2: {{fullurl}} and {{unsigned}} templates
            . "|\{\{(?:[Ff]ullurl\:[Uu]ser(?:[\s_][Tt]alk)?\:|"
            . "[Uu]nsigned\|)([^\}\|]*)(?:|[\|\}]*)?\}\}"
            //3: {{User:XX/sig}} templates
            . "|(?:\{\{)[Uu]ser(?:[\s_][Tt]alk)?\:([^\}\/\|]*)"
            //4: {{unsigned2|Date|XX}} templates
            . "|\{\{[Uu]nsigned2\|[^\|]*\|([^\}]*)\}\}"
            //5: [[User:XX/sig]] links (compromise measure)
            . "|(?:\[\[)[Uu]ser\:([^\]\/\|]*)\/[Ss]ig[\|\]]/";

        return preg_match_all(
            $regexp,
            $input,
            $matches,
            PREG_OFFSET_CAPTURE
        );
    }

    /**
     * RFA constructor.
     *
     * @param string      $rawWikiText      The text of the page we're parsing
     * @param array       $section_array    Section names that we're looking for
     * @param string      $user_namespace   Plain text of the user namespace
     * @param string      $date_regexp      Valid Regular Expression for the end date
     * @param string|null $user_looking_for User we're trying to find.
     */
    public function __construct(
        $rawWikiText,
        $section_array = ["Support", "Oppose", "Neutral"],
        $user_namespace = "User",
        $date_regexp = "final .*end(?:ing|ed)?(?: no earlier than)? (.*?)? \(UTC\)",
        $user_looking_for = null
    ) {
        $this->sections = $section_array;
        $this->user_looking_for = $user_looking_for;

        $lines = explode("\n", $rawWikiText);

        $keys = join("|", $section_array);

        $lastSection = "";

        foreach ($lines as $line) {
            if (preg_match("/={1,6}\s?($keys)\s?={1,6}/i", $line, $matches)) {
                $lastSection = strtolower($matches[1]);
            } elseif ($lastSection == ""
                && preg_match(
                    "/$date_regexp/",
                    $line,
                    $matches
                )
            ) {
                $this->endDate = $matches[1];
            } elseif ($lastSection != ""
                && preg_match("/^\s*#?:.*/i", $line) === 0
            ) {
                $this->findSig($line, $matches);
                if (!isset($matches[1][0])) {
                    continue;
                }
                $foundUser = trim($matches[1][0][0]);
                $this->data[$lastSection][] = $foundUser;
                if (strtolower($foundUser) == strtolower($this->user_looking_for)) {
                    $this->userSectionFound = $lastSection;
                }
            }
        }

        $final = [];    // initialize the final array
        $finalRaw = []; // Initialize the raw data array

        foreach ($this->data as $key => $value) {
            $finalRaw = array_merge($finalRaw, $this->data[$key]);
        }

        foreach ($finalRaw as $foundUsername) {
            $final[] = $foundUsername; // group all array's elements
        }

        $final = array_count_values($final); // find repetition and its count

        $final = array_diff($final, [1]);    // remove single occurrences

        $this->duplicates = array_keys($final);
    }

    /**
     * Which section we found the user we're looking for.
     *
     * @return string
     */
    public function getUserSectionFound()
    {
        return $this->userSectionFound;
    }

    /**
     * Returns data on the given section name.
     *
     * @param string $sectionName The section we're looking at
     *
     * @return array
     */
    public function getSection($sectionName)
    {
        $sectionName = strtolower($sectionName);
        if (!isset($this->data[$sectionName])) {
            return [];
        } else {
            return $this->data[$sectionName];
        }
    }

    /**
     * Get an array of duplicate votes.
     *
     * @return array
     */
    public function getDuplicates()
    {
        return $this->duplicates;
    }

    /**
     * Get the End Date of the RFA
     *
     * @return string
     */
    public function getEndDate()
    {
        return $this->endDate;
    }
}