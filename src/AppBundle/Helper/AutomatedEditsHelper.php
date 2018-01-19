<?php
/**
 * This file contains only the AutomatedEditsHelper class.
 */

namespace AppBundle\Helper;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper class for fetching semi-automated definitions.
 */
class AutomatedEditsHelper extends HelperBase
{

    /** @var string[] The list of tools that are considered reverting. */
    protected $revertTools = [];

    /** @var string[] The list of tool names and their regexes. */
    protected $tools = [];

    /**
     * AutomatedEditsHelper constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Get the tool that matched the given edit summary.
     * This only works for tools defined with regular expressions, not tags.
     * @param  string $summary Edit summary
     * @param  string $projectDomain Such as en.wikipedia.org
     * @return string|bool Tool entry including key for 'name', or false if nothing was found
     */
    public function getTool($summary, $projectDomain)
    {
        foreach ($this->getTools($projectDomain) as $tool => $values) {
            if (isset($values['regex']) && preg_match('/'.$values['regex'].'/', $summary)) {
                return array_merge([
                    'name' => $tool,
                ], $values);
            }
        }

        return false;
    }

    /**
     * Was the edit (semi-)automated, based on the edit summary?
     * This only works for tools defined with regular expressions, not tags.
     * @param  string $summary Edit summary
     * @param  string $projectDomain Such as en.wikipedia.org
     * @return bool
     */
    public function isAutomated($summary, $projectDomain)
    {
        return (bool) $this->getTool($summary, $projectDomain);
    }

    /**
     * Get list of automated tools and their associated info for the
     *   given project. This defaults to the 'default_project' if
     *   entries for the given project are not found.
     * @param  string $projectDomain Such as en.wikipedia.org
     * @return string[] Each tool with the tool name as the key,
     *   and 'link', 'regex' and/or 'tag' as the subarray keys.
     */
    public function getTools($projectDomain)
    {
        if (isset($this->tools[$projectDomain])) {
            return $this->tools[$projectDomain];
        }

        // Load the semi-automated edit types.
        $toolsByWiki = $this->container->getParameter('automated_tools');

        // Default to default project (e.g. en.wikipedia.org) if wiki not configured
        if (isset($toolsByWiki[$projectDomain])) {
            $this->tools[$projectDomain] = $toolsByWiki[$projectDomain];
        } elseif (isset($toolsByWiki[$this->container->getParameter('default_project')])) {
            $this->tools[$projectDomain] = $toolsByWiki[$this->container->getParameter('default_project')];
        } else {
            $this->tools[$projectDomain] = [];
        }

        // Override global rules with wiki-specific rules.
        $this->tools[$projectDomain] = $this->mergeValues(
            $this->tools[$projectDomain],
            $toolsByWiki['global']
        );

        return $this->tools[$projectDomain];
    }

    /**
     * Merges the given rule sets, giving priority to the wiki-specific set.
     * Regex is concatenated, not overridden.
     * @param array $localValues  The rule set for the local wiki.
     * @param array $globalValues The global rule set.
     */
    private function mergeValues($localValues, $globalValues)
    {
        // Initial set, including just the global values.
        $tools = $globalValues;

        // Loop through local values and override/merge as necessary.
        foreach ($localValues as $tool => $values) {
            $newValues = $values;

            if (isset($globalValues[$tool])) {
                // Order within array_merge is important, so that local values get priority.
                $newValues = array_merge($globalValues[$tool], $values);
            }

            // Regex should be merged, not overridden.
            if (isset($values['regex']) && isset($globalValues[$tool]['regex'])) {
                $newValues['regex'] = implode('|', [
                    $values['regex'],
                    $globalValues[$tool]['regex']
                ]);
            }

            $tools[$tool] = $newValues;
        }

        return $tools;
    }

    /**
     * Get only tools that are used to revert edits.
     * Revert detection happens only by testing against a regular expression,
     *   and not by checking tags.
     * @param  string $projectDomain Such as en.wikipedia.org
     * @return string[] Each tool with the tool name as the key,
     *   and 'link' and 'regex' as the subarray keys.
     */
    public function getRevertTools($projectDomain)
    {
        if (isset($this->revertTools[$projectDomain])) {
            return $this->revertTools[$projectDomain];
        }

        $revertEntries = array_filter(
            $this->getTools($projectDomain),
            function ($tool) {
                return isset($tool['revert']);
            }
        );

        // If 'revert' is set to `true`, then use 'regex' as the regular expression,
        //  otherwise 'revert' is assumed to be the regex string.
        $this->revertTools[$projectDomain] = array_map(function ($revertTool) {
            return [
                'link' => $revertTool['link'],
                'regex' => $revertTool['revert'] === true ? $revertTool['regex'] : $revertTool['revert']
            ];
        }, $revertEntries);

        return $this->revertTools[$projectDomain];
    }

    /**
     * Was the edit a revert, based on the edit summary?
     * This only works for tools defined with regular expressions, not tags.
     * @param  string $summary Edit summary
     * @param  string $projectDomain Such as en.wikipedia.org
     * @return bool
     */
    public function isRevert($summary, $projectDomain)
    {
        foreach ($this->getRevertTools($projectDomain) as $tool => $values) {
            if (preg_match('/'.$values['regex'].'/', $summary)) {
                return true;
            }
        }

        return false;
    }
}
