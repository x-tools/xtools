<?php

declare( strict_types = 1 );

namespace App\Repository;

use App\Model\Project;
use App\Model\User;
use Doctrine\DBAL\Driver\ResultStatement;
use Wikimedia\IPUtils;

/**
 * An EditSummaryRepository is responsible for retrieving information from the
 * databases for the Edit Summary tool. It does not do any post-processing
 * of that data.
 * @codeCoverageIgnore
 */
class EditSummaryRepository extends UserRepository {
	/**
	 * Build and execute SQL to get edit summary usage.
	 * @param Project $project The project we're working with.
	 * @param User $user The user to process.
	 * @param string|int $namespace Namespace ID or 'all' for all namespaces.
	 * @param int|false $start Start date as Unix timestamp.
	 * @param int|false $end End date as Unix timestamp.
	 * @return ResultStatement
	 */
	public function getRevisions(
		Project $project,
		User $user,
		$namespace,
		$start = false,
		$end = false
	): ResultStatement {
		$revisionTable = $project->getTableName( 'revision' );
		$commentTable = $project->getTableName( 'comment' );
		$pageTable = $project->getTableName( 'page' );

		$revDateConditions = $this->getDateConditions( $start, $end );
		$condNamespace = 'all' === $namespace ? '' : 'AND page_namespace = :namespace';
		$pageJoin = 'all' === $namespace ? '' : "JOIN $pageTable ON rev_page = page_id";
		$params = [];
		$ipcJoin = '';
		$whereClause = 'rev_actor = :actorId';

		if ( $user->isIpRange() ) {
			$ipcTable = $project->getTableName( 'ip_changes' );
			$ipcJoin = "JOIN $ipcTable ON rev_id = ipc_rev_id";
			$whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
			[ $params['startIp'], $params['endIp'] ] = IPUtils::parseRange( $user->getUsername() );
		}

		$sql = "SELECT comment_text AS `comment`, rev_timestamp, rev_minor_edit
                FROM $revisionTable
                $ipcJoin
                $pageJoin
                LEFT OUTER JOIN $commentTable ON comment_id = rev_comment_id
                WHERE $whereClause
                $condNamespace
                $revDateConditions
                ORDER BY rev_timestamp DESC";

		return $this->executeQuery( $sql, $project, $user, $namespace, $params );
	}

	/**
	 * Loop through the revisions and tally up totals, based on callback that lives in the EditSummary model.
	 * @param array $processRow [EditSummary instance, 'method name']
	 * @param Project $project
	 * @param User $user
	 * @param int|string $namespace Namespace ID or 'all' for all namespaces.
	 * @param int|false $start Start date as Unix timestamp.
	 * @param int|false $end End date as Unix timestamp.
	 * @return array The final results.
	 */
	public function prepareData(
		array $processRow,
		Project $project,
		User $user,
		$namespace,
		$start = false,
		$end = false
	): array {
		$cacheKey = $this->getCacheKey( [ $project, $user, $namespace, $start, $end ], 'edit_summary_usage' );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		$resultQuery = $this->getRevisions( $project, $user, $namespace, $start, $end );
		$data = [];

		while ( $row = $resultQuery->fetchAssociative() ) {
			$data = call_user_func( $processRow, $row );
		}

		// Cache and return.
		return $this->setCache( $cacheKey, $data );
	}
}
