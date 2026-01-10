<?php

declare( strict_types = 1 );

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
class PagesRepository extends UserRepository {
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
		string|int $namespace,
		string $redirects,
		string $deleted,
		int|false $start = false,
		int|false $end = false
	): array {
		$cacheKey = $this->getCacheKey( func_get_args(), 'num_user_pages_created' );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		$conditions = [
			'paSelects' => '',
			'paSelectsArchive' => '',
			'revPageGroupBy' => 'GROUP BY rev_page',
		];
		$conditions = array_merge(
			$conditions,
			$this->getNamespaceRedirectAndDeletedPagesConditions( $namespace, $redirects ),
			$this->getUserConditions( ( $start . $end ) !== '' ),
			$this->getPrpConditions( $namespace, $project )
		);

		$wasRedirect = $this->getWasRedirectClause( $redirects, $deleted );
		$summation = Pages::DEL_NONE !== $deleted ? 'redirect OR was_redirect' : 'redirect';

		$prpSelect = '';
		if ( $project->isPrpPage( $namespace ) ) {
			foreach ( [ 0, 1, 2, 3, 4 ] as $level ) {
				$prpSelect .= ", SUM(IF(`prp_quality` = $level, 1, 0)) AS `prp_quality$level`";
			}
		}

		$sql = "SELECT `namespace`,
                    COUNT(page_title) AS `count`,
                    SUM(IF(type = 'arc', 1, 0)) AS `deleted`,
                    SUM($summation) AS `redirects`,
                    SUM(rev_length) AS `total_length`
                    $prpSelect
                FROM (" .
			$this->getPagesCreatedInnerSql( $project, $conditions, $deleted, $start, $end, false, true ) . "
                ) a " .
				$wasRedirect .
				"GROUP BY `namespace`";

		$result = $this->executeQuery( $sql, $project, $user, $namespace )
			->fetchAllAssociative();

		// Cache and return.
		return $this->setCache( $cacheKey, $result );
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
		string|int $namespace,
		string $redirects,
		string $deleted,
		int|false $start = false,
		int|false $end = false,
		?int $limit = 1000,
		int|false $offset = false
	): array {
		$cacheKey = $this->getCacheKey( func_get_args(), 'user_pages_created' );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		// always group by rev_page, to address merges where 2 revisions with rev_parent_id=0
		$conditions = [
			'paSelects' => '',
			'paSelectsArchive' => '',
			'revPageGroupBy' => 'GROUP BY rev_page',
		];

		$conditions = array_merge(
			$conditions,
			$this->getNamespaceRedirectAndDeletedPagesConditions( $namespace, $redirects ),
			$this->getUserConditions( $start . $end !== '' ),
			$this->getPrpConditions( $namespace, $project )
		);

		$hasPageAssessments = $this->isWMF && $project->hasPageAssessments( $namespace );
		if ( $hasPageAssessments ) {
			$pageAssessmentsTable = $project->getTableName( 'page_assessments' );
			$paProjectsTable = $project->getTableName( 'page_assessments_projects' );
			$conditions['paSelects'] = ",
                (SELECT pa_class
                    FROM $pageAssessmentsTable
                    WHERE rev_page = pa_page_id
                    AND pa_class != ''
                    LIMIT 1
                ) AS pa_class,
                (SELECT JSON_ARRAYAGG(pap_project_title)
                    FROM $pageAssessmentsTable
                    JOIN $paProjectsTable
                    ON pa_project_id = pap_project_id
                    WHERE pa_page_id = page_id
                ) AS pap_project_title";
			$conditions['paSelectsArchive'] = ', NULL AS pa_class, NULL AS pap_project_title';
			$conditions['revPageGroupBy'] = 'GROUP BY rev_page';
		}

		$wasRedirect = $this->getWasRedirectClause( $redirects, $deleted );

		$sql = "SELECT * FROM (" .
			$this->getPagesCreatedInnerSql( $project, $conditions, $deleted, $start, $end, $offset ) . "
                ) a " .
				$wasRedirect .
				"ORDER BY `timestamp` DESC
                " . ( !empty( $limit ) ? "LIMIT $limit" : '' );

		$result = $this->executeQuery( $sql, $project, $user, $namespace )
			->fetchAllAssociative();

		// Cache and return.
		return $this->setCache( $cacheKey, $result );
	}

	private function getWasRedirectClause( string $redirects, string $deleted ): string {
		if ( Pages::REDIR_NONE === $redirects ) {
			return "WHERE was_redirect IS NULL ";
		} elseif ( Pages::REDIR_ONLY === $redirects && Pages::DEL_ONLY === $deleted ) {
			return "WHERE was_redirect = 1 ";
		} elseif ( Pages::REDIR_ONLY === $redirects && Pages::DEL_ALL === $deleted ) {
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
	private function getNamespaceRedirectAndDeletedPagesConditions( string|int $namespace, string $redirects ): array {
		$conditions = [
			'namespaceArc' => '',
			'namespaceRev' => '',
			'redirects' => '',
		];

		if ( $namespace !== 'all' ) {
			$conditions['namespaceRev'] = " AND page_namespace = '" . intval( $namespace ) . "' ";
			$conditions['namespaceArc'] = " AND ar_namespace = '" . intval( $namespace ) . "' ";
		}

		if ( Pages::REDIR_ONLY == $redirects ) {
			$conditions['redirects'] = " AND page_is_redirect = '1' ";
		} elseif ( Pages::REDIR_NONE == $redirects ) {
			$conditions['redirects'] = " AND page_is_redirect = '0' ";
		}

		return $conditions;
	}

	/**
	 * Get SQL fragments for ProofreadPage quality.
	 * @param int|string $namespace
	 * @param Project $project
	 * @return string[] With keys 'prpSelect', 'prpArSelect' and 'prpJoin'
	 */
	private function getPrpConditions( int|string $namespace, Project $project ): array {
		$conditions = [
			'prpSelect' => '',
			'prpArSelect' => '',
			'prpJoin' => '',
		];
		if ( $project->isPrpPage( $namespace ) ) {
			$pagePropsTable = $project->getTableName( 'page_props', '' );
			$conditions['prpSelect'] = ", pp_value AS `prp_quality`";
			$conditions['prpArSelect'] = ", NULL AS `prp_quality`";
			$conditions['prpJoin'] = "LEFT OUTER JOIN $pagePropsTable
                ON (pp_page, pp_propname) = (page_id, 'proofread_page_quality_level')";
		}
		return $conditions;
	}

	/**
	 * Inner SQL for getting or counting pages created by the user.
	 * @param Project $project
	 * @param string[] $conditions Conditions for the SQL, must include 'paSelects',
	 *     'paSelectsArchive', 'whereRev', 'whereArc', 'namespaceRev', 'namespaceArc',
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
		int|false $start,
		int|false $end,
		int|false $offset = false,
		bool $count = false
	): string {
		$pageTable = $project->getTableName( 'page' );
		$revisionTable = $project->getTableName( 'revision' );
		$archiveTable = $project->getTableName( 'archive' );
		$logTable = $project->getTableName( 'logging', 'logindex' );

		// Only SELECT things that are needed, based on whether or not we're doing a COUNT.
		$revSelects = "DISTINCT page_namespace AS `namespace`, 'rev' AS `type`, page_title, "
			. "page_is_redirect AS `redirect`, rev_len AS `rev_length`";
		if ( !$count ) {
			$revSelects .= ", page_len AS `length`, rev_timestamp AS `timestamp`, "
				. "rev_id, NULL AS `recreated` ";
		}

		$revDateConditions = $this->getDateConditions( $start, $end, $offset );
		$arDateConditions = $this->getDateConditions( $start, $end, $offset, '', 'ar_timestamp' );

		$tagTable = $project->getTableName( 'change_tag' );
		$tagDefTable = $project->getTableName( 'change_tag_def' );

		$revisionsSelect = "
            SELECT $revSelects " . $conditions['paSelects'] . $conditions['prpSelect'] . ",
                NULL AS was_redirect
            FROM $pageTable
            JOIN $revisionTable ON page_id = rev_page
            " . $conditions['prpJoin'] . "
            WHERE " . $conditions['whereRev'] . "
                AND rev_parent_id = '0'" .
				$conditions['namespaceRev'] .
				$conditions['redirects'] .
				$revDateConditions .
			$conditions['revPageGroupBy'];

		// Only SELECT things that are needed, based on whether or not we're doing a COUNT.
		$arSelects = "ar_namespace AS `namespace`, 'arc' AS `type`, ar_title AS `page_title`, "
			. "'0' AS `redirect`, ar_len AS `rev_length`";
		if ( !$count ) {
			$arSelects .= ", NULL AS `length`, MIN(ar_timestamp) AS `timestamp`, " .
				"ar_rev_id AS `rev_id`, EXISTS(
                    SELECT 1 FROM $pageTable
                    WHERE page_namespace = ar_namespace
                    AND page_title = ar_title
                ) AS `recreated`";
		}

		$archiveSelect = "
            SELECT $arSelects " . $conditions['paSelectsArchive'] . $conditions['prpArSelect'] . ",
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
            WHERE " . $conditions['whereArc'] . "
                AND ar_parent_id = '0' " .
				$conditions['namespaceArc'] . "
                AND log_action IS NULL
                $arDateConditions
            GROUP BY ar_namespace, ar_title";

		if ( $deleted === 'live' ) {
			return $revisionsSelect;
		} elseif ( $deleted === 'deleted' ) {
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
		int|string $namespace,
		string $redirects,
		int|false $start = false,
		int|false $end = false
	): array {
		$cacheKey = $this->getCacheKey( func_get_args(), 'user_pages_created_assessments' );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		$pageTable = $project->getTableName( 'page' );
		$revisionTable = $project->getTableName( 'revision' );
		$pageAssessmentsTable = $project->getTableName( 'page_assessments' );

		$conditions = array_merge(
			$this->getNamespaceRedirectAndDeletedPagesConditions( $namespace, $redirects ),
			$this->getUserConditions( $start . $end !== '' )
		);
		$revDateConditions = $this->getDateConditions( $start, $end );

		$paNamespaces = $project->getPageAssessments()::SUPPORTED_NAMESPACES;
		$paNamespaces = '(' . implode( ',', array_map( 'strval', $paNamespaces ) ) . ')';

		$sql = "SELECT pa_class AS `class`, COUNT(page_id) AS `count` FROM (
                    SELECT page_id,
                    (SELECT pa_class
                        FROM $pageAssessmentsTable
                        WHERE rev_page = pa_page_id
                        AND pa_class != ''
                        LIMIT 1
                    ) AS pa_class
                    FROM $pageTable
                    JOIN $revisionTable ON page_id = rev_page
                    WHERE " . $conditions['whereRev'] . "
                    AND rev_parent_id = '0'
                    AND (page_namespace in $paNamespaces)" .
					$conditions['namespaceRev'] .
					$conditions['redirects'] .
					$revDateConditions . "
                    GROUP BY page_id
                ) a
                GROUP BY pa_class";

		$resultQuery = $this->executeQuery( $sql, $project, $user, $namespace );

		$assessments = [];
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( $result = $resultQuery->fetchAssociative() ) {
			$class = $result['class'] == '' ? '' : $result['class'];
			$assessments[$class] = $result['count'];
		}

		// Cache and return.
		return $this->setCache( $cacheKey, $assessments );
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
	 * @return array Each element is an array with keys pap_project_title and count.
	 */
	public function getWikiprojectCounts(
		Project $project,
		User $user,
		int|string $namespace,
		string $redirects,
		int|false $start = false,
		int|false $end = false
	): array {
		$cacheKey = $this->getCacheKey( func_get_args(), 'user_pages_created_wikiprojects' );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		$pageTable = $project->getTableName( 'page' );
		$revisionTable = $project->getTableName( 'revision' );
		$pageAssessmentsTable = $project->getTableName( 'page_assessments' );
		$paProjectsTable = $project->getTableName( 'page_assessments_projects' );

		$conditions = array_merge(
			$this->getNamespaceRedirectAndDeletedPagesConditions( $namespace, $redirects ),
			$this->getUserConditions( $start . $end !== '' )
		);
		$revDateConditions = $this->getDateConditions( $start, $end );

		$sql = "SELECT pap_project_title, count(pap_project_title) as `count`
                FROM $pageTable
                LEFT JOIN $revisionTable ON page_id = rev_page
                JOIN $pageAssessmentsTable ON page_id = pa_page_id
                JOIN $paProjectsTable ON pa_project_id = pap_project_id
                WHERE " . $conditions['whereRev'] . "
                    AND rev_parent_id = '0'" .
					$conditions['namespaceRev'] .
					$conditions['redirects'] .
					$revDateConditions . "
                GROUP BY pap_project_title
                ORDER BY `count` DESC
                LIMIT 10";

		$totals = $this->executeQuery( $sql, $project, $user, $namespace )
			->fetchAllAssociative();

		// Cache and return.
		return $this->setCache( $cacheKey, $totals );
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
	public function getDeletionSummary( Project $project, int $namespace, string $pageTitle, string $offset ): array {
		$actorTable = $project->getTableName( 'actor' );
		$commentTable = $project->getTableName( 'comment' );
		$loggingTable = $project->getTableName( 'logging', 'logindex' );
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
		$ret = $this->executeProjectsQuery( $project, $sql, [
			'pageTitle' => str_replace( ' ', '_', $pageTitle ),
		] )->fetchAssociative();
		return $ret ?: [];
	}
}
