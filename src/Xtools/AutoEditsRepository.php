<?php
/**
 * This file contains only the AutoEditsRepository class.
 */

namespace Xtools;

use DateInterval;
use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * AutoEditsRepository is responsible for retrieving data from the database
 * about the automated edits made by a user.
 * @codeCoverageIgnore
 */
class AutoEditsRepository extends UserRepository
{
    /**
     * Get the number of edits this user made using semi-automated tools.
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'
     * @param string $start Start date in a format accepted by strtotime()
     * @param string $end End date in a format accepted by strtotime()
     * @return int Result of query, see below.
     */
    public function countAutomatedEdits(Project $project, User $user, $namespace = 'all', $start = '', $end = '')
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_autoeditcount');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $this->stopwatch->start($cacheKey, 'XTools');

        list($condBegin, $condEnd) = $this->getRevTimestampConditions($start, $end);

        // Get the combined regex and tags for the tools
        list($regex, $tags) = $this->getToolRegexAndTags($project->getDomain());

        list($pageJoin, $condNamespace) = $this->getPageAndNamespaceSql($project, $namespace);

        $revisionTable = $project->getTableName('revision');
        $tagTable = $project->getTableName('change_tag');
        $tagJoin = '';

        // Build SQL for detecting autoedits via regex and/or tags
        $condTools = [];
        if ($regex != '') {
            $condTools[] = "rev_comment REGEXP $regex";
        }
        if ($tags != '') {
            $tagJoin = $tags != '' ? "LEFT OUTER JOIN $tagTable ON ct_rev_id = rev_id" : '';
            $condTools[] = "ct_tag IN ($tags)";
        }
        $condTool = 'AND (' . implode(' OR ', $condTools) . ')';

        $sql = "SELECT COUNT(DISTINCT(rev_id))
                FROM $revisionTable
                $pageJoin
                $tagJoin
                WHERE rev_user_text = :username
                $condTool
                $condNamespace
                $condBegin
                $condEnd";

        $resultQuery = $this->executeQuery($sql, $user, $namespace, $start, $end);
        $result = (int) $resultQuery->fetchColumn();

        // Cache and return.
        $this->stopwatch->stop($cacheKey);
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get non-automated contributions for the given user.
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'
     * @param string $start Start date in a format accepted by strtotime()
     * @param string $end End date in a format accepted by strtotime()
     * @param int $offset Used for pagination, offset results by N edits
     * @return string[] Result of query, with columns 'page_title',
     *   'page_namespace', 'rev_id', 'timestamp', 'minor',
     *   'length', 'length_change', 'comment'
     */
    public function getNonAutomatedEdits(
        Project $project,
        User $user,
        $namespace = 'all',
        $start = '',
        $end = '',
        $offset = 0
    ) {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_nonautoedits');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $this->stopwatch->start($cacheKey, 'XTools');

        list($condBegin, $condEnd) = $this->getRevTimestampConditions($start, $end, 'revs.');

        // Get the combined regex and tags for the tools
        list($regex, $tags) = $this->getToolRegexAndTags($project->getDomain());

        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');
        $tagTable = $project->getTableName('change_tag');
        $condNamespace = $namespace === 'all' ? '' : 'AND page_namespace = :namespace';
        $tagJoin = $tags != '' ? "LEFT OUTER JOIN $tagTable ON (ct_rev_id = revs.rev_id)" : '';
        $condTag = $tags != '' ? "AND (ct_tag NOT IN ($tags) OR ct_tag IS NULL)" : '';
        $sql = "SELECT
                    page_title,
                    page_namespace,
                    revs.rev_id AS rev_id,
                    revs.rev_timestamp AS timestamp,
                    revs.rev_minor_edit AS minor,
                    revs.rev_len AS length,
                    (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                    revs.rev_comment AS comment
                FROM $pageTable
                JOIN $revisionTable AS revs ON (page_id = revs.rev_page)
                LEFT JOIN $revisionTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                $tagJoin
                WHERE revs.rev_user_text = :username
                AND revs.rev_timestamp > 0
                AND revs.rev_comment NOT RLIKE $regex
                $condTag
                $condBegin
                $condEnd
                $condNamespace
                ORDER BY revs.rev_timestamp DESC
                LIMIT 50
                OFFSET $offset";

        $resultQuery = $this->executeQuery($sql, $user, $namespace, $start, $end);
        $result = $resultQuery->fetchAll();

        // Cache and return.
        $this->stopwatch->stop($cacheKey);
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get counts of known automated tools used by the given user.
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'.
     * @param string $start Start date in a format accepted by strtotime()
     * @param string $end End date in a format accepted by strtotime()
     * @return string[] Each tool that they used along with the count and link:
     *                  [
     *                      'Twinkle' => [
     *                          'count' => 50,
     *                          'link' => 'Wikipedia:Twinkle',
     *                      ],
     *                  ]
     */
    public function getAutomatedCounts(
        Project $project,
        User $user,
        $namespace = 'all',
        $start = '',
        $end = ''
    ) {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_autotoolcounts');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $this->stopwatch->start($cacheKey, 'XTools');

        $sql = $this->getAutomatedCountsSql($project, $namespace, $start, $end);
        $resultQuery = $this->executeQuery($sql, $user, $namespace, $start, $end);

        $automatedEditsHelper = $this->container->get('app.automated_edits_helper');
        $tools = $automatedEditsHelper->getTools($project->getDomain());

        // handling results
        $results = [];

        while ($row = $resultQuery->fetch()) {
            // Only track tools that they've used at least once
            $tool = $row['toolname'];
            if ($row['count'] > 0) {
                $results[$tool] = [
                    'link' => $tools[$tool]['link'],
                    'count' => $row['count'],
                ];
            }
        }

        // Sort the array by count
        uasort($results, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        // Cache and return.
        $this->stopwatch->stop($cacheKey);
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
    private function getAutomatedCountsSql(Project $project, $namespace, $start, $end)
    {
        list($condBegin, $condEnd) = $this->getRevTimestampConditions($start, $end);

        // Load the semi-automated edit types.
        $automatedEditsHelper = $this->container->get('app.automated_edits_helper');
        $tools = $automatedEditsHelper->getTools($project->getDomain());

        // Create a collection of queries that we're going to run.
        $queries = [];

        $revisionTable = $project->getTableName('revision');
        $tagTable = $project->getTableName('change_tag');

        list($pageJoin, $condNamespace) = $this->getPageAndNamespaceSql($project, $namespace);

        $conn = $this->getProjectsConnection();

        foreach ($tools as $toolname => $values) {
            list($condTool, $tagJoin) = $this->getInnerAutomatedCountsSql($tagTable, $values);

            $toolname = $conn->quote($toolname, \PDO::PARAM_STR);

            // Developer error, no regex or tag provided for this tool.
            if ($condTool === '') {
                throw new Exception("No regex or tag found for the tool $toolname. " .
                    "Please verify this entry in semi_automated.yml");
            }

            $queries[] .= "
                SELECT $toolname AS toolname, COUNT(rev_id) AS count
                FROM $revisionTable
                $pageJoin
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
     * @param  string $tagTable Name of the `change_tag` table.
     * @param  string[] $values Values as defined in semi_automated.yml
     * @return string[] [Equality clause, JOIN clause]
     */
    private function getInnerAutomatedCountsSql($tagTable, $values)
    {
        $conn = $this->getProjectsConnection();
        $tagJoin = '';
        $condTool = '';

        if (isset($values['regex'])) {
            $regex = $conn->quote($values['regex'], \PDO::PARAM_STR);
            $condTool = "rev_comment REGEXP $regex";
        }
        if (isset($values['tag'])) {
            $tagJoin = "LEFT OUTER JOIN $tagTable ON ct_rev_id = rev_id";
            $tag = $conn->quote($values['tag'], \PDO::PARAM_STR);

            // Append to regex clause if already present.
            // Tags are more reliable but may not be present for edits made with
            //   older versions of the tool, before it started adding tags.
            if ($condTool === '') {
                $condTool = "ct_tag = $tag";
            } else {
                $condTool = '(' . $condTool . " OR ct_tag = $tag)";
            }
        }

        return [$condTool, $tagJoin];
    }

    /**
     * Get the combined regex and tags for all semi-automated tools,
     *   ready to be used in a query.
     * @param string $projectDomain Such as en.wikipedia.org
     * @return string[] In the format:
     *    ['combined|regex', 'combined,tags']
     */
    private function getToolRegexAndTags($projectDomain)
    {
        $conn = $this->getProjectsConnection();
        $automatedEditsHelper = $this->container->get('app.automated_edits_helper');
        $tools = $automatedEditsHelper->getTools($projectDomain);
        $regexes = [];
        $tags = [];

        foreach ($tools as $tool => $values) {
            if (isset($values['regex'])) {
                $regexes[] = $values['regex'];
            }
            if (isset($values['tag'])) {
                $tags[] = $conn->quote($values['tag'], \PDO::PARAM_STR);
            }
        }

        return [
            $conn->quote(implode('|', $regexes), \PDO::PARAM_STR),
            implode(',', $tags),
        ];
    }
}
