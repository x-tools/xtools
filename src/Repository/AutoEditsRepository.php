<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Model\Project;
use App\Model\User;
use PDO;
use Wikimedia\IPUtils;

/**
 * AutoEditsRepository is responsible for retrieving data from the database
 * about the automated edits made by a user.
 * @codeCoverageIgnore
 */
class AutoEditsRepository extends UserRepository
{
    /** @var array List of automated tools, used for fetching the tool list and filtering it. */
    private array $aeTools;

    /** @var bool Whether to use the /sandbox version of the config, bypassing caching. */
    private bool $useSandbox = false;

    /** @var array Process cache for tags/IDs. */
    private array $tags;

    /**
     * @param bool $useSandbox
     * @return AutoEditsRepository
     */
    public function setUseSandbox(bool $useSandbox): AutoEditsRepository
    {
        $this->useSandbox = $useSandbox;
        return $this;
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
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @return int Result of query, see below.
     */
    public function countAutomatedEdits(
        Project $project,
        User $user,
        $namespace = 'all',
        $start = false,
        $end = false
    ): int {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_autoeditcount');
        if (!$this->useSandbox && $this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revDateConditions = $this->getDateConditions($start, $end);

        // Get the combined regex and tags for the tools
        [$regex, $tagIds] = $this->getToolRegexAndTags($project, false, null, $namespace);

        [$pageJoin, $condNamespace] = $this->getPageAndNamespaceSql($project, $namespace);

        $revisionTable = $project->getTableName('revision');
        $ipcTable = $project->getTableName('ip_changes');
        $commentTable = $project->getTableName('comment', 'revision');
        $tagTable = $project->getTableName('change_tag');
        $commentJoin = '';
        $tagJoin = '';

        $params = [];

        // IP range handling.
        $ipcJoin = '';
        $whereClause = 'rev_actor = :actorId';
        if ($user->isIpRange()) {
            $ipcJoin = "JOIN $ipcTable ON rev_id = ipc_rev_id";
            $whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
        }

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
                $ipcJoin
                $pageJoin
                $commentJoin
                $tagJoin
                WHERE $whereClause
                $condNamespace
                $condTool
                $revDateConditions";

        $resultQuery = $this->executeQuery($sql, $project, $user, $namespace, $params);
        $result = (int)$resultQuery->fetchOne();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get non-automated contributions for the given user.
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'.
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @param int|false $offset Unix timestamp. Used for pagination.
     * @param int $limit Number of results to return.
     * @return string[] Result of query, with columns 'page_title', 'page_namespace', 'rev_id', 'timestamp', 'minor',
     *   'length', 'length_change', 'comment'.
     */
    public function getNonAutomatedEdits(
        Project $project,
        User $user,
        $namespace = 'all',
        $start = false,
        $end = false,
        $offset = false,
        int $limit = 50
    ): array {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_nonautoedits');
        if (!$this->useSandbox && $this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revDateConditions = $this->getDateConditions($start, $end, $offset, 'revs.');

        // Get the combined regex and tags for the tools
        [$regex, $tagIds] = $this->getToolRegexAndTags($project, false, null, $namespace);

        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');
        $ipcTable = $project->getTableName('ip_changes');
        $commentTable = $project->getTableName('comment', 'revision');
        $tagTable = $project->getTableName('change_tag');

        // IP range handling.
        $ipcJoin = '';
        $whereClause = 'revs.rev_actor = :actorId';
        $params = ['tools' => $regex];
        if ($user->isIpRange()) {
            $ipcJoin = "JOIN $ipcTable ON revs.rev_id = ipc_rev_id";
            $whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
        }

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
                $ipcJoin
                LEFT JOIN $revisionTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                LEFT OUTER JOIN $commentTable ON (revs.rev_comment_id = comment_id)
                WHERE $whereClause
                AND revs.rev_timestamp > 0
                AND comment_text NOT RLIKE :tools
                $condTag
                $revDateConditions
                $condNamespace
                GROUP BY revs.rev_id
                ORDER BY revs.rev_timestamp DESC
                LIMIT $limit";

        $resultQuery = $this->executeQuery($sql, $project, $user, $namespace, $params);
        $result = $resultQuery->fetchAllAssociative();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get (semi-)automated contributions for the given user, and optionally for a given tool.
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'.
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @param string|null $tool Only get edits made with this tool. Must match the keys in the AutoEdits config.
     * @param int|false $offset Unix timestamp. Used for pagination.
     * @param int $limit Number of results to return.
     * @return string[] Result of query, with columns 'page_title', 'page_namespace', 'rev_id', 'timestamp', 'minor',
     *   'length', 'length_change', 'comment'.
     */
    public function getAutomatedEdits(
        Project $project,
        User $user,
        $namespace = 'all',
        $start = false,
        $end = false,
        ?string $tool = null,
        $offset = false,
        int $limit = 50
    ): array {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_autoedits');
        if (!$this->useSandbox && $this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revDateConditions = $this->getDateConditions($start, $end, $offset, 'revs.');

        // In this case there is a slight performance improvement we can make if we're not given a start date.
        if ('' === $revDateConditions) {
            $revDateConditions = 'AND revs.rev_timestamp > 0';
        }

        // Get the combined regex and tags for the tools
        [$regex, $tagIds] = $this->getToolRegexAndTags($project, false, $tool);

        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');
        $ipcTable = $project->getTableName('ip_changes');
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

        // IP range handling.
        $ipcJoin = '';
        $whereClause = 'revs.rev_actor = :actorId';
        $params = ['tools' => $regex];
        if ($user->isIpRange()) {
            $ipcJoin = "JOIN $ipcTable ON rev_id = ipc_rev_id";
            $whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
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
                $ipcJoin
                LEFT JOIN $revisionTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                LEFT OUTER JOIN $commentTable ON (revs.rev_comment_id = comment_id)
                $tagJoin
                WHERE $whereClause
                $revDateConditions
                $condNamespace
                AND (".implode(' OR ', $condsTool).")
                GROUP BY revs.rev_id
                ORDER BY revs.rev_timestamp DESC
                LIMIT $limit";

        $resultQuery = $this->executeQuery($sql, $project, $user, $namespace, $params);
        $result = $resultQuery->fetchAllAssociative();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get counts of known automated tools used by the given user.
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'.
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @return string[] Each tool that they used along with the count and link:
     *                  [
     *                      'Twinkle' => [
     *                          'count' => 50,
     *                          'link' => 'Wikipedia:Twinkle',
     *                      ],
     *                  ]
     */
    public function getToolCounts(Project $project, User $user, $namespace = 'all', $start = false, $end = false): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_autotoolcounts');
        if (!$this->useSandbox && $this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $sql = $this->getAutomatedCountsSql($project, $user, $namespace, $start, $end);
        $params = [];
        if ($user->isIpRange()) {
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
        }
        $resultQuery = $this->executeQuery($sql, $project, $user, $namespace, $params);

        $tools = $this->getTools($project, $namespace);

        // handling results
        $results = [];

        while ($row = $resultQuery->fetchAssociative()) {
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
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'.
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @return string The SQL.
     */
    private function getAutomatedCountsSql(
        Project $project,
        User $user,
        $namespace,
        $start = false,
        $end = false
    ): string {
        $revDateConditions = $this->getDateConditions($start, $end);

        // Load the semi-automated edit types.
        $tools = $this->getTools($project, $namespace);

        // Create a collection of queries that we're going to run.
        $queries = [];

        $revisionTable = $project->getTableName('revision');
        $ipcTable = $project->getTableName('ip_changes');
        [$pageJoin, $condNamespace] = $this->getPageAndNamespaceSql($project, $namespace);
        $conn = $this->getProjectsConnection($project);

        // IP range handling.
        $ipcJoin = '';
        $whereClause = 'rev_actor = :actorId';
        if ($user->isIpRange()) {
            $ipcJoin = "JOIN $ipcTable ON rev_id = ipc_rev_id";
            $whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
        }

        foreach ($tools as $toolName => $values) {
            [$condTool, $commentJoin, $tagJoin] = $this->getInnerAutomatedCountsSql($project, $toolName, $values);

            $toolName = $conn->quote($toolName, PDO::PARAM_STR);

            // No regex or tag provided for this tool. This can happen for tag-only tools that are in the global
            // configuration, but no local tag exists on the said project.
            if ('' === $condTool) {
                continue;
            }

            $queries[] .= "
                SELECT $toolName AS toolname, COUNT(DISTINCT(rev_id)) AS count
                FROM $revisionTable
                $ipcJoin
                $pageJoin
                $commentJoin
                $tagJoin
                WHERE $whereClause
                AND $condTool
                $condNamespace
                $revDateConditions";
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
        $conn = $this->getProjectsConnection($project);
        $commentJoin = '';
        $tagJoin = '';
        $condTool = '';

        if (isset($values['regex'])) {
            $commentTable = $project->getTableName('comment', 'revision');
            $commentJoin = "LEFT OUTER JOIN $commentTable ON rev_comment_id = comment_id";
            $regex = $conn->quote($values['regex'], PDO::PARAM_STR);
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

        $conn = $this->getProjectsConnection($project);

        // Get all tag values.
        $tags = [];
        foreach (array_values($this->getTools($project)) as $values) {
            if (isset($values['tags'])) {
                $tags = array_merge(
                    $tags,
                    array_map(function ($tag) use ($conn) {
                        return $conn->quote($tag, PDO::PARAM_STR);
                    }, $values['tags'])
                );
            }
        }

        $tags = implode(',', $tags);
        $tagDefTable = $project->getTableName('change_tag_def');
        $sql = "SELECT ctd_name, ctd_id FROM $tagDefTable
                WHERE ctd_name IN ($tags)";
        $this->tags = $this->executeProjectsQuery($project, $sql)->fetchAllKeyValue();

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
