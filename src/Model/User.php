<?php

declare( strict_types = 1 );

namespace App\Model;

use App\Repository\Repository;
use App\Repository\UserRepository;
use DateTime;
use Exception;
use UnexpectedValueException;
use Wikimedia\IPUtils;

/**
 * A User is a wiki user who has the same username across all projects in an XTools installation.
 */
class User extends Model {
	/** @var int Maximum queryable range for IPv4. */
	public const MAX_IPV4_CIDR = 16;

	/** @var int Maximum queryable range for IPv6. */
	public const MAX_IPV6_CIDR = 32;

	/** @var int[] Quick cache of edit counts, keyed by project domain. */
	protected array $editCounts = [];

	/** @var bool Whether the user is a temporary account. */
	protected bool $isTemp;

	/**
	 * Create a new User given a username.
	 * @param Repository|UserRepository $repository
	 * @param string $username
	 */
	public function __construct(
		protected Repository|UserRepository $repository,
		protected string $username
	) {
		if ( str_starts_with( $username, 'ipr-' ) ) {
			$username = substr( $username, 4 );
		}
		$this->username = ucfirst( str_replace( '_', ' ', trim( $username ) ) );

		// IPv6 address are stored as uppercase in the database.
		if ( $this->isIP() ) {
			$this->username = IPUtils::sanitizeIP( $this->username );
		}
	}

	/**
	 * Unique identifier for this User, to be used in cache keys. Use of md5 ensures the cache key does not contain
	 * reserved characters. You could also use the ID, but that may require an unnecessary DB query.
	 * @see Repository::getCacheKey()
	 * @return string
	 */
	public function getCacheKey(): string {
		return md5( $this->username );
	}

	/**
	 * Get the username.
	 * @return string
	 */
	public function getUsername(): string {
		return $this->username;
	}

	/**
	 * Get a prettified username for IP addresses. For accounts, just the username is returned.
	 * @return string
	 */
	public function getPrettyUsername(): string {
		if ( !$this->isIP() ) {
			return $this->username;
		}
		return IPUtils::prettifyIP( $this->username );
	}

	/**
	 * Get the username identifier that should be used in routing. This only matters for IP ranges,
	 * which get prefixed with 'ipr-' to ensure they don't conflict with other routing params (such as namespace ID).
	 * Always use this method when linking to or generating internal routes, and use it nowhere else.
	 * @return string
	 */
	public function getUsernameIdent(): string {
		if ( $this->isIpRange() ) {
			return 'ipr-' . $this->username;
		}
		return $this->username;
	}

	/**
	 * Is this an IP range?
	 * @return bool
	 */
	public function isIpRange(): bool {
		return IPUtils::isValidRange( $this->username );
	}

	/**
	 * Is this an IPv6 address or range?
	 * @return bool
	 */
	public function isIPv6(): bool {
		return IPUtils::isIPv6( $this->username );
	}

	/**
	 * Get the common characters between the start and end address of an IPv4 range.
	 * This is used when running a LIKE query against actor names.
	 * @return string[]|null
	 */
	public function getIpSubstringFromCidr(): ?string {
		if ( !$this->isIpRange() ) {
			return null;
		}

		if ( $this->isIPv6() ) {
			// Adapted from https://stackoverflow.com/a/10086404/604142 (CC BY-SA 3.0)
			[ $firstAddrStr, $prefixLen ] = explode( '/', $this->username );
			$firstAddrBin = inet_pton( $firstAddrStr );
			$firstAddrUnpacked = unpack( 'H*', $firstAddrBin );
			$firstAddrHex = reset( $firstAddrUnpacked );
			$range[0] = inet_ntop( $firstAddrBin );
			$flexBits = 128 - $prefixLen;
			$lastAddrHex = $firstAddrHex;

			$pos = 31;
			while ( $flexBits > 0 ) {
				$orig = substr( $lastAddrHex, $pos, 1 );
				$origVal = hexdec( $orig );
				$newVal = $origVal | ( pow( 2, min( 4, $flexBits ) ) - 1 );
				$new = dechex( $newVal );
				$lastAddrHex = substr_replace( $lastAddrHex, $new, $pos, 1 );
				$flexBits -= 4;
				$pos -= 1;
			}

			$lastAddrBin = pack( 'H*', $lastAddrHex );
			$range[1] = inet_ntop( $lastAddrBin );
		} else {
			$cidr = explode( '/', $this->username );
			$range[0] = long2ip( ip2long( $cidr[0] ) & -1 << ( 32 - (int)$cidr[1] ) );
			$range[1] = long2ip( ip2long( $range[0] ) + pow( 2, ( 32 - (int)$cidr[1] ) ) - 1 );
		}

		// Find the leftmost common characters between the two addresses.
		$common = '';
		$startSplit = str_split( strtoupper( $range[0] ) );
		$endSplit = str_split( strtoupper( $range[1] ) );
		foreach ( $startSplit as $index => $char ) {
			if ( $endSplit[$index] === $char ) {
				$common .= $char;
			} else {
				break;
			}
		}

		return $common;
	}

	/**
	 * Is this IP range outside the queryable limits?
	 * @return bool
	 */
	public function isQueryableRange(): bool {
		if ( !$this->isIpRange() ) {
			return true;
		}

		[ , $bits ] = IPUtils::parseCIDR( $this->username );
		$limit = $this->isIPv6() ? self::MAX_IPV6_CIDR : self::MAX_IPV4_CIDR;
		return (int)$bits >= $limit;
	}

	/**
	 * Get the user's ID on the given project.
	 * @param Project $project
	 * @return int|null
	 */
	public function getId( Project $project ): ?int {
		$ret = $this->repository->getIdAndRegistration(
			$project->getDatabaseName(),
			$this->getUsername()
		);

		return $ret ? (int)$ret['userId'] : null;
	}

	/**
	 * Get the user's actor ID on the given project.
	 * @param Project $project
	 * @return int
	 * Just returns a repository result.
	 * @codeCoverageIgnore
	 */
	public function getActorId( Project $project ): int {
		return (int)$this->repository->getActorId(
			$project->getDatabaseName(),
			$this->getUsername()
		);
	}

	/**
	 * Get the user's registration date on the given project.
	 * @param Project $project
	 * @return DateTime|null null if no registration date was found.
	 */
	public function getRegistrationDate( Project $project ): ?DateTime {
		$ret = $this->repository->getIdAndRegistration(
			$project->getDatabaseName(),
			$this->getUsername()
		);

		return $ret['regDate'] !== null ?
			DateTime::createFromFormat( 'YmdHis', $ret['regDate'] ) :
			null;
	}

	/**
	 * Get a user's local user rights on the given Project.
	 * @param Project $project
	 * @return string[]
	 */
	public function getUserRights( Project $project ): array {
		return $this->repository->getUserRights( $project, $this );
	}

	/**
	 * Get a list of this user's global rights.
	 * @param Project|null $project A project to query; if not provided, the default will be used.
	 * @return string[]
	 * Just returns a repository result.
	 * @codeCoverageIgnore
	 */
	public function getGlobalUserRights( ?Project $project = null ): array {
		return $this->repository->getGlobalUserRights( $this->getUsername(), $project );
	}

	/**
	 * Get the user's (system) edit count.
	 * @param Project $project
	 * @return int
	 */
	public function getEditCount( Project $project ): int {
		$domain = $project->getDomain();
		if ( isset( $this->editCounts[$domain] ) ) {
			return $this->editCounts[$domain];
		}

		$this->editCounts[$domain] = (int)$this->repository->getEditCount(
			$project->getDatabaseName(),
			$this->getUsername()
		);

		return $this->editCounts[$domain];
	}

	/**
	 * Number of edits which if exceeded, will require the user to log in.
	 * @return int
	 * Just returns a repository result.
	 * @codeCoverageIgnore
	 */
	public function numEditsRequiringLogin(): int {
		return $this->repository->numEditsRequiringLogin();
	}

	/**
	 * Maximum number of edits to process, based on configuration.
	 * @return int
	 */
	public function maxEdits(): int {
		return $this->repository->maxEdits();
	}

	/**
	 * Does this user exist on the given project?
	 * @param Project $project
	 * @return bool
	 */
	public function existsOnProject( Project $project ): bool {
		return $this->getId( $project ) > 0;
	}

	/**
	 * Does this user exist globally?
	 * @return bool
	 * Just returns a repository result.
	 * @codeCoverageIgnore
	 */
	public function existsGlobally(): bool {
		return $this->repository->existsGlobally( $this );
	}

	/**
	 * Is this user an Administrator on the given project?
	 * @param Project $project The project.
	 * @return bool
	 */
	public function isAdmin( Project $project ): bool {
		return in_array( 'sysop', $this->getUserRights( $project ) );
	}

	/**
	 * Is this user an IP user? (Not a named or temporary account)
	 * @return bool
	 */
	public function isIP(): bool {
		return IPUtils::isIPAddress( $this->username );
	}

	/**
	 * Is this user a temporary account?
	 * @param Project $project
	 * @return bool
	 */
	public function isTemp( Project $project ): bool {
		if ( !isset( $this->isTemp ) ) {
			$this->isTemp = self::isTempUsername( $project, $this->getUsername() );
		}
		return $this->isTemp;
	}

	/**
	 * Does the given username match that of temporary accounts?
	 * Based on https://w.wiki/BZQY from MediaWiki core (GPL-2.0-or-later)
	 * @param Project $project
	 * @param string $username
	 * @return bool
	 */
	public static function isTempUsername( Project $project, string $username ): bool {
		if ( !$project->hasTempAccounts() ) {
			return false;
		}
		foreach ( $project->getTempAccountPatterns() as $pattern ) {
			$varPos = strpos( $pattern, '$1' );
			if ( $varPos === false ) {
				throw new UnexpectedValueException( 'Invalid temp account pattern: ' . $pattern );
			}
			$prefix = substr( $pattern, 0, $varPos );
			$suffix = substr( $pattern, $varPos + 2 );
			$match = true;
			if ( $prefix !== '' ) {
				$match = str_starts_with( $username, $prefix );
			}
			if ( $match && $suffix !== '' ) {
				$match = str_ends_with( $username, $suffix )
					&& strlen( $username ) >= strlen( $prefix ) + strlen( $suffix );
			}
			if ( $match ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is this user an anonymous user (IP or temporary account)?
	 * @param Project $project
	 * @return bool
	 */
	public function isAnon( Project $project ): bool {
		return $this->isIP() || $this->isTemp( $project );
	}

	/**
	 * Get the number of active blocks on the user.
	 * @param Project $project The project.
	 * @return int Number of active blocks.
	 */
	public function countActiveBlocks( Project $project ): int {
		return (int)$this->repository->countActiveBlocks( $project, $this );
	}

	/**
	 * Is this user currently blocked on the given project?
	 * @param Project $project The project.
	 * @return bool
	 */
	public function isBlocked( Project $project ): bool {
		return $this->countActiveBlocks( $project ) > 0;
	}

	/**
	 * Does the user have enough edits that we want to require login?
	 * @param Project $project
	 * @return bool
	 */
	public function hasManyEdits( Project $project ): bool {
		$editCount = $this->getEditCount( $project );
		return $editCount > $this->numEditsRequiringLogin();
	}

	/**
	 * Does the user have more edits than maximum amount allowed for processing?
	 * @param Project $project
	 * @return bool
	 */
	public function hasTooManyEdits( Project $project ): bool {
		$editCount = $this->getEditCount( $project );
		return $this->maxEdits() > 0 && $editCount > $this->maxEdits();
	}

	/**
	 * Get edit count within given timeframe and namespace
	 * @param Project $project
	 * @param int|string $namespace Namespace ID or 'all' for all namespaces
	 * @param false|int $start Start date as Unix timestamp.
	 * @param int|false $end End date as Unix timestamp.
	 * @return int
	 * Just returns a repository result.
	 * @codeCoverageIgnore
	 */
	public function countEdits(
		Project $project,
		int|string $namespace = 'all',
		false|int $start = false,
		false|int $end = false
	): int {
		return $this->repository->countEdits( $project, $this, $namespace, $start, $end );
	}

	/**
	 * Is this user the same as the current XTools user?
	 * @return bool
	 */
	public function isCurrentlyLoggedIn(): bool {
		try {
			$ident = $this->repository->getXtoolsUserInfo();
		} catch ( Exception ) {
			return false;
		}
		return isset( $ident->username ) && $ident->username === $this->getUsername();
	}
}
