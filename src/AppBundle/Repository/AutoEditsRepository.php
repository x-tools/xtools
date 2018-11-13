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

    /**
     * Method to give the repository access to the AutomatedEditsHelper
     * and fetch the list of semi-automated tools.
     * @param Project $project
     * @return array
     */
    public function getTools(Project $project): array
    {
        if (!isset($this->aeTools)) {
            $this->aeTools = $this->container
                ->get('app.automated_edits_helper')
                ->getTools($project);
        }
        return $this->aeTools;
    }

    /**
     * Is the tag for given tool intended to be counted by itself?
     * For instance, when counting Rollback edits we don't want to also
     * count Huggle edits (which are tagged as Rollback).
     * @param Project $project
     * @param string|null $tool
     * @return bool
     */
    private function usesSingleTag(Project $project, ?string $tool): bool
    {
        return isset($this->getTools($project)[$tool]['single_tag']);
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
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        [$condBegin, $condEnd] = $this->getRevTimestampConditions($start, $end);

        // Get the combined regex and tags for the tools
        [$regex, $tagIds] = $this->getToolRegexAndTags($project);

        [$pageJoin, $condNamespace] = $this->getPageAndNamespaceSql($project, $namespace);

        $revisionTable = $project->getTableName('revision');
        $commentTable = $project->getTableName('comment');
        $tagTable = $project->getTableName('change_tag');
        $commentJoin = '';
        $tagJoin = '';

        $params = [];

        // Build SQL for detecting autoedits via regex and/or tags
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
                WHERE rev_user_text = :username
                $condNamespace
                $condTool
                $condBegin
                $condEnd";

        $resultQuery = $this->executeQuery($sql, $user, $namespace, $start, $end, $params);
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
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        [$condBegin, $condEnd] = $this->getRevTimestampConditions($start, $end, 'revs.');

        // Get the combined regex and tags for the tools
        [$regex, $tagIds] = $this->getToolRegexAndTags($project, true);

        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');
        $commentTable = $project->getTableName('comment');
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
                WHERE revs.rev_user_text = :username
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

        $resultQuery = $this->executeQuery($sql, $user, $namespace, $start, $end, ['tools' => $regex]);
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
     * @param string|null $tool Only get edits made with this tool. Must match the keys in semi_automated.yml.
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
        if ($this->cache->hasItem($cacheKey)) {
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
        $commentTable = $project->getTableName('comment');
        $tagTable = $project->getTableName('change_tag');
        $condNamespace = 'all' === $namespace ? '' : 'AND page_namespace = :namespace';
        $tagJoin = '';
        $condsTool = [];

        if ('' != $regex) {
            $condsTool[] = 'comment_text RLIKE :tools';
        }

        if ('' != $tagIds) {
            $tagJoin = "LEFT OUTER JOIN $tagTable ON (ct_rev_id = revs.rev_id)";
            if ($this->usesSingleTag($project, $tool)) {
                // Only show edits made with the tool that don't overlap with other tools.
                // For instance, Huggle edits are also tagged as Rollback, but when viewing
                // Rollback edits we don't want to show Huggle edits.
                $condsTool[] = "
                    EXISTS (
                        SELECT COUNT(ct_tag_id) AS tag_count
                        FROM $tagTable
                        WHERE ct_rev_id = revs.rev_id
                        HAVING tag_count = 1 AND ct_tag_id = $tagIds
                    )";
            } else {
                $condsTool[] = "ct_tag_id IN ($tagIds)";
            }
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
                WHERE revs.rev_user_text = :username
                $condBegin
                $condEnd
                $condNamespace
                AND (".implode(' OR ', $condsTool).")
                GROUP BY revs.rev_id
                ORDER BY revs.rev_timestamp DESC
                LIMIT 50
                OFFSET $offset";

        $resultQuery = $this->executeQuery($sql, $user, $namespace, $start, $end, ['tools' => $regex]);
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
    public function getToolCounts(
        Project $project,
        User $user,
        $namespace = 'all',
        $start = '',
        $end = ''
    ): array {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_autotoolcounts');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $sql = $this->getAutomatedCountsSql($project, $namespace, $start, $end);
        $resultQuery = $this->executeQuery($sql, $user, $namespace, $start, $end);

        $tools = $this->getTools($project);

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
     * Get SQL for getting counts of known automated tools used by the given user.
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
        $tools = $this->getTools($project);

        // Create a collection of queries that we're going to run.
        $queries = [];

        $revisionTable = $project->getTableName('revision');

        [$pageJoin, $condNamespace] = $this->getPageAndNamespaceSql($project, $namespace);

        $conn = $this->getProjectsConnection();

        foreach ($tools as $toolname => $values) {
            [$condTool, $commentJoin, $tagJoin] = $this->getInnerAutomatedCountsSql($project, $values);

            $toolname = $conn->quote($toolname, \PDO::PARAM_STR);

            // No regex or tag provided for this tool. This can happen for tag-only tools that are in the global
            // configuration, but no local tag exists on the said project.
            if ('' === $condTool) {
                continue;
            }

            $queries[] .= "
                SELECT $toolname AS toolname, COUNT(DISTINCT(rev_id)) AS count
                FROM $revisionTable
                $pageJoin
                $commentJoin
                $tagJoin
                WHERE rev_user_text = :username
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
     * @param string[] $values Values as defined in semi_automated.yml
     * @return string[] [Equality clause, JOIN clause]
     */
    private function getInnerAutomatedCountsSql(Project $project, array $values): array
    {
        $conn = $this->getProjectsConnection();
        $commentJoin = '';
        $tagJoin = '';
        $condTool = '';

        if (isset($values['regex'])) {
            $commentTable = $project->getTableName('comment');
            $commentJoin = "LEFT OUTER JOIN $commentTable ON rev_comment_id = comment_id";
            $regex = $conn->quote($values['regex'], \PDO::PARAM_STR);
            $condTool = "comment_text REGEXP $regex";
        }
        if (isset($values['tag']) && isset($this->getTags($project)[$values['tag']])) {
            $tagTable = $project->getTableName('change_tag');
            $tagJoin = "LEFT OUTER JOIN $tagTable ON ct_rev_id = rev_id";

            $tagId = $this->getTags($project)[$values['tag']];

            // This ensures we count only edits made with the given tool, and not other
            // edits that incidentally have the same tag. For instance, Huggle edits
            // are also tagged as Rollback, but we want to make them mutually exclusive.
            $tagClause = "
                EXISTS (
                    SELECT COUNT(ct_tag_id) AS tag_count
                    FROM $tagTable
                    WHERE ct_rev_id = rev_id
                    HAVING tag_count = 1 AND ct_tag_id = $tagId
                )";

            // Use tags in addition to the regex clause, if already present.
            // Tags are more reliable but may not be present for edits made with
            // older versions of the tool, before it started adding tags.
            if ('' === $condTool) {
                $condTool = $tagClause;
            } else {
                $condTool = '(' . $condTool . " OR $tagClause)";
            }
        }

        return [$condTool, $commentJoin, $tagJoin];
    }

    /**
     * Get the combined regex and tags for all semi-automated tools, or the given tool, ready to be used in a query.
     * @param Project $project
     * @param bool $nonAutoEdits Set to true to exclude tools with the 'contribs' flag.
     * @param string|null $tool
     * @return array In the format: ['combined|regex', [1,2,3]] where the second element contains the tag IDs.
     */
    private function getToolRegexAndTags(Project $project, bool $nonAutoEdits = false, ?string $tool = null): array
    {
        $tools = $this->getTools($project);
        $regexes = [];
        $allTagIds = $this->getTags($project);
        $tagIds = [];

        if ('' != $tool) {
            $tools = [$tools[$tool]];
        }

        foreach (array_values($tools) as $values) {
            if ($nonAutoEdits && isset($values['contribs'])) {
                continue;
            }

            if (isset($values['regex'])) {
                $regexes[] = $values['regex'];
            }
            if (isset($values['tag']) && isset($allTagIds[$values['tag']])) {
                $tagIds[] = $allTagIds[$values['tag']];
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
     * @return int[]
     */
    public function getTags(Project $project): array
    {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'ae_tag_ids');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $conn = $this->getProjectsConnection();

        // Get all tag values.
        $tags = [];
        foreach (array_values($this->getTools($project)) as $values) {
            if (isset($values['tag'])) {
                $tags[] = $conn->quote($values['tag'], \PDO::PARAM_STR);
            }
        }

        $tags = implode(',', $tags);
        $tagDefTable = $project->getTableName('change_tag_def');
        $sql = "SELECT ctd_name, ctd_id FROM $tagDefTable
                WHERE ctd_name IN ($tags)";
        $result = $this->executeProjectsQuery($sql)->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }
}
