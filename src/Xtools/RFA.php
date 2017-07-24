<?php

/**
 * An RFA object contains the parsed information for an RFA
 */

namespace Xtools;


class RFA
{
    private $sections;
    private $data;
    private $user_looking_for;
    private $userSectionFound;
    private $endDate;

    /**
     * Attempts to find a signature in $input using the default regex.
     * Returns matches.
     *
     * @param $input
     * @param $matches
     *
     * @return int
     */
    protected function findSig( $input, &$matches ) {
        //Supports User: and User talk: wikilinks, {{fullurl}}, unsubsted {{unsigned}}, unsubsted {{unsigned2}}, anything that looks like a custom sig template
        // TODO: Cross-wiki this sucker
        return preg_match_all(
            "/\[\[[Uu]ser(?:[\s_][Tt]alk)?\:([^\]\|\/]*)(?:\|[^\]]*)?\]\]" //1: Normal [[User:XX]] and [[User talk:XX]]
            . "|\{\{(?:[Ff]ullurl\:[Uu]ser(?:[\s_][Tt]alk)?\:|[Uu]nsigned\|)([^\}\|]*)(?:|[\|\}]*)?\}\}" //2: {{fullurl}} and {{unsigned}} templates
            . "|(?:\{\{)[Uu]ser(?:[\s_][Tt]alk)?\:([^\}\/\|]*)" //3: {{User:XX/sig}} templates
            . "|\{\{[Uu]nsigned2\|[^\|]*\|([^\}]*)\}\}" //4: {{unsigned2|Date|XX}} templates
            . "|(?:\[\[)[Uu]ser\:([^\]\/\|]*)\/[Ss]ig[\|\]]/" //5: [[User:XX/sig]] links (compromise measure)
            , $input, $matches, PREG_OFFSET_CAPTURE
        );
    }

    public function __construct(
        $rawWikiText,
        $section_array = ["Support", "Oppose", "Neutral"],
        $user_namespace = "User",
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
                // TODO: Make this cross-project
                // TODO: Handle other date possibilities
                && preg_match(
                    "/final .*end(?:ing|ed)?(?: no earlier than)? (.*?)? \(UTC\)/i",
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
    }

    public function getUserSectionFound() {
        return $this->userSectionFound;
    }

    public function getSection($sectionName) {
        $sectionName = strtolower($sectionName);
        if (!isset($this->data[$sectionName])) {
            return [];
        }
        else {
            return $this->data[$sectionName];
        }
    }

    public function getEndDate() {
        return $this->endDate;
    }

    // MRB - Compatibility functions with Peachy's version.
    // MRB - To remove.

    public function get_userSectionFound() {
        return $this->getUserSectionFound();
    }

    public function get_support() {
        return $this->getSection("support");
    }

    public function get_oppose() {
        return $this->getSection("oppose");
    }

    public function get_neutral() {
        return $this->getSection("neutral");
    }

    public function get_enddate() {
        return $this->getEndDate();
    }
}