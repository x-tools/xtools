<?php
/**
 * This file contains only the AutomatedEditsHelper class.
 */

namespace AppBundle\Helper;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Xtools\Project;

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
     * @param string $summary Edit summary
     * @param Project $project
     * @return string|bool Tool entry including key for 'name', or false if nothing was found
     */
    public function getTool($summary, Project $project)
    {
        foreach ($this->getTools($project) as $tool => $values) {
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
     * @param string $summary Edit summary
     * @param Project $project
     * @return bool
     */
    public function isAutomated($summary, Project $project)
    {
        return (bool)$this->getTool($summary, $project);
    }

    /**
     * Get list of automated tools and their associated info for the given project.
     * This defaults to the 'default_project' if entries for the given project are not found.
     * @param Project $project
     * @return array Each tool with the tool name as the key, and 'link', 'regex' and/or 'tag' as the subarray keys.
     */
    public function getTools(Project $project)
    {
        $projectDomain = $project->getDomain();

        if (isset($this->tools[$projectDomain])) {
            return $this->tools[$projectDomain];
        }

        // Load the semi-automated edit types.
        $tools = $this->container->getParameter('automated_tools');

        // Default to default project (e.g. en.wikipedia.org) if wiki not configured
        if (isset($tools[$projectDomain])) {
            $localRules = $tools[$projectDomain];
        } elseif (isset($tools[$this->container->getParameter('default_project')])) {
            $localRules = $tools[$this->container->getParameter('default_project')];
        } else {
            $localRules = [];
        }

        $langRules = isset($tools[$project->getLang()])
            ? $tools[$project->getLang()]
            : [];

        // Per-wiki rules have priority, followed by language-specific and global.
        $globalWithLangRules = $this->mergeRules($tools['global'], $langRules);
        $this->tools[$projectDomain] = $this->mergeRules(
            $globalWithLangRules,
            $localRules
        );

        return $this->tools[$projectDomain];
    }

    /**
     * Merges the given rule sets, giving priority to the local set. Regex is concatenated, not overridden.
     * @param array $globalRules The global rule set.
     * @param array $localRules The rule set for the local wiki.
     * @return array Merged rules.
     */
    private function mergeRules($globalRules, $localRules)
    {
        // Initial set, including just the global rules.
        $tools = $globalRules;

        // Loop through local rules and override/merge as necessary.
        foreach ($localRules as $tool => $rules) {
            $newRules = $rules;

            if (isset($globalRules[$tool])) {
                // Order within array_merge is important, so that local rules get priority.
                $newRules = array_merge($globalRules[$tool], $rules);
            }

            // Regex should be merged, not overridden.
            if (isset($rules['regex']) && isset($globalRules[$tool]['regex'])) {
                $newRules['regex'] = implode('|', [
                    $rules['regex'],
                    $globalRules[$tool]['regex']
                ]);
            }

            $tools[$tool] = $newRules;
        }

        return $tools;
    }

    /**
     * Get only tools that are used to revert edits.
     * Revert detection happens only by testing against a regular expression, and not by checking tags.
     * @param Project $project
     * @return string[] Each tool with the tool name as the key,
     *   and 'link' and 'regex' as the subarray keys.
     */
    public function getRevertTools(Project $project)
    {
        $projectDomain = $project->getDomain();

        if (isset($this->revertTools[$projectDomain])) {
            return $this->revertTools[$projectDomain];
        }

        $revertEntries = array_filter(
            $this->getTools($project),
            function ($tool) {
                return isset($tool['revert']);
            }
        );

        // If 'revert' is set to `true`, then use 'regex' as the regular expression,
        //  otherwise 'revert' is assumed to be the regex string.
        $this->revertTools[$projectDomain] = array_map(function ($revertTool) {
            return [
                'link' => $revertTool['link'],
                'regex' => $revertTool['revert'] === true ? $revertTool['regex'] : $revertTool['revert'],
            ];
        }, $revertEntries);

        return $this->revertTools[$projectDomain];
    }

    /**
     * Was the edit a revert, based on the edit summary?
     * This only works for tools defined with regular expressions, not tags.
     * @param string $summary Edit summary
     * @param Project $project
     * @return bool
     */
    public function isRevert($summary, Project $project)
    {
        foreach ($this->getRevertTools($project) as $tool => $values) {
            if (preg_match('/'.$values['regex'].'/', $summary)) {
                return true;
            }
        }

        return false;
    }
}
