<?php

declare( strict_types = 1 );

namespace App\Repository;

use App\Exception\BadGatewayException;
use App\Model\Page;
use App\Model\Project;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;

/**
 * AuthorshipRepository is responsible for retrieving authorship data about a single page.
 * @codeCoverageIgnore
 */
class AuthorshipRepository extends Repository {
	/**
	 * Query the WikiWho service to get authorship percentages.
	 * @see https://api.wikiwho.net/
	 * @param Page $page
	 * @param int|null $revId ID of revision to target, or null for latest revision.
	 * @param bool $returnRevId Whether or not to include the relevant revision IDs with each token.
	 * @return array[]|null Response from WikiWho. null if something went wrong.
	 */
	public function getData( Page $page, ?int $revId, bool $returnRevId = false ): ?array {
		$cacheKey = $this->getCacheKey( func_get_args(), 'page_authorship' );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		$title = rawurlencode( str_replace( ' ', '_', $page->getTitle() ) );
		$projectLang = $page->getProject()->getLang();
		$oRevId = $returnRevId ? 'true' : 'false';

		$url = "https://wikiwho.wmcloud.org/$projectLang/api/v1.0.0-beta/rev_content/$title"
			. ( $revId ? "/$revId" : '' )
			. "/?o_rev_id=$oRevId&editor=true&token_id=false&out=false&in=false";

		$opts = [
			// Ignore HTTP errors to fail gracefully.
			'http_errors' => false,
			'timeout' => 60,
			'read_timeout' => 60,
		];

		try {
			$res = $this->guzzle->request( 'GET', $url, $opts );
		} catch ( ServerException | ConnectException $e ) {
			throw new BadGatewayException( 'api-error-wikimedia', [ 'WikiWho' ], $e );
		}

		// Cache and return.
		return $this->setCache( $cacheKey, json_decode( $res->getBody()->getContents(), true ) );
	}

	/**
	 * Get a map of user IDs/usernames given the user IDs.
	 * @param Project $project
	 * @param int[] $userIds
	 * @return array
	 */
	public function getUsernamesFromIds( Project $project, array $userIds ): array {
		$userTable = $project->getTableName( 'user' );
		$userIds = implode( ',', array_unique( array_filter( $userIds ) ) );
		$sql = "SELECT user_id, user_name
                FROM $userTable
                WHERE user_id IN ($userIds)";
		return $this->executeProjectsQuery( $project, $sql )->fetchAllAssociative();
	}
}
