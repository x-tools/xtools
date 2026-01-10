<?php

declare( strict_types = 1 );

namespace App\Repository;

use App\Exception\BadGatewayException;
use App\Model\Page;
use App\Model\Project;
use App\Model\User;
use DateTime;
use Doctrine\DBAL\Result;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpFoundation\Response;

/**
 * A PageRepository fetches data about Pages, either singularly or for multiple.
 * Despite the name, this does not have a direct correlation with the Pages tool.
 * @codeCoverageIgnore
 */
class PageRepository extends Repository {
	/**
	 * Get metadata about a single page from the API.
	 * @param Project $project The project to which the page belongs.
	 * @param string $pageTitle Page title.
	 * @return string[]|null Array with some of the following keys: pageid, title, missing, displaytitle, url.
	 *   Returns null if page does not exist.
	 */
	public function getPageInfo( Project $project, string $pageTitle ): ?array {
		$info = $this->getPagesInfo( $project, [ $pageTitle ] );
		return $info !== null ? array_shift( $info ) : null;
	}

	/**
	 * Get metadata about a set of pages from the API.
	 * @param Project $project The project to which the pages belong.
	 * @param string[] $pageTitles Array of page titles.
	 * @return array|null Array keyed by the page names, each element with some of the following keys: pageid,
	 *   title, missing, displaytitle, url. Returns null if page does not exist.
	 */
	public function getPagesInfo( Project $project, array $pageTitles ): ?array {
		$params = [
			'prop' => 'info|pageprops',
			'inprop' => 'protection|talkid|watched|watchers|notificationtimestamp|subjectid|url|displaytitle',
			'converttitles' => '',
			'titles' => implode( '|', $pageTitles ),
			'formatversion' => 2,
		];

		$res = $this->executeApiRequest( $project, $params );
		$result = [];
		if ( isset( $res['query']['pages'] ) ) {
			foreach ( $res['query']['pages'] as $pageInfo ) {
				$result[$pageInfo['title']] = $pageInfo;
			}
		} else {
			return null;
		}
		return $result;
	}

	/**
	 * Get the full page text of a set of pages.
	 * @param Project $project The project to which the pages belong.
	 * @param string[] $pageTitles Array of page titles.
	 * @return string[] Array keyed by the page names, with the page text as the values.
	 */
	public function getPagesWikitext( Project $project, array $pageTitles ): array {
		$params = [
			'prop' => 'revisions',
			'rvprop' => 'content',
			'titles' => implode( '|', $pageTitles ),
			'formatversion' => 2,
		];
		$res = $this->executeApiRequest( $project, $params );
		$result = [];

		if ( !isset( $res['query']['pages'] ) ) {
			return [];
		}

		foreach ( $res['query']['pages'] as $page ) {
			if ( isset( $page['revisions'][0]['content'] ) ) {
				$result[$page['title']] = $page['revisions'][0]['content'];
			} else {
				$result[$page['title']] = '';
			}
		}

		return $result;
	}

	/**
	 * Get revisions of a single page.
	 * @param Page $page The page.
	 * @param User|null $user Specify to get only revisions by the given user.
	 * @param false|int $start
	 * @param false|int $end
	 * @param int|null $limit
	 * @return string[] Each member with keys: id, timestamp, length,
	 *   minor, length_change, user_id, username, comment, sha, deleted, tags.
	 */
	public function getRevisions(
		Page $page,
		?User $user = null,
		false|int $start = false,
		false|int $end = false,
		?int $limit = null,
	): array {
		$cacheKey = $this->getCacheKey( func_get_args(), 'page_revisions' );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		$stmt = $this->getRevisionsStmt( $page, $user, $limit, $start, $end );
		$result = $stmt->fetchAllAssociative();

		// Cache and return.
		return $this->setCache( $cacheKey, $result );
	}

	/**
	 * Get the statement for a single revision, so that you can iterate row by row.
	 * @param Page $page The page.
	 * @param User|null $user Specify to get only revisions by the given user.
	 * @param ?int $limit Max number of revisions to process.
	 * @param false|int $start
	 * @param false|int $end
	 * @return Result
	 */
	public function getRevisionsStmt(
		Page $page,
		?User $user = null,
		?int $limit = null,
		false|int $start = false,
		false|int $end = false
	): Result {
		$revTable = $this->getTableName(
			$page->getProject()->getDatabaseName(),
			'revision',
			// Use 'revision' if there's no user, otherwise default to revision_userindex
			$user ? null : ''
		);
		$slotsTable = $page->getProject()->getTableName( 'slots' );
		$contentTable = $page->getProject()->getTableName( 'content' );
		$commentTable = $page->getProject()->getTableName( 'comment' );
		$actorTable = $page->getProject()->getTableName( 'actor' );
		$ctTable = $page->getProject()->getTableName( 'change_tag' );
		$ctdTable = $page->getProject()->getTableName( 'change_tag_def' );
		$userClause = $user ? "revs.rev_actor = :actorId AND " : "";

		$limitClause = '';
		if ( intval( $limit ) > 0 && isset( $numRevisions ) ) {
			$limitClause = "LIMIT $limit";
		}

		$dateConditions = $this->getDateConditions( $start, $end, false, 'revs.' );

		$sql = "SELECT * FROM (
                    SELECT
                        revs.rev_id AS `id`,
                        revs.rev_timestamp AS `timestamp`,
                        revs.rev_minor_edit AS `minor`,
                        revs.rev_len AS `length`,
                        (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS `length_change`,
                        actor_user AS user_id,
                        actor_name AS username,
                        comment_text AS `comment`,
                        content_sha1 AS `sha`,
                        revs.rev_deleted AS `deleted`,
                        (
                            SELECT JSON_ARRAYAGG(ctd_name)
                            FROM $ctTable
                            JOIN $ctdTable
                            ON ct_tag_id = ctd_id
                            WHERE ct_rev_id = revs.rev_id
                        ) as `tags`
                    FROM $revTable AS revs
                    JOIN $slotsTable ON slot_revision_id = revs.rev_id
                    JOIN $contentTable ON slot_content_id = content_id
                    LEFT JOIN $actorTable ON revs.rev_actor = actor_id
                    LEFT JOIN $revTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                    LEFT OUTER JOIN $commentTable ON comment_id = revs.rev_comment_id
                    WHERE $userClause revs.rev_page = :pageid $dateConditions
                    ORDER BY revs.rev_timestamp DESC
                    $limitClause
                ) a
                ORDER BY `timestamp` ASC";

		$params = [ 'pageid' => $page->getId() ];
		if ( $user ) {
			$params['actorId'] = $user->getActorId( $page->getProject() );
		}

		return $this->executeProjectsQuery( $page->getProject(), $sql, $params );
	}

	/**
	 * Get a count of the number of revisions of a single page
	 * @param Page $page The page.
	 * @param User|null $user Specify to only count revisions by the given user.
	 * @param false|int $start
	 * @param false|int $end
	 * @return int
	 */
	public function getNumRevisions(
		Page $page,
		?User $user = null,
		false|int $start = false,
		false|int $end = false
	): int {
		$cacheKey = $this->getCacheKey( func_get_args(), 'page_numrevisions' );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		// In this case revision is faster than revision_userindex if we're not querying by user.
		$revTable = $page->getProject()->getTableName(
			'revision',
			$user && $this->isWMF ? '_userindex' : ''
		);
		$userClause = $user ? "rev_actor = :actorId AND " : "";

		$dateConditions = $this->getDateConditions( $start, $end );

		$sql = "SELECT COUNT(*)
                FROM $revTable
                WHERE $userClause rev_page = :pageid $dateConditions";
		$params = [ 'pageid' => $page->getId() ];
		if ( $user ) {
			$params['rev_actor'] = $user->getActorId( $page->getProject() );
		}

		$result = (int)$this->executeProjectsQuery( $page->getProject(), $sql, $params )->fetchOne();

		// Cache and return.
		return $this->setCache( $cacheKey, $result );
	}

	/**
	 * Get any CheckWiki errors of a single page
	 * @param Page $page
	 * @return array Results from query
	 */
	public function getCheckWikiErrors( Page $page ): array {
		// Only support mainspace on Labs installations
		if ( $page->getNamespace() !== 0 || !$this->isWMF ) {
			return [];
		}

		$sql = "SELECT error, notice, found, name_trans AS name, prio, text_trans AS explanation
                FROM s51080__checkwiki_p.cw_error a
                JOIN s51080__checkwiki_p.cw_overview_errors b
                WHERE a.project = b.project
                AND a.project = :dbName
                AND a.title = :title
                AND a.error = b.id
                AND a.ok = 0";

		// remove _p if present
		$dbName = preg_replace( '/_p$/', '', $page->getProject()->getDatabaseName() );

		// Page title without underscores (str_replace just to be sure)
		$pageTitle = str_replace( '_', ' ', $page->getTitle() );

		return $this->getToolsConnection()->executeQuery( $sql, [
			'dbName' => $dbName,
			'title' => $pageTitle,
		] )->fetchAllAssociative();
	}

	/**
	 * Get or count all wikidata items for the given page, not just languages of sister projects.
	 * @param Page $page
	 * @param bool $count Set to true to get only a COUNT
	 * @return string[]|int Records as returned by the DB, or raw COUNT of the records.
	 */
	public function getWikidataItems( Page $page, bool $count = false ): array|int {
		if ( !$page->getWikidataId() ) {
			return $count ? 0 : [];
		}

		$wikidataId = ltrim( $page->getWikidataId(), 'Q' );

		$sql = "SELECT " . ( $count ? 'COUNT(*) AS count' : '*' ) . "
                FROM wikidatawiki_p.wb_items_per_site
                WHERE ips_item_id = :wikidataId";

		$result = $this->executeProjectsQuery( 'wikidatawiki', $sql, [
			'wikidataId' => $wikidataId,
		] )->fetchAllAssociative();

		return $count ? (int)$result[0]['count'] : $result;
	}

	/**
	 * Get number of in and outgoing links and redirects to the given page.
	 * @param Page $page
	 * @return string[] Counts with the keys 'links_ext_count', 'links_out_count',
	 *                  'links_in_count' and 'redirects_count'
	 */
	public function countLinksAndRedirects( Page $page ): array {
		$externalLinksTable = $page->getProject()->getTableName( 'externallinks' );
		$pageLinksTable = $page->getProject()->getTableName( 'pagelinks' );
		$linkTargetTable = $page->getProject()->getTableName( 'linktarget' );
		$redirectTable = $page->getProject()->getTableName( 'redirect' );

		$sql = "SELECT 'links_ext_count' AS type, COUNT(*) AS value
                FROM $externalLinksTable WHERE el_from = :id
                UNION
                SELECT 'links_out_count' AS type, COUNT(*) AS value
                FROM $pageLinksTable WHERE pl_from = :id
                UNION
                SELECT 'links_in_count' AS type, COUNT(*) AS value
                FROM $pageLinksTable
                JOIN $linkTargetTable ON lt_id = pl_target_id
                WHERE lt_namespace = :namespace AND lt_title = :title
                UNION
                SELECT 'redirects_count' AS type, COUNT(*) AS value
                FROM $redirectTable WHERE rd_namespace = :namespace AND rd_title = :title";

		$params = [
			'id' => $page->getId(),
			'title' => str_replace( ' ', '_', $page->getTitleWithoutNamespace() ),
			'namespace' => $page->getNamespace(),
		];

		return $this->executeProjectsQuery( $page->getProject(), $sql, $params )->fetchAllKeyValue();
	}

	/**
	 * Count wikidata items for the given page, not just languages of sister projects
	 * @param Page $page
	 * @return int Number of records.
	 */
	public function countWikidataItems( Page $page ): int {
		return $this->getWikidataItems( $page, true );
	}

	/**
	 * Get page views for the given page and timeframe.
	 * @fixme use Symfony Guzzle package.
	 * @param Page $page
	 * @param string|DateTime $start In the format YYYYMMDD
	 * @param string|DateTime $end In the format YYYYMMDD
	 * @return string[][][]
	 * @throws BadGatewayException
	 */
	public function getPageviews( Page $page, string|DateTime $start, string|DateTime $end ): array {
		// Pull from cache for each call during the same request.
		// FIXME: This is fine for now as we only fetch pageviews for one page at a time,
		//   but if that ever changes we'll need to use APCu cache or otherwise respect $page, $start and $end.
		//   Better of course would be to move to a Symfony CachingHttpClient instead of Guzzle across the board.
		static $pageviews;
		if ( isset( $pageviews ) ) {
			return $pageviews;
		}

		$title = rawurlencode( str_replace( ' ', '_', $page->getTitle() ) );

		if ( $start instanceof DateTime ) {
			$start = $start->format( 'Ymd' );
		} else {
			$start = ( new DateTime( $start ) )->format( 'Ymd' );
		}
		if ( $end instanceof DateTime ) {
			$end = $end->format( 'Ymd' );
		} else {
			$end = ( new DateTime( $end ) )->format( 'Ymd' );
		}

		$project = $page->getProject()->getDomain();

		$url = 'https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/' .
			"$project/all-access/user/$title/daily/$start/$end";

		try {
			$res = $this->guzzle->request( 'GET', $url, [
				// Five seconds should be plenty...
				RequestOptions::CONNECT_TIMEOUT => 5,
			] );
			$pageviews = json_decode( $res->getBody()->getContents(), true );
			return $pageviews;
		} catch ( ServerException | ConnectException $e ) {
			throw new BadGatewayException( 'api-error-wikimedia', [ 'Pageviews' ], $e );
		}
	}

	/**
	 * Get the full HTML content of the the page.
	 * @param Page $page
	 * @param ?int $revId What revision to query for.
	 * @return string
	 * @throws BadGatewayException
	 */
	public function getHTMLContent( Page $page, ?int $revId = null ): string {
		if ( $this->isWMF ) {
			$domain = $page->getProject()->getDomain();
			$url = "https://$domain/api/rest_v1/page/html/" . urlencode( str_replace( ' ', '_', $page->getTitle() ) );
			if ( $revId !== null ) {
				$url .= "/$revId";
			}
		} else {
			$url = $page->getUrl();
			if ( $revId !== null ) {
				$url .= "?oldid=$revId";
			}
		}

		try {
			return $this->guzzle->request( 'GET', $url )
				->getBody()
				->getContents();
		} catch ( ServerException $e ) {
			throw new BadGatewayException( 'api-error-wikimedia', [ 'Wikimedia REST' ], $e );
		} catch ( ClientException $e ) {
			if ( $page->exists() && Response::HTTP_NOT_FOUND === $e->getCode() ) {
				// Sometimes the REST API throws 404s when the page does in fact exist.
				throw new BadGatewayException( 'api-error-wikimedia', [ 'Wikimedia REST' ], $e );
			}
			throw $e;
		}
	}

	/**
	 * Get the ID of the revision of a page at the time of the given DateTime.
	 * @param Page $page
	 * @param DateTime $date
	 * @return int
	 */
	public function getRevisionIdAtDate( Page $page, DateTime $date ): int {
		$revisionTable = $page->getProject()->getTableName( 'revision' );
		$pageId = $page->getId();
		$datestamp = $date->format( 'YmdHis' );
		$sql = "SELECT MAX(rev_id)
                FROM $revisionTable
                WHERE rev_timestamp <= $datestamp
                AND rev_page = $pageId LIMIT 1;";
		$resultQuery = $this->getProjectsConnection( $page->getProject() )
			->executeQuery( $sql );
		return (int)$resultQuery->fetchOne();
	}

	/**
	 * Get HTML display titles of a set of pages (or the normal title if there's no display title).
	 * This will send t/50 API requests where t is the number of titles supplied.
	 * @param Project $project The project.
	 * @param string[] $pageTitles The titles to fetch.
	 * @return string[] Keys are the original supplied title, and values are the display titles.
	 */
	public function displayTitles( Project $project, array $pageTitles ): array {
		$displayTitles = [];
		$numPages = count( $pageTitles );

		for ( $n = 0; $n < $numPages; $n += 50 ) {
			$titleSlice = array_slice( $pageTitles, $n, 50 );
			$res = $this->guzzle->request( 'GET', $project->getApiUrl(), [ 'query' => [
				'action' => 'query',
				'prop' => 'info|pageprops',
				'inprop' => 'displaytitle',
				'titles' => implode( '|', $titleSlice ),
				'format' => 'json',
			] ] );
			$result = json_decode( $res->getBody()->getContents(), true );

			// Extract normalization info.
			$normalized = [];
			if ( isset( $result['query']['normalized'] ) ) {
				array_map(
					static function ( $e ) use ( &$normalized ): void {
						$normalized[$e['to']] = $e['from'];
					},
					$result['query']['normalized']
				);
			}

			// Match up the normalized titles with the display titles and the original titles.
			foreach ( $result['query']['pages'] as $pageInfo ) {
				$displayTitle = $pageInfo['pageprops']['displaytitle'] ?? $pageInfo['title'];
				$origTitle = $normalized[$pageInfo['title']] ?? $pageInfo['title'];
				$displayTitles[$origTitle] = $displayTitle;
			}
		}

		return $displayTitles;
	}
}
