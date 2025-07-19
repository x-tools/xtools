<?php

declare(strict_types = 1);

namespace App\Helper;

use App\Model\Project;
use DateInterval;
use MediaWiki\OAuthClient\Client;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Helper class for fetching semi-automated definitions.
 */
class AutomatedEditsHelper
{
    protected CacheItemPoolInterface $cache;
    protected SessionInterface $session;

    /** @var array The list of tools that are considered reverting. */
    protected array $revertTools = [];

    /** @var array The list of tool names and their regexes/tags. */
    protected array $tools = [];

    /**
     * AutomatedEditsHelper constructor.
     * @param RequestStack $requestStack
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(RequestStack $requestStack, CacheItemPoolInterface $cache)
    {
        $this->session = $requestStack->getSession();
        $this->cache = $cache;
    }

    /**
     * Get the first tool that matched the given edit summary and tags.
     * @param string $summary Edit summary
     * @param Project $project
     * @param string[]|null $tags
     * @return string[]|null Tool entry including key for 'name', or false if nothing was found
     */
    public function getTool(string $summary, Project $project, ?array $tags = []): ?array
    {
        foreach ($this->getTools($project) as $tool => $values) {
            if ((isset($values['regex']) && preg_match('/'.$values['regex'].'/', $summary)) ||
                (isset($values['tags']) && count(array_intersect($values['tags'], $tags)) > 0)) {
                return array_merge([
                    'name' => $tool,
                ], $values);
            }
        }

        return null;
    }

    /**
     * Was the edit (semi-)automated, based on the edit summary?
     * @param string $summary Edit summary
     * @param Project $project
     * @return bool
     */
    public function isAutomated(string $summary, Project $project): bool
    {
        return (bool)$this->getTool($summary, $project);
    }

    /**
     * Fetch the config from https://meta.wikimedia.org/wiki/MediaWiki:XTools-AutoEdits.json
     * @param bool $useSandbox Use the sandbox version of the config, located at MediaWiki:XTools-AutoEdits.json/sandbox
     * @return array
     */
    public function getConfig(bool $useSandbox = false): array
    {
        $cacheKey = 'autoedits_config';
        if (!$useSandbox && $this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $uri = 'https://meta.wikimedia.org/w/api.php?' . http_build_query([
            'action' => 'query',
            'prop' => 'revisions',
            'rvprop' => 'content',
            'rvslots' => 'main',
            'format' => 'json',
            'formatversion' => 2,
            'titles' => 'MediaWiki:XTools-AutoEdits.json' . ($useSandbox ? '/sandbox' : ''),
        ]);

        if ($useSandbox && $this->session->get('logged_in_user')) {
            // Request via OAuth to get around server-side caching.
            /** @var Client $client */
            $client = $this->session->get('oauth_client');
            $resp = json_decode($client->makeOAuthCall(
                $this->session->get('oauth_access_token'),
                $uri
            ));
        } else {
            $resp = json_decode(file_get_contents($uri));
        }

        $ret = json_decode($resp->query->pages[0]->revisions[0]->slots->main->content, true);

        if (!$useSandbox) {
            $cacheItem = $this->cache
                ->getItem($cacheKey)
                ->set($ret)
                ->expiresAfter(new DateInterval('PT20M'));
            $this->cache->save($cacheItem);
        }

        return $ret;
    }

    /**
     * Get list of automated tools and their associated info for the given project.
     * This defaults to the DEFAULT_PROJECT if entries for the given project are not found.
     * @param Project $project
     * @param bool $useSandbox Whether to use the /sandbox version for testing (also bypasses caching).
     * @return array Each tool with the tool name as the key and 'link', 'regex' and/or 'tag' as the subarray keys.
     */
    public function getTools(Project $project, bool $useSandbox = false): array
    {
        $projectDomain = $project->getDomain();

        if (isset($this->tools[$projectDomain])) {
            return $this->tools[$projectDomain];
        }

        // Load the semi-automated edit types.
        $tools = $this->getConfig($useSandbox);

        if (isset($tools[$projectDomain])) {
            $localRules = $tools[$projectDomain];
        } else {
            $localRules = [];
        }

        $langRules = $tools[$project->getLang()] ?? [];

        // Per-wiki rules have priority, followed by language-specific and global.
        $globalWithLangRules = $this->mergeRules($tools['global'], $langRules);

        $this->tools[$projectDomain] = $this->mergeRules(
            $globalWithLangRules,
            $localRules
        );

        // Once last walk through for some tidying up and validation.
        $invalid = [];
        array_walk($this->tools[$projectDomain], function (&$data, $tool) use (&$invalid): void {
            // Populate the 'label' with the tool name, if a label doesn't already exist.
            $data['label'] = $data['label'] ?? $tool;

            // 'namespaces' should be an array of ints.
            $data['namespaces'] = $data['namespaces'] ?? [];
            if (isset($data['namespace'])) {
                $data['namespaces'][] = $data['namespace'];
                unset($data['namespace']);
            }

            // 'tags' should be an array of strings.
            $data['tags'] = $data['tags'] ?? [];
            if (isset($data['tag'])) {
                $data['tags'][] = $data['tag'];
                unset($data['tag']);
            }

            // If neither a tag or regex is given, it's invalid.
            if (empty($data['tags']) && empty($data['regex'])) {
                $invalid[] = $tool;
            }
        });

        uksort($this->tools[$projectDomain], 'strcasecmp');

        if ($invalid) {
            $this->tools[$projectDomain]['invalid'] = $invalid;
        }

        return $this->tools[$projectDomain];
    }

    /**
     * Merges the given rule sets, giving priority to the local set. Regex is concatenated, not overridden.
     * @param string[] $globalRules The global rule set.
     * @param string[] $localRules The rule set for the local wiki.
     * @return string[] Merged rules.
     */
    private function mergeRules(array $globalRules, array $localRules): array
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
                    $globalRules[$tool]['regex'],
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
     * @return string[][] Each tool with the tool name as the key,
     *   and 'link' and 'regex' as the subarray keys.
     */
    public function getRevertTools(Project $project): array
    {
        $projectDomain = $project->getDomain();

        if (isset($this->revertTools[$projectDomain])) {
            return $this->revertTools[$projectDomain];
        }

        $revertEntries = array_filter(
            $this->getTools($project),
            function ($tool) {
                return isset($tool['revert']) && isset($tool['regex']);
            }
        );

        // If 'revert' is set to `true`, then use 'regex' as the regular expression,
        //  otherwise 'revert' is assumed to be the regex string.
        $this->revertTools[$projectDomain] = array_map(function ($revertTool) {
            return [
                'link' => $revertTool['link'],
                'regex' => true === $revertTool['revert'] ? $revertTool['regex'] : $revertTool['revert'],
            ];
        }, $revertEntries);

        return $this->revertTools[$projectDomain];
    }

    /**
     * Was the edit a revert, based on the edit summary?
     * This only works for tools defined with regular expressions, not tags.
     * @param string|null $summary Edit summary. Can be null for instance for suppressed edits.
     * @param Project $project
     * @return bool
     */
    public function isRevert(?string $summary, Project $project): bool
    {
        foreach (array_values($this->getRevertTools($project)) as $values) {
            if (preg_match('/'.$values['regex'].'/', (string)$summary)) {
                return true;
            }
        }

        return false;
    }
}
