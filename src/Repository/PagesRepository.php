<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Model\Pages;
use App\Model\Project;
use App\Model\User;

/**
 * An PagesRepository is responsible for retrieving information from the
 * databases for the Pages Created tool. It does not do any post-processing
 * of that data.
 * @codeCoverageIgnore
 */
class PagesRepository extends UserRepository
{
    /**
     * Count the number of pages created by a user.
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'.
     * @param string $redirects One of the Pages::REDIR_ constants.
     * @param string $deleted One of the Pages::DEL_ constants.
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @return string[] Result of query, see below. Includes live and deleted pages.
     */
    public function countPagesCreated(
        Project $project,
        User $user,
        $namespace,
        string $redirects,
        string $deleted,
        $start = false,
        $end = false
    ): array {
        $cacheKey = $this->getCacheKey(func_get_args(), 'num_user_pages_created');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $conditions = [
            'paSelects' => '',
            'paSelectsArchive' => '',
            'paJoin' => '',
            'revPageGroupBy' => '',
        ];
        $conditions = array_merge(
            $conditions,
            $this->getNamespaceRedirectAndDeletedPagesConditions($namespace, $redirects),
            $this->getUserConditions('' !== $start.$end)
        );

        $wasRedirect = $this->getWasRedirectClause($redirects, $deleted);
        $summation = Pages::DEL_NONE !== $deleted ? 'redirect OR was_redirect' : 'redirect';

        $sql = "SELECT `namespace`,
                    COUNT(page_title) AS `count`,
                    SUM(IF(type = 'arc', 1, 0)) AS `deleted`,
                    SUM($summation) AS `redirects`,
                    SUM(rev_length) AS `total_length`
                FROM (" .
            $this->getPagesCreatedInnerSql($project, $conditions, $deleted, $start, $end, false, true)."
                ) a ".
                $wasRedirect .
                "GROUP BY `namespace`";

        $result = $this->executeQuery($sql, $project, $user, $namespace)
            ->fetchAllAssociative();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get pages created by a user.
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'.
     * @param string $redirects One of the Pages::REDIR_ constants.
     * @param string $deleted One of the Pages::DEL_ constants.
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @param int|null $limit Number of results to return, or blank to return all.
     * @param false|int $offset Unix timestamp. Used for pagination.
     * @return string[] Result of query, see below. Includes live and deleted pages.
     */
    public function getPagesCreated(
        Project $project,
        User $user,
        $namespace,
        string $redirects,
        string $deleted,
        $start = false,
        $end = false,
        ?int $limit = 1000,
        $offset = false
    ): array {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_pages_created');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $conditions = [
            'paSelects' => '',
            'paSelectsArchive' => '',
            'paJoin' => '',
            'revPageGroupBy' => '',
        ];

        $conditions = array_merge(
            $conditions,
            $this->getNamespaceRedirectAndDeletedPagesConditions($namespace, $redirects),
            $this->getUserConditions('' !== $start.$end)
        );

        $hasPageAssessments = $this->isWMF && $project->hasPageAssessments();
        if ($hasPageAssessments) {
            $pageAssessmentsTable = $project->getTableName('page_assessments');
            $conditions['paSelects'] = ', pa_class';
            $conditions['paWhere'] = "AND pa_class != ''";
            $conditions['paSelectsArchive'] = ', NULL AS pa_class';
            $conditions['paJoin'] = "LEFT OUTER JOIN $pageAssessmentsTable ON rev_page = pa_page_id";
            $conditions['revPageGroupBy'] = 'GROUP BY rev_page';
        }

        $wasRedirect = $this->getWasRedirectClause($redirects, $deleted);

        $sql = "SELECT * FROM (".
            $this->getPagesCreatedInnerSql($project, $conditions, $deleted, $start, $end, $offset)."
                ) a ".
                $wasRedirect .
                "ORDER BY `timestamp` DESC
                ".(!empty($limit) ? "LIMIT $limit" : '');

        $result = $this->executeQuery($sql, $project, $user, $namespace)
            ->fetchAllAssociative();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    private function getWasRedirectClause(string $redirects, string $deleted): string
    {
        if (Pages::REDIR_NONE === $redirects) {
            return "WHERE was_redirect IS NULL ";
        } elseif (Pages::REDIR_ONLY === $redirects && Pages::DEL_ONLY === $deleted) {
            return "WHERE was_redirect = 1 ";
        } elseif (Pages::REDIR_ONLY === $redirects && Pages::DEL_ALL === $deleted) {
            return "WHERE was_redirect = 1 OR redirect = 1 ";
        }
        return '';
    }

    /**
     * Get SQL fragments for the namespace and redirects,
     * to be used in self::getPagesCreatedInnerSql().
     * @param string|int $namespace Namespace ID or 'all'.
     * @param string $redirects One of the Pages::REDIR_ constants.
     * @return string[] With keys 'namespaceRev', 'namespaceArc' and 'redirects'
     */
    private function getNamespaceRedirectAndDeletedPagesConditions($namespace, string $redirects): array
    {
        $conditions = [
            'namespaceArc' => '',
            'namespaceRev' => '',
            'redirects' => '',
        ];

        if ('all' !== $namespace) {
            $conditions['namespaceRev'] = " AND page_namespace = '".intval($namespace)."' ";
            $conditions['namespaceArc'] = " AND ar_namespace = '".intval($namespace)."' ";
        }

        if (Pages::REDIR_ONLY == $redirects) {
            $conditions['redirects'] = " AND page_is_redirect = '1' ";
        } elseif (Pages::REDIR_NONE == $redirects) {
            $conditions['redirects'] = " AND page_is_redirect = '0' ";
        }

        return $conditions;
    }

    /**
     * Inner SQL for getting or counting pages created by the user.
     * @param Project $project
     * @param string[] $conditions Conditions for the SQL, must include 'paSelects',
     *     'paSelectsArchive', 'paJoin', 'whereRev', 'whereArc', 'namespaceRev', 'namespaceArc',
     *     'redirects' and 'revPageGroupBy'.
     * @param string $deleted One of the Pages::DEL_ constants.
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @param int|false $offset Unix timestamp, used for pagination.
     * @param bool $count Omit unneeded columns from the SELECT clause.
     * @return string Raw SQL.
     */
    private function getPagesCreatedInnerSql(
        Project $project,
        array $conditions,
        string $deleted,
        $start,
        $end,
        $offset = false,
        bool $count = false
    ): string {
        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');
        $archiveTable = $project->getTableName('archive');
        $logTable = $project->getTableName('logging', 'logindex');

        // Only SELECT things that are needed, based on whether or not we're doing a COUNT.
        $revSelects = "DISTINCT page_namespace AS `namespace`, 'rev' AS `type`, page_title, "
            . "page_is_redirect AS `redirect`, rev_len AS `rev_length`";
        if (!$count) {
            $revSelects .= ", page_len AS `length`, rev_timestamp AS `timestamp`, "
                . "rev_id, NULL AS `recreated` ";
        }

        $revDateConditions = $this->getDateConditions($start, $end, $offset);
        $arDateConditions = $this->getDateConditions($start, $end, $offset, '', 'ar_timestamp');

        $tagTable = $project->getTableName('change_tag');
        $tagDefTable = $project->getTableName('change_tag_def');

        $revisionsSelect = "
            SELECT $revSelects ".$conditions['paSelects'].",
                NULL AS was_redirect
            FROM $pageTable
            JOIN $revisionTable ON page_id = rev_page ".
            $conditions['paJoin']."
            WHERE ".$conditions['whereRev']."
                AND rev_parent_id = '0'".
                $conditions['namespaceRev'].
                $conditions['redirects'].
                $revDateConditions.
                $conditions['paWhere'].
            $conditions['revPageGroupBy'];

        // Only SELECT things that are needed, based on whether or not we're doing a COUNT.
        $arSelects = "ar_namespace AS `namespace`, 'arc' AS `type`, ar_title AS `page_title`, "
            . "'0' AS `redirect`, ar_len AS `rev_length`";
        if (!$count) {
            $arSelects .= ", NULL AS `length`, MIN(ar_timestamp) AS `timestamp`, ".
                "ar_rev_id AS `rev_id`, EXISTS(
                    SELECT 1 FROM $pageTable
                    WHERE page_namespace = ar_namespace
                    AND page_title = ar_title
                ) AS `recreated`";
        }

        $archiveSelect = "
            SELECT $arSelects ".$conditions['paSelectsArchive'].",
                (
                    SELECT 1
                    FROM $tagTable
                    WHERE ct_rev_id = ar_rev_id
                    AND ct_tag_id = (
                        SELECT ctd_id
                        FROM $tagDefTable
                        WHERE ctd_name = 'mw-new-redirect'
                    )
                    LIMIT 1
                ) AS `was_redirect`
            FROM $archiveTable
            LEFT JOIN $logTable ON log_namespace = ar_namespace AND log_title = ar_title
                AND log_actor = ar_actor AND (log_action = 'move' OR log_action = 'move_redir')
                AND log_type = 'move'
            WHERE ".$conditions['whereArc']."
                AND ar_parent_id = '0' ".
                $conditions['namespaceArc']."
                AND log_action IS NULL
                $arDateConditions
            GROUP BY ar_namespace, ar_title";

        if ('live' === $deleted) {
            return $revisionsSelect;
        } elseif ('deleted' === $deleted) {
            return $archiveSelect;
        }

        return "($revisionsSelect) UNION ($archiveSelect)";
    }

    /**
     * Get the number of pages the user created by assessment.
     * @param Project $project
     * @param User $user
     * @param int|string $namespace
     * @param string $redirects One of the Pages::REDIR_ constants.
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @return array Keys are the assessment class, values are the counts.
     */
    public function getAssessmentCounts(
        Project $project,
        User $user,
        $namespace,
        string $redirects,
        $start = false,
        $end = false
    ): array {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_pages_created_assessments');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');
        $pageAssessmentsTable = $project->getTableName('page_assessments');

        $conditions = array_merge(
            $this->getNamespaceRedirectAndDeletedPagesConditions($namespace, $redirects),
            $this->getUserConditions('' !== $start.$end)
        );
        $revDateConditions = $this->getDateConditions($start, $end);

        $sql = "SELECT pa_class AS `class`, COUNT(pa_class) AS `count` FROM (
                    SELECT DISTINCT page_id, IFNULL(pa_class, '') AS pa_class
                    FROM $pageTable
                    JOIN $revisionTable ON page_id = rev_page
                    LEFT OUTER JOIN $pageAssessmentsTable ON rev_page = pa_page_id
                    WHERE ".$conditions['whereRev']."
                    AND rev_parent_id = '0'".
                    $conditions['namespaceRev'].
                    $conditions['redirects'].
                    $revDateConditions."
                    AND pa_class != ''
                    GROUP BY page_id
                ) a
                GROUP BY pa_class";

        $resultQuery = $this->executeQuery($sql, $project, $user, $namespace);

        $assessments = [];
        while ($result = $resultQuery->fetchAssociative()) {
            $class = '' == $result['class'] ? '' : $result['class'];
            $assessments[$class] = $result['count'];
        }

        // Cache and return.
        return $this->setCache($cacheKey, $assessments);
    }

    /**
     * Get the number of pages the user created by WikiProject.
     * Max 10 projects.
     * @param Project $project
     * @param User $user
     * @param int|string $namespace
     * @param string $redirects One of the Pages::REDIR_ constants.
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @return array Keys are the WikiProject names, values are the counts.
     */
    public function getWikiprojectCounts(
        Project $project,
        User $user,
        $namespace,
        string $redirects,
        $start = false,
        $end = false
    ): array {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_pages_created_wikiprojects');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');
        $pageAssessmentsTable = $project->getTableName('page_assessments');
        $paProjectsTable = $project->getTableName('page_assessments_projects');

        $conditions = array_merge(
            $this->getNamespaceRedirectAndDeletedPagesConditions($namespace, $redirects),
            $this->getUserConditions('' !== $start.$end)
        );
        $revDateConditions = $this->getDateConditions($start, $end);

        $sql = "SELECT pap_project_title,count(pap_project_title) as `count`
                FROM $pageTable
                LEFT JOIN $revisionTable ON page_id = rev_page
                JOIN $pageAssessmentsTable ON page_id = pa_page_id
                JOIN $paProjectsTable ON pa_project_id = pap_project_id
                WHERE ".$conditions['whereRev']."
                    AND rev_parent_id = '0'".
                    $conditions['namespaceRev'].
                    $conditions['redirects'].
                    $revDateConditions."
                GROUP BY pap_project_title
                ORDER BY `count` DESC
                LIMIT 10";

        $resultQuery = $this->executeQuery($sql, $project, $user, $namespace);

        // index => [name, count]
        $result = $resultQuery->fetchAllNumeric();
        // convert that to: name => count
        $totals = [];
        foreach ($result as [$name, $count]) {
            $totals[$name] = $count;
        }
        // sort by count decreasing
        arsort($totals);

        // Cache and return.
        return $this->setCache($cacheKey, $totals);
    }

    /**
     * Fetch the closest 'delete' event as of the time of the given $offset.
     *
     * @param Project $project
     * @param int $namespace
     * @param string $pageTitle
     * @param string $offset
     * @return array
     */
    public function getDeletionSummary(Project $project, int $namespace, string $pageTitle, string $offset): array
    {
        $actorTable = $project->getTableName('actor');
        $commentTable = $project->getTableName('comment');
        $loggingTable = $project->getTableName('logging', 'logindex');
        $sql = "SELECT actor_name, comment_text, log_timestamp
                FROM $loggingTable
                JOIN $actorTable ON actor_id = log_actor
                JOIN $commentTable ON comment_id = log_comment_id
                WHERE log_namespace = $namespace
                AND log_title = :pageTitle
                AND log_timestamp >= $offset
                AND log_type = 'delete'
                AND log_action IN ('delete', 'delete_redir', 'delete_redir2')
                LIMIT 1";
        $ret = $this->executeProjectsQuery($project, $sql, [
            'pageTitle' => str_replace(' ', '_', $pageTitle),
        ])->fetchAssociative();
        return $ret ?: [];
    }
}
