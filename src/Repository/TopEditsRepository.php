<?php

declare( strict_types = 1 );

namespace App\Repository;

use App\Model\Edit;
use App\Model\Page;
use App\Model\Project;
use App\Model\User;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Wikimedia\IPUtils;

/**
 * TopEditsRepository is responsible for retrieving data from the database
 * about the top-edited pages of a user. It doesn't do any post-processing
 * of that information.
 * @codeCoverageIgnore
 */
class TopEditsRepository extends UserRepository {
	public function __construct(
		protected ManagerRegistry $managerRegistry,
		protected CacheItemPoolInterface $cache,
		protected Client $guzzle,
		protected LoggerInterface $logger,
		protected ParameterBagInterface $parameterBag,
		protected bool $isWMF,
		protected int $queryTimeout,
		protected ProjectRepository $projectRepo,
		protected EditRepository $editRepo,
		protected UserRepository $userRepo,
		protected ?RequestStack $requestStack
	) {
		parent::__construct(
			$managerRegistry,
			$cache,
			$guzzle,
			$logger,
			$parameterBag,
			$isWMF,
			$queryTimeout,
			$projectRepo,
			$requestStack
		);
	}

	/**
	 * Factory to instantiate a new Edit for the given revision.
	 * @param Page $page
	 * @param array $revision
	 * @return Edit
	 */
	public function getEdit( Page $page, array $revision ): Edit {
		return new Edit( $this->editRepo, $this->userRepo, $page, $revision );
	}

	/**
	 * Get the selects for PageAssessments class and projects.
	 * @param Project $project
	 * @param int|string $namespace
	 * @return string
	 */
	private function getPaSelects(
		Project $project,
		$namespace
	): string {
		$hasPageAssessments = $this->isWMF && $project->hasPageAssessments( $namespace );
		$paTable = $project->getTableName( 'page_assessments' );
		$paSelect = $hasPageAssessments
			? ", (
                    SELECT pa_class
                    FROM $paTable
                    WHERE pa_page_id = page_id
                    AND pa_class != ''
                    LIMIT 1
                ) AS pa_class"
			: '';
		$paProjectsTable = $project->getTableName( 'page_assessments_projects' );
		$paProjectsSelect = $hasPageAssessments
			? ", (
                    SELECT JSON_ARRAYAGG(pap_project_title)
                    FROM $paTable
                    JOIN $paProjectsTable
                    ON pa_project_id = pap_project_id
                    WHERE pa_page_id = page_id
                ) AS pap_project_title"
			: '';
		return $paSelect . $paProjectsSelect;
	}

	/**
	 * Get the select and join for ProofreadPage quality level.
	 * @param Project $project
	 * @param int|string $namespace Namespade ID or 'all'
	 * @return array With keys 'prpSelect' and 'prpJoin'
	 */
	private function getPrpConditions(
		Project $project,
		$namespace
	): array {
		if ( $project->isPrpPage( $namespace ) ) {
			$pagePropsTable = $project->getTableName( 'page_props', '' );
			return [
				'prpSelect' => ", pp_value as `prp_quality`",
				'prpJoin' => "LEFT OUTER JOIN $pagePropsTable
                ON (pp_page, pp_propname) = (page_id, 'proofread_page_quality_level')",
			];
		}
		return [ 'prpSelect' => '', 'prpJoin' => '' ];
	}

	/**
	 * Get the top edits by a user in a single namespace.
	 * @param Project $project
	 * @param User $user
	 * @param int $namespace Namespace ID.
	 * @param int|false $start Start date as Unix timestamp.
	 * @param int|false $end End date as Unix timestamp.
	 * @param int $limit Number of edits to fetch.
	 * @param int $pagination Which page of results to return.
	 * @return string[] namespace, page_title, redirect, count (number of edits), assessment (page assessment).
	 */
	public function getTopEditsNamespace(
		Project $project,
		User $user,
		int $namespace = 0,
		int|false $start = false,
		int|false $end = false,
		int $limit = 1000,
		int $pagination = 0
	): array {
		// Set up cache.
		$cacheKey = $this->getCacheKey( func_get_args(), 'topedits_ns' );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		$revDateConditions = $this->getDateConditions( $start, $end );
		$pageTable = $project->getTableName( 'page' );
		$revisionTable = $project->getTableName( 'revision' );

		$ipcJoin = '';
		$whereClause = 'rev_actor = :actorId';
		$params = [];
		if ( $user->isIpRange() ) {
			$ipcTable = $project->getTableName( 'ip_changes' );
			$ipcJoin = "JOIN $ipcTable ON rev_id = ipc_rev_id";
			$whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
			[ $params['startIp'], $params['endIp'] ] = IPUtils::parseRange( $user->getUsername() );
		}

		$paSelects = $this->getPaSelects( $project, $namespace );

		$prpConditions = $this->getPrpConditions( $project, $namespace );

		$offset = $pagination * $limit;
		$sql = "SELECT page_namespace AS `namespace`, page_title,
                    page_is_redirect AS `redirect`, COUNT(page_title) AS `count`
                    $paSelects
                    " . $prpConditions['prpSelect'] . "
                FROM $pageTable
                JOIN $revisionTable ON page_id = rev_page
                " . $prpConditions['prpJoin'] . "
                $ipcJoin
                WHERE $whereClause
                AND page_namespace = :namespace
                $revDateConditions
                GROUP BY page_namespace, page_title
                ORDER BY count DESC
                LIMIT $limit
                OFFSET $offset";

		$resultQuery = $this->executeQuery( $sql, $project, $user, $namespace, $params );
		$result = $resultQuery->fetchAllAssociative();

		// Cache and return.
		return $this->setCache( $cacheKey, $result );
	}

	/**
	 * Count the number of pages edited in the given namespace.
	 * @param Project $project
	 * @param User $user
	 * @param int|string $namespace
	 * @param int|false $start Start date as Unix timestamp.
	 * @param int|false $end End date as Unix timestamp.
	 * @return mixed
	 */
	public function countPagesNamespace(
		Project $project,
		User $user,
		int|string $namespace,
		int|false $start = false,
		int|false $end = false
	) {
		// Set up cache.
		$cacheKey = $this->getCacheKey( func_get_args(), 'topedits_count_ns' );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		$revDateConditions = $this->getDateConditions( $start, $end );
		$pageTable = $project->getTableName( 'page' );
		$revisionTable = $project->getTableName( 'revision' );
		$nsCondition = is_numeric( $namespace ) ? 'AND page_namespace = :namespace' : '';

		$ipcJoin = '';
		$whereClause = 'rev_actor = :actorId';
		$params = [];
		if ( $user->isIpRange() ) {
			$ipcTable = $project->getTableName( 'ip_changes' );
			$ipcJoin = "JOIN $ipcTable ON rev_id = ipc_rev_id";
			$whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
			[ $params['startIp'], $params['endIp'] ] = IPUtils::parseRange( $user->getUsername() );
		}

		$sql = "SELECT COUNT(DISTINCT page_id)
                FROM $pageTable
                JOIN $revisionTable ON page_id = rev_page
                $ipcJoin
                WHERE $whereClause
                $nsCondition
                $revDateConditions";

		$resultQuery = $this->executeQuery( $sql, $project, $user, $namespace, $params );

		// Cache and return.
		return $this->setCache( $cacheKey, $resultQuery->fetchOne() );
	}

	/**
	 * Get the 10 Wikiprojects within which the user has the most edits.
	 * @param Project $project
	 * @param User $user
	 * @param int $ns
	 * @param int|false $start
	 * @param int|false $end
	 * @return array
	 */
	public function getProjectTotals(
		Project $project,
		User $user,
		int $ns,
		int|false $start = false,
		int|false $end = false
	): array {
		$cacheKey = $this->getCacheKey( func_get_args(), 'top_edits_wikiprojects' );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		$revDateConditions = $this->getDateConditions( $start, $end );
		$pageTable = $project->getTableName( 'page' );
		$revisionTable = $project->getTableName( 'revision' );
		$pageAssessmentsTable = $project->getTableName( 'page_assessments' );
		$paProjectsTable = $project->getTableName( 'page_assessments_projects' );

		$ipcJoin = '';
		$whereClause = 'rev_actor = :actorId';
		$params = [];
		if ( $user->isIpRange() ) {
			$ipcTable = $project->getTableName( 'ip_changes' );
			$ipcJoin = "JOIN $ipcTable ON rev_id = ipc_rev_id";
			$whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
			[ $params['startIp'], $params['endIp'] ] = IPUtils::parseRange( $user->getUsername() );
		}

		$sql = "SELECT pap_project_title, SUM(`edit_count`) AS `count`
                FROM (
                    SELECT page_id, COUNT(page_id) AS `edit_count`
                    FROM $revisionTable
                    $ipcJoin
                    JOIN $pageTable ON page_id = rev_page
                    WHERE $whereClause
                    AND page_namespace = :namespace
                    $revDateConditions
                    GROUP BY page_id
                ) a
                JOIN $pageAssessmentsTable ON pa_page_id = page_id
                JOIN $paProjectsTable ON pa_project_id = pap_project_id
                GROUP BY pap_project_title
                ORDER BY `count` DESC
                LIMIT 10";

		$totals = $this->executeQuery( $sql, $project, $user, $ns )
			->fetchAllAssociative();

		// Cache and return.
		return $this->setCache( $cacheKey, $totals );
	}

	/**
	 * Get the top edits by a user across all namespaces.
	 * @param Project $project
	 * @param User $user
	 * @param int|false $start Start date as Unix timestamp.
	 * @param int|false $end End date as Unix timestamp.
	 * @param int $limit Number of edits to fetch.
	 * @return string[] namespace, page_title, redirect, count (number of edits), assessment (page assessment).
	 */
	public function getTopEditsAllNamespaces(
		Project $project,
		User $user,
		int|false $start = false,
		int|false $end = false,
		int $limit = 10
	): array {
		// Set up cache.
		$cacheKey = $this->getCacheKey( func_get_args(), 'topedits_all' );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		$revDateConditions = $this->getDateConditions( $start, $end );
		$pageTable = $this->getTableName( $project->getDatabaseName(), 'page' );
		$revisionTable = $this->getTableName( $project->getDatabaseName(), 'revision' );

		$ipcJoin = '';
		$whereClause = 'rev_actor = :actorId';
		$params = [];
		if ( $user->isIpRange() ) {
			$ipcTable = $project->getTableName( 'ip_changes' );
			$ipcJoin = "JOIN $ipcTable ON rev_id = ipc_rev_id";
			$whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
			[ $params['startIp'], $params['endIp'] ] = IPUtils::parseRange( $user->getUsername() );
		}

		$paSelects = $this->getPaSelects( $project, 'all' );
		$prpConditions = $this->getPrpConditions( $project, 'all' );

		$sql = "
            SELECT * FROM (
                SELECT
                    page_namespace as `namespace`,
                    page_title,
                    page_is_redirect as `redirect`,
                    rev_page,
                    count(rev_page) AS `count`,
                    ROW_NUMBER() OVER (
                        PARTITION BY page_namespace
                        ORDER BY page_namespace ASC, `count` DESC
                    ) `row_number`
                    $paSelects
                    " . $prpConditions['prpSelect'] . "
                FROM $revisionTable
                $ipcJoin
                JOIN $pageTable ON page_id = rev_page
                " . $prpConditions['prpJoin'] . "
                WHERE $whereClause
                $revDateConditions
                GROUP BY page_namespace, rev_page
            ) a
            WHERE `row_number` <= $limit
            ORDER BY `namespace` ASC, `count` DESC";
		$resultQuery = $this->executeQuery( $sql, $project, $user, 'all', $params );
		$result = $resultQuery->fetchAllAssociative();

		// Cache and return.
		return $this->setCache( $cacheKey, $result );
	}

	/**
	 * Get the top edits by a user to a single page.
	 * @param Page $page
	 * @param User $user
	 * @param int|false $start Start date as Unix timestamp.
	 * @param int|false $end End date as Unix timestamp.
	 * @return string[][] Each row with keys 'id', 'timestamp', 'minor', 'length',
	 *   'length_change', 'reverted', 'user_id', 'username', 'comment', 'parent_comment'
	 */
	public function getTopEditsPage( Page $page, User $user, int|false $start = false, int|false $end = false ): array {
		// Set up cache.
		$cacheKey = $this->getCacheKey( func_get_args(), 'topedits_page' );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		$results = $this->queryTopEditsPage( $page, $user, $start, $end, true );

		// Now we need to get the most recent revision, since the childrevs stuff excludes it.
		$lastRev = $this->queryTopEditsPage( $page, $user, $start, $end, false );
		if ( empty( $results ) || $lastRev[0]['id'] !== $results[0]['id'] ) {
			$results = array_merge( $lastRev, $results );
		}

		// Cache and return.
		return $this->setCache( $cacheKey, $results );
	}

	/**
	 * The actual query to get the top edits by the user to the page.
	 * Because of the way the main query works, we aren't given the most recent revision,
	 * so we have to call this twice, once with $childRevs set to true and once with false.
	 * @param Page $page
	 * @param User $user
	 * @param int|false $start Start date as Unix timestamp.
	 * @param int|false $end End date as Unix timestamp.
	 * @param bool $childRevs Whether to include child revisions.
	 * @return array Each row with keys 'id', 'timestamp', 'minor', 'length',
	 *   'length_change', 'reverted', 'user_id', 'username', 'comment', 'parent_comment'
	 */
	private function queryTopEditsPage(
		Page $page,
		User $user,
		int|false $start = false,
		int|false $end = false,
		bool $childRevs = false
	): array {
		$project = $page->getProject();
		$revDateConditions = $this->getDateConditions( $start, $end, false, 'revs.' );
		$revTable = $project->getTableName( 'revision' );
		$commentTable = $project->getTableName( 'comment' );
		$tagTable = $project->getTableName( 'change_tag' );
		$tagDefTable = $project->getTableName( 'change_tag_def' );
		// sha1 temporarily disabled, see T407814/T389026
		if ( $childRevs ) {
			$childSelect = ", (
                    CASE WHEN
                        /* childrevs.rev_sha1 = parentrevs.rev_sha1
                        OR */ (
                            SELECT 1
                            FROM $tagTable
                            WHERE ct_rev_id = revs.rev_id
                            AND ct_tag_id = (
                                SELECT ctd_id
                                FROM $tagDefTable
                                WHERE ctd_name = 'mw-reverted'
                            )
                        )
                    THEN 1
                    ELSE 0
                    END
                ) AS `reverted`,
                childcomments.comment_text AS `parent_comment`";
			$childJoin = "LEFT JOIN $revTable AS childrevs ON (revs.rev_id = childrevs.rev_parent_id)
                LEFT OUTER JOIN $commentTable AS childcomments
                ON (childrevs.rev_comment_id = childcomments.comment_id)";
			$childWhere = 'AND childrevs.rev_page = :pageid';
			$childLimit = '';
		} else {
			$childSelect = ', "" AS parent_comment, 0 AS reverted';
			$childJoin = '';
			$childWhere = '';
			$childLimit = 'LIMIT 1';
		}

		$userId = $user->getId( $page->getProject() );
		$username = $this->getProjectsConnection( $project )->quote( $user->getUsername() );

		// IP range handling.
		$ipcJoin = '';
		$whereClause = 'revs.rev_actor = :actorId';
		$params = [ 'pageid' => $page->getId() ];
		if ( $user->isIpRange() ) {
			$ipcTable = $project->getTableName( 'ip_changes' );
			$ipcJoin = "JOIN $ipcTable ON revs.rev_id = ipc_rev_id";
			$whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
			[ $params['startIp'], $params['endIp'] ] = IPUtils::parseRange( $user->getUsername() );
		}

		$sql = "SELECT * FROM (
                    SELECT
                        revs.rev_id AS id,
                        revs.rev_timestamp AS timestamp,
                        revs.rev_minor_edit AS minor,
                        revs.rev_len AS length,
                        (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                        $userId AS user_id,
                        $username AS username,
                        comments.comment_text AS `comment`
                        $childSelect
                    FROM $revTable AS revs
                    LEFT JOIN $revTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                    $ipcJoin
                    LEFT OUTER JOIN $commentTable AS comments ON (revs.rev_comment_id = comments.comment_id)
                    $childJoin
                    WHERE $whereClause
                    $revDateConditions
                    AND revs.rev_page = :pageid
                    $childWhere
                ) a
                ORDER BY timestamp DESC
                $childLimit";

		$resultQuery = $this->executeQuery( $sql, $project, $user, null, $params );
		return $resultQuery->fetchAllAssociative();
	}
}
