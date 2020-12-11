<?php
/**
 * This file contains only the AutoEditsRepository class.
 */

declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Project;
use AppBundle\Model\User;

/**
 * AutoEditsRepository is responsible for retrieving data from the database
 * about the automated edits made by a user.
 * @codeCoverageIgnore
 */
class AutoEditsRepository extends UserRepository
{
    /** @var array List of automated tools, used for fetching the tool list and filtering it. */
    private $aeTools;

    /** @var bool Whether to use the /sandbox version of the config, bypassing caching. */
    private $useSandbox;

    /** @var array Process cache for tags/IDs. */
    private $tags;

    /**
     * AutoEditsRepository constructor. Used solely to set $useSandbox (from AutomatedEditsController).
     * @param bool $useSandbox
     */
    public function __construct(bool $useSandbox = false)
    {
        parent::__construct();
        $this->useSandbox = $useSandbox;
    }

    /**
     * Method to give the repository access to the AutomatedEditsHelper and fetch the list of semi-automated tools.
     * @param Project $project
     * @param int|string $namespace Namespace ID or 'all'.
     * @return array
     */
    public function getTools(Project $project, $namespace = 'all'): array
    {
        if (!isset($this->aeTools)) {
            $this->aeTools = $this->container
                ->get('app.automated_edits_helper')
                ->getTools($project, $this->useSandbox);
        }

        if ('all' !== $namespace) {
            // Limit by namespace.
            return array_filter($this->aeTools, function (array $tool) use ($namespace) {
                return empty($tool['namespaces']) ||
                    in_array((int)$namespace, $tool['namespaces']) ||
                    (
                        1 === $namespace % 2 &&
                        isset($tool['talk_namespaces'])
                    );
            });
        }

        return $this->aeTools;
    }

    /**
     * Get tools that were misconfigured, also removing them from $this->aeTools.
     * @param Project $project
     * @return string[] Labels for the invalid tools.
     */
    public function getInvalidTools(Project $project): array
    {
        $tools = $this->getTools($project);
        $invalidTools = $tools['invalid'] ?? [];
        unset($this->aeTools['invalid']);
        return $invalidTools;
    }

    /**
     * Overrides Repository::setCache(), and will not call the parent (which sets the cache) if using the sandbox.
     * @inheritDoc
     */
    public function setCache(string $cacheKey, $value, $duration = 'PT20M')
    {
        if ($this->useSandbox) {
            return $value;
        }

        return parent::setCache($cacheKey, $value, $duration);
    }

    /**
     * Get the number of edits this user made using semi-automated tools.
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'
     * @param string $start Start date in a format accepted by strtotime()
     * @param string $end End date in a format accepted by strtotime()
     * @return int Result of query, see below.
     */
    public function countAutomatedEdits(Project $project, User $user, $namespace = 'all', $start = '', $end = ''): int
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_autoeditcount');
        if (!$this->useSandbox && $this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        [$condBegin, $condEnd] = $this->getRevTimestampConditions($start, $end);

        // Get the combined regex and tags for the tools
        [$regex, $tagIds] = $this->getToolRegexAndTags($project, false, null, $namespace);

        [$pageJoin, $condNamespace] = $this->getPageAndNamespaceSql($project, $namespace);

        $revisionTable = $project->getTableName('revision');
        $commentTable = $project->getTableName('comment', 'revision');
        $tagTable = $project->getTableName('change_tag');
        $commentJoin = '';
        $tagJoin = '';

        $params = [];

        // Build SQL for detecting AutoEdits via regex and/or tags.
        $condTools = [];
        if ('' != $regex) {
            $commentJoin = "LEFT OUTER JOIN $commentTable ON rev_comment_id = comment_id";
            $condTools[] = "comment_text REGEXP :tools";
            $params['tools'] = $regex;
        }
        if ('' != $tagIds) {
            $tagJoin = "LEFT OUTER JOIN $tagTable ON ct_rev_id = rev_id";
            $condTools[] = "ct_tag_id IN ($tagIds)";
        }
        $condTool = 'AND (' . implode(' OR ', $condTools) . ')';

        $sql = "SELECT COUNT(DISTINCT(rev_id))
                FROM $revisionTable
                $pageJoin
                $commentJoin
                $tagJoin
                WHERE rev_actor = :actorId
                $condNamespace
                $condTool
                $condBegin
                $condEnd";

        $resultQuery = $this->executeQuery($sql, $project, $user, $namespace, $start, $end, $params);
        $result = (int)$resultQuery->fetchColumn();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get non-automated contributions for the given user.
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'.
     * @param string $start Start date in a format accepted by strtotime().
     * @param string $end End date in a format accepted by strtotime().
     * @param int $offset Used for pagination, offset results by N edits.
     * @return string[] Result of query, with columns 'page_title', 'page_namespace', 'rev_id', 'timestamp', 'minor',
     *   'length', 'length_change', 'comment'.
     */
    public function getNonAutomatedEdits(
        Project $project,
        User $user,
        $namespace = 'all',
        $start = '',
        $end = '',
        int $offset = 0
    ): array {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_nonautoedits');
        if (!$this->useSandbox && $this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        [$condBegin, $condEnd] = $this->getRevTimestampConditions($start, $end, 'revs.');

        // Get the combined regex and tags for the tools
        [$regex, $tagIds] = $this->getToolRegexAndTags($project, false, null, $namespace);

        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');
        $commentTable = $project->getTableName('comment', 'revision');
        $tagTable = $project->getTableName('change_tag');
        $condNamespace = 'all' === $namespace ? '' : 'AND page_namespace = :namespace';
        $condTag = '' != $tagIds ? "AND NOT EXISTS (SELECT 1 FROM $tagTable
            WHERE ct_rev_id = revs.rev_id AND ct_tag_id IN ($tagIds))" : '';
        $sql = "SELECT
                    page_title,
                    page_namespace,
                    revs.rev_id AS rev_id,
                    revs.rev_timestamp AS timestamp,
                    revs.rev_minor_edit AS minor,
                    revs.rev_len AS length,
                    (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                    comment_text AS comment
                FROM $pageTable
                JOIN $revisionTable AS revs ON (page_id = revs.rev_page)
                LEFT JOIN $revisionTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                LEFT OUTER JOIN $commentTable ON (revs.rev_comment_id = comment_id)
                WHERE revs.rev_actor = :actorId
                AND revs.rev_timestamp > 0
                AND comment_text NOT RLIKE :tools
                $condTag
                $condBegin
                $condEnd
                $condNamespace
                GROUP BY revs.rev_id
                ORDER BY revs.rev_timestamp DESC
                LIMIT 50
                OFFSET $offset";

        $resultQuery = $this->executeQuery($sql, $project, $user, $namespace, $start, $end, ['tools' => $regex]);
        $result = $resultQuery->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get (semi-)automated contributions for the given user, and optionally for a given tool.
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'.
     * @param string $start Start date in a format accepted by strtotime().
     * @param string $end End date in a format accepted by strtotime().
     * @param string|null $tool Only get edits made with this tool. Must match the keys in the AutoEdits config.
     * @param int $offset Used for pagination, offset results by N edits.
     * @return string[] Result of query, with columns 'page_title', 'page_namespace', 'rev_id', 'timestamp', 'minor',
     *   'length', 'length_change', 'comment'.
     */
    public function getAutomatedEdits(
        Project $project,
        User $user,
        $namespace = 'all',
        $start = '',
        $end = '',
        ?string $tool = null,
        int $offset = 0
    ): array {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_autoedits');
        if (!$this->useSandbox && $this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        [$condBegin, $condEnd] = $this->getRevTimestampConditions($start, $end, 'revs.');

        // In this case there is a slight performance improvement we can make if we're not given a start date.
        if ('' == $condBegin && '' == $condEnd) {
            $condBegin = 'AND revs.rev_timestamp > 0';
        }

        // Get the combined regex and tags for the tools
        [$regex, $tagIds] = $this->getToolRegexAndTags($project, false, $tool);

        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');
        $commentTable = $project->getTableName('comment', 'revision');
        $tagTable = $project->getTableName('change_tag');
        $condNamespace = 'all' === $namespace ? '' : 'AND page_namespace = :namespace';
        $tagJoin = '';
        $condsTool = [];

        if ('' != $regex) {
            $condsTool[] = 'comment_text RLIKE :tools';
        }

        if ('' != $tagIds) {
            $tagJoin = "LEFT OUTER JOIN $tagTable ON (ct_rev_id = revs.rev_id)";
            $condsTool[] = "ct_tag_id IN ($tagIds)";
        }

        $sql = "SELECT
                    page_title,
                    page_namespace,
                    revs.rev_id AS rev_id,
                    revs.rev_timestamp AS timestamp,
                    revs.rev_minor_edit AS minor,
                    revs.rev_len AS length,
                    (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                    comment_text AS comment
                FROM $pageTable
                JOIN $revisionTable AS revs ON (page_id = revs.rev_page)
                LEFT JOIN $revisionTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                LEFT OUTER JOIN $commentTable ON (revs.rev_comment_id = comment_id)
                $tagJoin
                WHERE revs.rev_actor = :actorId
                $condBegin
                $condEnd
                $condNamespace
                AND (".implode(' OR ', $condsTool).")
                GROUP BY revs.rev_id
                ORDER BY revs.rev_timestamp DESC
                LIMIT 50
                OFFSET $offset";

        $resultQuery = $this->executeQuery($sql, $project, $user, $namespace, $start, $end, ['tools' => $regex]);
        $result = $resultQuery->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get counts of known automated tools used by the given user.
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'.
     * @param string $start Start date in a format accepted by strtotime().
     * @param string $end End date in a format accepted by strtotime().
     * @return string[] Each tool that they used along with the count and link:
     *                  [
     *                      'Twinkle' => [
     *                          'count' => 50,
     *                          'link' => 'Wikipedia:Twinkle',
     *                      ],
     *                  ]
     */
    public function getToolCounts(Project $project, User $user, $namespace = 'all', $start = '', $end = ''): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_autotoolcounts');
        if (!$this->useSandbox && $this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $sql = $this->getAutomatedCountsSql($project, $namespace, $start, $end);
        $resultQuery = $this->executeQuery($sql, $project, $user, $namespace, $start, $end);

        $tools = $this->getTools($project, $namespace);

        // handling results
        $results = [];

        while ($row = $resultQuery->fetch()) {
            // Only track tools that they've used at least once
            $tool = $row['toolname'];
            if ($row['count'] > 0) {
                $results[$tool] = [
                    'link' => $tools[$tool]['link'],
                    'label' => $tools[$tool]['label'] ?? $tool,
                    'count' => $row['count'],
                ];
            }
        }

        // Sort the array by count
        uasort($results, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        // Cache and return.
        return $this->setCache($cacheKey, $results);
    }

    /**
     * Get SQL for getting counts of known automated tools used by the user.
     * @see self::getAutomatedCounts()
     * @param Project $project
     * @param string|int $namespace Namespace ID or 'all'.
     * @param string $start Start date in a format accepted by strtotime()
     * @param string $end End date in a format accepted by strtotime()
     * @return string The SQL.
     */
    private function getAutomatedCountsSql(Project $project, $namespace, $start, $end): string
    {
        [$condBegin, $condEnd] = $this->getRevTimestampConditions($start, $end);

        // Load the semi-automated edit types.
        $tools = $this->getTools($project, $namespace);

        // Create a collection of queries that we're going to run.
        $queries = [];

        $revisionTable = $project->getTableName('revision');
        [$pageJoin, $condNamespace] = $this->getPageAndNamespaceSql($project, $namespace);
        $conn = $this->getProjectsConnection();

        foreach ($tools as $toolName => $values) {
            [$condTool, $commentJoin, $tagJoin] = $this->getInnerAutomatedCountsSql($project, $toolName, $values);

            $toolName = $conn->quote($toolName, \PDO::PARAM_STR);

            // No regex or tag provided for this tool. This can happen for tag-only tools that are in the global
            // configuration, but no local tag exists on the said project.
            if ('' === $condTool) {
                continue;
            }

            $queries[] .= "
                SELECT $toolName AS toolname, COUNT(DISTINCT(rev_id)) AS count
                FROM $revisionTable
                $pageJoin
                $commentJoin
                $tagJoin
                WHERE rev_actor = :actorId
                AND $condTool
                $condNamespace
                $condBegin
                $condEnd";
        }

        // Combine to one big query.
        return implode(' UNION ', $queries);
    }

    /**
     * Get some of the inner SQL for self::getAutomatedCountsSql().
     * @param Project $project
     * @param string $toolName
     * @param string[] $values Values as defined in the AutoEdits config.
     * @return string[] [Equality clause, JOIN clause]
     */
    private function getInnerAutomatedCountsSql(Project $project, string $toolName, array $values): array
    {
        $conn = $this->getProjectsConnection();
        $commentJoin = '';
        $tagJoin = '';
        $condTool = '';

        if (isset($values['regex'])) {
            $commentTable = $project->getTableName('comment', 'revision');
            $commentJoin = "LEFT OUTER JOIN $commentTable ON rev_comment_id = comment_id";
            $regex = $conn->quote($values['regex'], \PDO::PARAM_STR);
            $condTool = "comment_text REGEXP $regex";
        }
        if (isset($values['tags'])) {
            $tagIds = $this->getTagIdsFromNames($project, $values['tags']);

            if ($tagIds) {
                $tagTable = $project->getTableName('change_tag');
                $tagJoin = "LEFT OUTER JOIN $tagTable ON ct_rev_id = rev_id";
                $tagClause = $this->getTagsExclusionsSql($project, $toolName, $tagIds);

                // Use tags in addition to the regex clause, if already present.
                // Tags are more reliable but may not be present for edits made with
                // older versions of the tool, before it started adding tags.
                if ('' === $condTool) {
                    $condTool = $tagClause;
                } else {
                    $condTool = "($condTool OR $tagClause)";
                }
            }
        }

        return [$condTool, $commentJoin, $tagJoin];
    }

    /**
     * Get the combined regex and tags for all semi-automated tools, or the given tool, ready to be used in a query.
     * @param Project $project
     * @param bool $nonAutoEdits Set to true to exclude tools with the 'contribs' flag.
     * @param string|null $tool
     * @param int|string|null $namespace Tools only used in given namespace ID, or 'all' for all namespaces.
     * @return array In the format: ['combined|regex', '1,2,3'] where the second element is a
     *   comma-separated list of the tag IDs, ready to be used in SQL.
     */
    private function getToolRegexAndTags(
        Project $project,
        bool $nonAutoEdits = false,
        ?string $tool = null,
        $namespace = null
    ): array {
        $tools = $this->getTools($project);
        $regexes = [];
        $tagIds = [];

        if ('' != $tool) {
            $tools = [$tools[$tool]];
        }

        foreach (array_values($tools) as $values) {
            if ($nonAutoEdits && isset($values['contribs'])) {
                continue;
            }

            if (is_numeric($namespace) &&
                !empty($values['namespaces']) &&
                !in_array((int)$namespace, $values['namespaces'])
            ) {
                continue;
            }

            if (isset($values['regex'])) {
                $regexes[] = $values['regex'];
            }
            if (isset($values['tags'])) {
                $tagIds = array_merge($tagIds, $this->getTagIdsFromNames($project, $values['tags']));
            }
        }

        return [
            implode('|', $regexes),
            implode(',', $tagIds),
        ];
    }

    /**
     * Get the IDs of tags for given Project, which are used in the IN clauses of other queries above.
     * This join decomposition is actually faster than JOIN'ing on change_tag_def all in one query.
     * @param Project $project
     * @return int[] Keys are the tag name, values are the IDs.
     */
    public function getTags(Project $project): array
    {
        // Use process cache; ensures we don't needlessly re-query for tag IDs
        // during the same request when using the ?usesandbox=1 option.
        if (isset($this->tags)) {
            return $this->tags;
        }

        $cacheKey = $this->getCacheKey(func_get_args(), 'ae_tag_ids');
        if (!$this->useSandbox && $this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $conn = $this->getProjectsConnection();

        // Get all tag values.
        $tags = [];
        foreach (array_values($this->getTools($project)) as $values) {
            if (isset($values['tags'])) {
                $tags = array_merge(
                    $tags,
                    array_map(function ($tag) use ($conn) {
                        return $conn->quote($tag, \PDO::PARAM_STR);
                    }, $values['tags'])
                );
            }
        }

        $tags = implode(',', $tags);
        $tagDefTable = $project->getTableName('change_tag_def');
        $sql = "SELECT ctd_name, ctd_id FROM $tagDefTable
                WHERE ctd_name IN ($tags)";
        $this->tags = $this->executeProjectsQuery($sql)->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Cache and return.
        return $this->setCache($cacheKey, $this->tags);
    }

    /**
     * Generate the WHERE clause to query for the given tags, filtering out exclusions ('tag_excludes' option).
     * For instance, Huggle edits are also tagged as Rollback, but when viewing
     * Rollback edits we don't want to show Huggle edits.
     * @param Project $project
     * @param string $tool
     * @param array $tagIds
     * @return string
     */
    private function getTagsExclusionsSql(Project $project, string $tool, array $tagIds): string
    {
        $tagsList = implode(',', $tagIds);
        $tagExcludes = $this->getTools($project)[$tool]['tag_excludes'] ?? [];
        $excludesSql = '';

        if ($tagExcludes && 1 === count($tagIds)) {
            // Get tag IDs, filtering out those for which no ID exists (meaning there is no local tag for that tool).
            $excludesList = implode(',', array_filter(array_map(function ($tagName) use ($project) {
                return $this->getTags($project)[$tagName] ?? null;
            }, $tagExcludes)));

            if (strlen($excludesList)) {
                $excludesSql = "AND ct_tag_id NOT IN ($excludesList)";
            }
        }

        return "ct_tag_id IN ($tagsList) $excludesSql";
    }

    /**
     * Get IDs for tags given the names.
     * @param Project $project
     * @param array $tagNames
     * @return array
     */
    private function getTagIdsFromNames(Project $project, array $tagNames): array
    {
        $allTagIds = $this->getTags($project);
        $tagIds = [];

        foreach ($tagNames as $tag) {
            if (isset($allTagIds[$tag])) {
                $tagIds[] = $allTagIds[$tag];
            }
        }

        return $tagIds;
    }
}
