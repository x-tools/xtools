<?php
/**
 * This file contains only the PagesRepository class.
 */

declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Project;
use AppBundle\Model\User;

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
     * @param string $redirects One of 'noredirects', 'onlyredirects' or blank for both.
     * @param string $deleted One of 'live', 'deleted' or blank for both.
     * @param string $start Start date in a format accepted by strtotime()
     * @param string $end End date in a format accepted by strtotime()
     * @return string[] Result of query, see below. Includes live and deleted pages.
     */
    public function countPagesCreated(
        Project $project,
        User $user,
        $namespace,
        string $redirects,
        string $deleted,
        string $start = '',
        string $end = ''
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
            $this->getUserConditions($project, $user, '' !== $start.$end)
        );

        $sql = "SELECT namespace,
                    COUNT(page_title) AS count,
                    SUM(CASE WHEN type = 'arc' THEN 1 ELSE 0 END) AS deleted,
                    SUM(page_is_redirect) AS redirects
                FROM (".
                    $this->getPagesCreatedInnerSql($project, $conditions, $deleted, $start, $end, true)."
                ) a ".
                "GROUP BY namespace";

        $result = $this->executeQuery($sql, $project, $user, $namespace, $start, $end)->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get pages created by a user.
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'.
     * @param string $redirects One of 'noredirects', 'onlyredirects' or blank for both.
     * @param string $deleted One of 'live', 'deleted' or blank for both.
     * @param string $start Start date in a format accepted by strtotime()
     * @param string $end End date in a format accepted by strtotime()
     * @param int|null $limit Number of results to return, or blank to return all.
     * @param int $offset Number of results past the initial dataset. Used for pagination.
     * @return string[] Result of query, see below. Includes live and deleted pages.
     */
    public function getPagesCreated(
        Project $project,
        User $user,
        $namespace,
        string $redirects,
        string $deleted,
        string $start = '',
        string $end = '',
        $limit = 1000,
        $offset = 0
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
            $this->getUserConditions($project, $user, '' !== $start.$end)
        );

        $pageAssessmentsTable = $project->getTableName('page_assessments');

        $hasPageAssessments = $this->isLabs() && $project->hasPageAssessments();
        if ($hasPageAssessments) {
            $conditions['paSelects'] = ', pa_class, pa_importance, pa_page_revision';
            $conditions['paSelectsArchive'] = ', NULL AS pa_class, NULL AS pa_page_id, '.
                'NULL AS pa_page_revision';
            $conditions['paJoin'] = "LEFT JOIN $pageAssessmentsTable ON rev_page = pa_page_id";
            $conditions['revPageGroupBy'] = 'GROUP BY rev_page';
        }

        $sql = "SELECT * FROM (".
                    $this->getPagesCreatedInnerSql($project, $conditions, $deleted, $start, $end)."
                ) a ".
                "ORDER BY rev_timestamp DESC
                ".(!empty($limit) ? "LIMIT $limit OFFSET $offset" : '');

        $result = $this->executeQuery($sql, $project, $user, $namespace, $start, $end)->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get SQL fragments for the namespace and redirects,
     * to be used in self::getPagesCreatedInnerSql().
     * @param string|int $namespace Namespace ID or 'all'.
     * @param string $redirects One of 'noredirects', 'onlyredirects' or blank for both.
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

        if ('onlyredirects' == $redirects) {
            $conditions['redirects'] = " AND page_is_redirect = '1' ";
        } elseif ('noredirects' == $redirects) {
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
     * @param string $deleted One of 'live', 'deleted' or blank for both.
     * @param string $start Start date in a format accepted by strtotime()
     * @param string $end End date in a format accepted by strtotime()
     * @param bool $count Omit unneeded columns from the SELECT clause.
     * @return string Raw SQL.
     */
    private function getPagesCreatedInnerSql(
        Project $project,
        array $conditions,
        string $deleted,
        string $start = '',
        string $end = '',
        bool $count = false
    ): string {
        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');
        $archiveTable = $project->getTableName('archive');
        $logTable = $project->getTableName('logging', 'logindex');

        // Only SELECT things that are needed, based on whether or not we're doing a COUNT.
        $revSelects = "DISTINCT page_namespace AS `namespace`, 'rev' AS `type`, page_title, page_is_redirect";
        if (false === $count) {
            $revSelects .= ", page_len, rev_timestamp, rev_len, rev_id, NULL AS `recreated` ";
        }

        [$revCondBegin, $revCondEnd] = $this->getRevTimestampConditions($start, $end);
        [$arCondBegin, $arCondEnd] = $this->getRevTimestampConditions($start, $end, '', true);

        $revisionsSelect = "
            SELECT $revSelects ".$conditions['paSelects']."
            FROM $pageTable
            JOIN $revisionTable ON page_id = rev_page ".
            $conditions['paJoin']."
            WHERE ".$conditions['whereRev']."
                AND rev_parent_id = '0'".
                $conditions['namespaceRev'].
                $conditions['redirects'].
                $revCondBegin.
                $revCondEnd.
            $conditions['revPageGroupBy'];

        // Only SELECT things that are needed, based on whether or not we're doing a COUNT.
        $arSelects = "ar_namespace AS `namespace`, 'arc' AS `type`, ar_title AS page_title, '0' AS page_is_redirect";
        if (false === $count) {
            $arSelects .= ", NULL AS page_len, MIN(ar_timestamp) AS rev_timestamp, ".
                "ar_len AS rev_len, ar_rev_id AS rev_id, EXISTS(
                    SELECT 1 FROM $pageTable
                    WHERE page_namespace = ar_namespace
                    AND page_title = ar_title
                ) AS `recreated`";
        }

        $archiveSelect = "
            SELECT $arSelects ".$conditions['paSelectsArchive']."
            FROM $archiveTable
            LEFT JOIN $logTable ON log_namespace = ar_namespace AND log_title = ar_title
                AND log_user = ar_user AND (log_action = 'move' OR log_action = 'move_redir')
                AND log_type = 'move'
            WHERE ".$conditions['whereArc']."
                AND ar_parent_id = '0' ".
                $conditions['namespaceArc']."
                AND log_action IS NULL
                $arCondBegin
                $arCondEnd
            GROUP BY ar_namespace, ar_title";

        if ('live' == $deleted) {
            return $revisionsSelect;
        } elseif ('deleted' == $deleted) {
            return $archiveSelect;
        }

        return "($revisionsSelect) UNION ($archiveSelect)";
    }

    /**
     * Get the number of pages the user created by assessment.
     * @param Project $project
     * @param User $user
     * @param int|string $namespace
     * @param string $redirects
     * @param string $start Start date in a format accepted by strtotime()
     * @param string $end End date in a format accepted by strtotime()
     * @return array Keys are the assessment class, values are the counts.
     */
    public function getAssessmentCounts(
        Project $project,
        User $user,
        $namespace,
        string $redirects,
        string $start = '',
        string $end = ''
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
            $this->getUserConditions($project, $user, '' !== $start.$end)
        );
        [$revCondBegin, $revCondEnd] = $this->getRevTimestampConditions($start, $end);

        $sql = "SELECT pa_class AS `class`, COUNT(pa_class) AS `count` FROM (
                    SELECT DISTINCT page_id, IFNULL(pa_class, '') AS pa_class
                    FROM $pageTable
                    JOIN $revisionTable ON page_id = rev_page
                    LEFT JOIN $pageAssessmentsTable ON rev_page = pa_page_id
                    WHERE ".$conditions['whereRev']."
                    AND rev_parent_id = '0'".
                    $conditions['namespaceRev'].
                    $conditions['redirects'].
                    $revCondBegin.
                    $revCondEnd."
                    GROUP BY page_id
                ) a
                GROUP BY pa_class";

        $resultQuery = $this->executeProjectsQuery($sql);

        $assessments = [];
        while ($result = $resultQuery->fetch()) {
            $class = '' == $result['class'] ? '' : $result['class'];
            $assessments[$class] = $result['count'];
        }

        // Cache and return.
        return $this->setCache($cacheKey, $assessments);
    }
}
