<?php

declare( strict_types=1 );

namespace App\Controller;

use App\Exception\XtoolsHttpException;
use App\Helper\I18nHelper;
use App\Model\Page;
use App\Model\Project;
use App\Model\User;
use App\Repository\PageRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Twig\Environment;
use Twig\Markup;
use Wikimedia\IPUtils;

/**
 * XtoolsController supplies a variety of methods around parsing and validating parameters, and initializing
 * Project/User instances. These are used in other controllers in the App\Controller namespace.
 * @abstract
 */
abstract class XtoolsController extends AbstractController {
	/** OTHER CLASS PROPERTIES */

	/** @var Request The request object. */
	protected Request $request;

	/** @var string Name of the action within the child controller that is being executed. */
	protected string $controllerAction;

	/** @var array Hash of params parsed from the Request. */
	protected array $params;

	/** @var bool Whether this is a request to an API action. */
	protected bool $isApi;

	/** @var Project Relevant Project parsed from the Request. */
	protected Project $project;

	/** @var User|null Relevant User parsed from the Request. */
	protected ?User $user = null;

	/** @var Page|null Relevant Page parsed from the Request. */
	protected ?Page $page = null;

	/** @var int|false Start date parsed from the Request. */
	protected int|false $start = false;

	/** @var int|false End date parsed from the Request. */
	protected int|false $end = false;

	/** @var int|string|null Namespace parsed from the Request, ID as int or 'all' for all namespaces. */
	protected int|string|null $namespace;

	/** @var int|false Unix timestamp. Pagination offset that substitutes for $end. */
	protected int|false $offset = false;

	/** @var int|null Number of results to return. */
	protected ?int $limit = 50;

	/** @var bool Is the current request a subrequest? */
	protected bool $isSubRequest;

	/**
	 * Stores user preferences such default project.
	 * This may get altered from the Request and updated in the Response.
	 * @var array
	 */
	protected array $cookies = [
		'XtoolsProject' => null,
	];

	/** OVERRIDABLE METHODS */

	/**
	 * Require the tool's index route (initial form) be defined here. This should also
	 * be the name of the associated model, if present.
	 * @return string
	 */
	abstract protected function getIndexRoute(): string;

	/**
	 * Override this to activate the 'too high edit count' functionality. The return value
	 * should represent the route name that we should be redirected to if the requested user
	 * has too high of an edit count.
	 * @return string|null Name of route to redirect to.
	 */
	protected function tooHighEditCountRoute(): ?string {
		return null;
	}

	/**
	 * Override this to specify which actions
	 * @return string[]
	 */
	protected function tooHighEditCountActionAllowlist(): array {
		return [];
	}

	/**
	 * Override to restrict a tool's access to only the specified projects, instead of any valid project.
	 * @return string[] Domain or DB names.
	 */
	protected function supportedProjects(): array {
		return [];
	}

	/**
	 * Override this to set which API actions for the controller require the
	 * target user to opt in to the restricted statistics.
	 * @see https://www.mediawiki.org/wiki/XTools/Edit_Counter#restricted_stats
	 * @return array
	 */
	protected function restrictedApiActions(): array {
		return [];
	}

	/**
	 * Override to set the maximum number of days allowed for the given date range.
	 * This will be used as the default date span unless $this->defaultDays() is overridden.
	 * @see XtoolsController::getUnixFromDateParams()
	 * @return int|null
	 */
	public function maxDays(): ?int {
		return null;
	}

	/**
	 * Override to set default days from current day, to use as the start date if none was provided.
	 * If this is null and $this->maxDays() is non-null, the latter will be used as the default.
	 * @return int|null
	 */
	protected function defaultDays(): ?int {
		return null;
	}

	/**
	 * Override to set the maximum number of results to show per page, default 5000.
	 * @return int
	 */
	protected function maxLimit(): int {
		return 5000;
	}

	/**
	 * XtoolsController constructor.
	 * @param ContainerInterface $container
	 * @param RequestStack $requestStack
	 * @param ManagerRegistry $managerRegistry
	 * @param CacheItemPoolInterface $cache
	 * @param Client $guzzle
	 * @param I18nHelper $i18n
	 * @param ProjectRepository $projectRepo
	 * @param UserRepository $userRepo
	 * @param PageRepository $pageRepo
	 * @param Environment $twig
	 * @param bool $isWMF
	 * @param string $defaultProject
	 */
	public function __construct(
		ContainerInterface $container,
		RequestStack $requestStack,
		protected ManagerRegistry $managerRegistry,
		protected CacheItemPoolInterface $cache,
		protected Client $guzzle,
		protected I18nHelper $i18n,
		protected ProjectRepository $projectRepo,
		protected UserRepository $userRepo,
		protected PageRepository $pageRepo,
		protected Environment $twig,
		/** @var bool Whether this is a WMF installation. */
		protected bool $isWMF,
		/** @var string The configured default project. */
		protected string $defaultProject,
	) {
		$this->container = $container;
		$this->request = $requestStack->getCurrentRequest();
		$this->params = $this->parseQueryParams();

		// Parse out the name of the controller and action.
		$pattern = "#::([a-zA-Z]*)Action#";
		$matches = [];
		// The blank string here only happens in the unit tests, where the request may not be made to an action.
		preg_match( $pattern, $this->request->get( '_controller' ) ?? '', $matches );
		$this->controllerAction = $matches[1] ?? '';

		// Whether the action is an API action.
		$this->isApi = str_ends_with( $this->controllerAction, 'Api' ) || $this->controllerAction === 'recordUsage';

		// Whether we're making a subrequest (the view makes a request to another action).
		$this->isSubRequest = $this->request->get( 'htmlonly' )
			|| $requestStack->getParentRequest() !== null;

		// Disallow AJAX (unless it's an API or subrequest).
		$this->checkIfAjax();

		// Load user options from cookies.
		$this->loadCookies();

		// Set the class-level properties based on params.
		if ( str_contains( strtolower( $this->controllerAction ), 'index' ) ) {
			// Index pages should only set the project, and no other class properties.
			$this->setProject( $this->getProjectFromQuery() );

			// ...except for transforming IP ranges. Because Symfony routes are separated by slashes, we need a way to
			// indicate a CIDR range because otherwise i.e. the path /sc/enwiki/192.168.0.0/24 could be interpreted as
			// the Simple Edit Counter for 192.168.0.0 in the namespace with ID 24. So we prefix ranges with 'ipr-'.
			// Further IP range handling logic is in the User class, i.e. see User::__construct, User::isIpRange.
			if ( isset( $this->params['username'] ) && IPUtils::isValidRange( $this->params['username'] ) ) {
				$this->params['username'] = 'ipr-' . $this->params['username'];
			}
		} else {
			// Includes the project.
			$this->setProperties();
		}

		// Check if the request is to a restricted API endpoint, where the target user has to opt-in to statistics.
		$this->checkRestrictedApiEndpoint();
	}

	/**
	 * Check if the request is AJAX, and disallow it unless they're using the API or if it's a subrequest.
	 */
	private function checkIfAjax(): void {
		if ( $this->request->isXmlHttpRequest() && !$this->isApi && !$this->isSubRequest ) {
			throw new HttpException(
				Response::HTTP_FORBIDDEN,
				$this->i18n->msg( 'error-automation', [ 'https://www.mediawiki.org/Special:MyLanguage/XTools/API' ] )
			);
		}
	}

	/**
	 * Check if the request is to a restricted API endpoint, and throw an exception if the target user hasn't opted-in.
	 * @throws XtoolsHttpException
	 */
	private function checkRestrictedApiEndpoint(): void {
		$restrictedAction = in_array( $this->controllerAction, $this->restrictedApiActions() );

		if ( $this->isApi && $restrictedAction && !$this->project->userHasOptedIn( $this->user ) ) {
			throw new XtoolsHttpException(
				$this->i18n->msg( 'not-opted-in', [
					$this->getOptedInPage()->getTitle(),
					$this->i18n->msg( 'not-opted-in-link' ) .
						' <https://www.mediawiki.org/wiki/Special:MyLanguage/XTools/Edit_Counter#restricted_stats>',
					$this->i18n->msg( 'not-opted-in-login' ),
				] ),
				'',
				$this->params,
				true,
				Response::HTTP_UNAUTHORIZED
			);
		}
	}

	/**
	 * Get the path to the opt-in page for restricted statistics.
	 * @return Page
	 */
	protected function getOptedInPage(): Page {
		return new Page( $this->pageRepo, $this->project, $this->project->userOptInPage( $this->user ) );
	}

	/***********
	 * COOKIES *
	 */

	/**
	 * Load user preferences from the associated cookies.
	 */
	private function loadCookies(): void {
		// Not done for subrequests.
		if ( $this->isSubRequest ) {
			return;
		}

		foreach ( array_keys( $this->cookies ) as $name ) {
			$this->cookies[$name] = $this->request->cookies->get( $name );
		}
	}

	/**
	 * Set cookies on the given Response.
	 * @param Response $response
	 */
	private function setCookies( Response $response ): void {
		// Not done for subrequests.
		if ( $this->isSubRequest ) {
			return;
		}

		foreach ( $this->cookies as $name => $value ) {
			$response->headers->setCookie(
				Cookie::create( $name, $value )
			);
		}
	}

	/**
	 * Sets the project, with the domain in $this->cookies['XtoolsProject'] that will
	 * later get set on the Response headers in self::getFormattedResponse().
	 * @param Project $project
	 */
	private function setProject( Project $project ): void {
		$this->project = $project;
		$this->cookies['XtoolsProject'] = $project->getDomain();
	}

	/****************************
	 * SETTING CLASS PROPERTIES *
	 */

	/**
	 * Normalize all common parameters used by the controllers and set class properties.
	 */
	private function setProperties(): void {
		$this->namespace = $this->params['namespace'] ?? null;

		// Offset is given as ISO timestamp and is stored as a UNIX timestamp (or false).
		if ( isset( $this->params['offset'] ) ) {
			$this->offset = strtotime( $this->params['offset'] );
		}

		// Limit needs to be an int.
		if ( isset( $this->params['limit'] ) ) {
			// Normalize.
			$this->params['limit'] = min( max( 1, (int)$this->params['limit'] ), $this->maxLimit() );
			$this->limit = $this->params['limit'];
		}

		if ( isset( $this->params['project'] ) ) {
			$this->setProject( $this->validateProject( $this->params['project'] ) );
		} elseif ( $this->cookies['XtoolsProject'] !== null ) {
			// Set from cookie.
			$this->setProject(
				$this->validateProject( $this->cookies['XtoolsProject'] )
			);
		}

		if ( isset( $this->params['username'] ) ) {
			$this->user = $this->validateUser( $this->params['username'] );
		}
		if ( isset( $this->params['page'] ) ) {
			$this->page = $this->getPageFromNsAndTitle( $this->namespace, $this->params['page'] );
		}

		$this->setDates();
	}

	/**
	 * Set class properties for dates, if such params were passed in.
	 */
	private function setDates(): void {
		$start = $this->params['start'] ?? false;
		$end = $this->params['end'] ?? false;
		if ( $start || $end || $this->maxDays() !== null ) {
			[ $this->start, $this->end ] = $this->getUnixFromDateParams( $start, $end );

			// Set $this->params accordingly too, so that for instance API responses will include it.
			$this->params['start'] = is_int( $this->start ) ? date( 'Y-m-d', $this->start ) : false;
			$this->params['end'] = is_int( $this->end ) ? date( 'Y-m-d', $this->end ) : false;
		}
	}

	/**
	 * Construct a fully qualified page title given the namespace and title.
	 * @param int|string $ns Namespace ID.
	 * @param string $title Page title.
	 * @param bool $rawTitle Return only the title (and not a Page).
	 * @return Page|string
	 */
	protected function getPageFromNsAndTitle( $ns, string $title, bool $rawTitle = false ) {
		if ( (int)$ns === 0 ) {
			return $rawTitle ? $title : $this->validatePage( $title );
		}

		// Prepend namespace and strip out duplicates.
		$nsName = $this->project->getNamespaces()[$ns] ?? $this->i18n->msg( 'unknown' );
		$title = $nsName . ':' . preg_replace( '/^' . $nsName . ':/', '', $title );
		return $rawTitle ? $title : $this->validatePage( $title );
	}

	/**
	 * Get a Project instance from the project string, using defaults if the given project string is invalid.
	 * @return Project
	 */
	public function getProjectFromQuery(): Project {
		// Set default project so we can populate the namespace selector on index pages.
		// Defaults to project stored in cookie, otherwise project specified in parameters.yml.
		if ( isset( $this->params['project'] ) ) {
			$project = $this->params['project'];
		} elseif ( $this->cookies['XtoolsProject'] !== null ) {
			$project = $this->cookies['XtoolsProject'];
		} else {
			$project = $this->defaultProject;
		}

		$projectData = $this->projectRepo->getProject( $project );

		// Revert back to defaults if we've established the given project was invalid.
		if ( !$projectData->exists() ) {
			$projectData = $this->projectRepo->getProject( $this->defaultProject );
		}

		return $projectData;
	}

	/*************************
	 * GETTERS / VALIDATIONS *
	 */

	/**
	 * Validate the given project, returning a Project if it is valid or false otherwise.
	 * @param string $projectQuery Project domain or database name.
	 * @return Project
	 * @throws XtoolsHttpException
	 */
	public function validateProject( string $projectQuery ): Project {
		$project = $this->projectRepo->getProject( $projectQuery );

		// Check if it is an explicitly allowed project for the current tool.
		if ( $this->supportedProjects() && !in_array( $project->getDomain(), $this->supportedProjects() ) ) {
			$this->throwXtoolsException(
				$this->getIndexRoute(),
				'error-authorship-unsupported-project',
				[ $this->params['project'] ],
				'project'
			);
		}

		if ( !$project->exists() ) {
			$this->throwXtoolsException(
				$this->getIndexRoute(),
				'invalid-project',
				[ $this->params['project'] ],
				'project'
			);
		}

		return $project;
	}

	/**
	 * Validate the given user, returning a User or Redirect if they don't exist.
	 * @param string $username
	 * @return User
	 * @throws XtoolsHttpException
	 */
	public function validateUser( string $username ): User {
		$user = new User( $this->userRepo, $username );

		// Allow querying for any IP, currently with no edit count limitation...
		// Once T188677 is resolved IPs will be affected by the EXPLAIN results.
		if ( $user->isIP() ) {
			// Validate CIDR limits.
			if ( !$user->isQueryableRange() ) {
				$limit = $user->isIPv6() ? User::MAX_IPV6_CIDR : User::MAX_IPV4_CIDR;
				$this->throwXtoolsException( $this->getIndexRoute(), 'ip-range-too-wide', [ $limit ], 'username' );
			}
			return $user;
		}

		// Check against centralauth for global tools.
		$isGlobalTool = str_contains( $this->request->get( '_controller', '' ), 'Global' );
		if ( $isGlobalTool && !$user->existsGlobally() ) {
			$this->throwXtoolsException( $this->getIndexRoute(), 'user-not-found', [], 'username' );
		} elseif ( !$isGlobalTool && isset( $this->project ) && !$user->existsOnProject( $this->project ) ) {
			// Don't continue if the user doesn't exist.
			$this->throwXtoolsException( $this->getIndexRoute(), 'user-not-found', [], 'username' );
		}

		if ( isset( $this->project ) && $user->hasManyEdits( $this->project ) ) {
			$this->handleHasManyEdits( $user );
		}

		return $user;
	}

	private function handleHasManyEdits( User $user ): void {
		$originalParams = $this->params;
		$actionAllowlisted = in_array( $this->controllerAction, $this->tooHighEditCountActionAllowlist() );

		// Reject users with a crazy high edit count.
		if ( $this->tooHighEditCountRoute() &&
			!$actionAllowlisted &&
			$user->hasTooManyEdits( $this->project )
		) {
			/** TODO: Somehow get this to use self::throwXtoolsException */

			// If redirecting to a different controller, show an informative message accordingly.
			if ( $this->tooHighEditCountRoute() !== $this->getIndexRoute() ) {
				// FIXME: This is currently only done for Edit Counter, redirecting to Simple Edit Counter,
				//   so this bit is hardcoded. We need to instead give the i18n key of the route.
				$redirMsg = $this->i18n->msg( 'too-many-edits-redir', [
					$this->i18n->msg( 'tool-simpleeditcounter' ),
				] );
				$msg = $this->i18n->msg( 'too-many-edits', [
						$this->i18n->numberFormat( $user->maxEdits() ),
					] ) . '. ' . $redirMsg;
				$this->addFlashMessage( 'danger', $msg );
			} else {
				$this->addFlashMessage( 'danger', 'too-many-edits', [
					$this->i18n->numberFormat( $user->maxEdits() ),
				] );

				// Redirecting back to index, so remove username (otherwise we'd get a redirect loop).
				unset( $this->params['username'] );
			}

			// Clear flash bag for API responses, since they get intercepted in ExceptionListener
			// and would otherwise be shown in subsequent requests.
			if ( $this->isApi ) {
				$this->getFlashBag()?->clear();
			}

			throw new XtoolsHttpException(
				$this->i18n->msg( 'too-many-edits', [ $user->maxEdits() ] ),
				$this->generateUrl( $this->tooHighEditCountRoute(), $this->params ),
				$originalParams,
				$this->isApi,
				Response::HTTP_NOT_IMPLEMENTED
			);
		}

		// Require login for users with a semi-crazy high edit count.
		// For now, this only effects HTML requests and not the API.
		if ( !$this->isApi && !$actionAllowlisted && !$this->request->getSession()->get( 'logged_in_user' ) ) {
			throw new AccessDeniedHttpException( 'error-login-required' );
		}
	}

	/**
	 * Get a Page instance from the given page title, and validate that it exists.
	 * @param string $pageTitle
	 * @return Page
	 * @throws XtoolsHttpException
	 */
	public function validatePage( string $pageTitle ): Page {
		$page = new Page( $this->pageRepo, $this->project, $pageTitle );

		if ( !$page->exists() ) {
			$this->throwXtoolsException(
				$this->getIndexRoute(),
				'no-result',
				[ $this->params['page'] ?? null ],
				'page'
			);
		}

		return $page;
	}

	/**
	 * Throw an XtoolsHttpException, which the given error message and redirects to specified action.
	 * @param string $redirectAction Name of action to redirect to.
	 * @param string $message i18n key of error message. Shown in API responses.
	 *   If no message with this key exists, $message is shown as-is.
	 * @param array $messageParams
	 * @param string|null $invalidParam This will be removed from $this->params. Omit if you don't want this to happen.
	 * @throws XtoolsHttpException
	 */
	public function throwXtoolsException(
		string $redirectAction,
		string $message,
		array $messageParams = [],
		?string $invalidParam = null
	): void {
		$this->addFlashMessage( 'danger', $message, $messageParams );
		$originalParams = $this->params;

		// Remove invalid parameter if it was given.
		if ( is_string( $invalidParam ) ) {
			unset( $this->params[$invalidParam] );
		}

		// We sometimes are redirecting to the index page, so also remove project (otherwise we'd get a redirect loop).
		/**
		 * FIXME: Index pages should have a 'nosubmit' parameter to prevent submission.
		 * Then we don't even need to remove $invalidParam.
		 * Better, we should show the error on the results page, with no results.
		 */
		unset( $this->params['project'] );

		// Throw exception which will redirect to $redirectAction.
		throw new XtoolsHttpException(
			$this->i18n->msgIfExists( $message, $messageParams ),
			$this->generateUrl( $redirectAction, $this->params ),
			$originalParams,
			$this->isApi
		);
	}

	/******************
	 * PARSING PARAMS *
	 */

	/**
	 * Get all standardized parameters from the Request, either via URL query string or routing.
	 * @return string[]
	 */
	public function getParams(): array {
		$paramsToCheck = [
			'project',
			'username',
			'namespace',
			'page',
			'categories',
			'group',
			'redirects',
			'deleted',
			'start',
			'end',
			'offset',
			'limit',
			'format',
			'tool',
			'tools',
			'q',
			'include_pattern',
			'exclude_pattern',
			'classonly',
			'countsOnly',

			// Legacy parameters.
			'user',
			'name',
			'article',
			'wiki',
			'wikifam',
			'lang',
			'wikilang',
			'begin',
		];

		/** @var string[] $params Each parameter that was detected along with its value. */
		$params = [];

		foreach ( $paramsToCheck as $param ) {
			// Pull in either from URL query string or route.
			$value = $this->request->query->get( $param ) ?: $this->request->get( $param );

			// Only store if value is given ('namespace' or 'username' could be '0').
			if ( $value !== null && $value !== '' ) {
				$params[$param] = rawurldecode( (string)$value );
			}
		}

		return $params;
	}

	/**
	 * Parse out common parameters from the request. These include the 'project', 'username', 'namespace' and 'page',
	 * along with their legacy counterparts (e.g. 'lang' and 'wiki').
	 * @return string[] Normalized parameters (no legacy params).
	 */
	public function parseQueryParams(): array {
		$params = $this->getParams();

		// Covert any legacy parameters, if present.
		$params = $this->convertLegacyParams( $params );

		// Remove blank values.
		return array_filter( $params, static function ( $param ) {
			// 'namespace' or 'username' could be '0'.
			return $param !== null && $param !== '';
		} );
	}

	/**
	 * Get Unix timestamps from given start and end string parameters. This also makes $start $maxDays() before
	 * $end if not present, and makes $end the current time if not present.
	 * The date range will not exceed $this->maxDays() days, if this public class property is set.
	 * @param int|string|false $start Unix timestamp or string accepted by strtotime.
	 * @param int|string|false $end Unix timestamp or string accepted by strtotime.
	 * @return int[] Start and end date as UTC timestamps.
	 */
	public function getUnixFromDateParams( $start, $end ): array {
		$today = strtotime( 'today midnight' );

		// start time should not be in the future.
		$startTime = min(
			is_int( $start ) ? $start : strtotime( (string)$start ),
			$today
		);

		// end time defaults to now, and will not be in the future.
		$endTime = min(
			( is_int( $end ) ? $end : strtotime( (string)$end ) ) ?: $today,
			$today
		);

		// Default to $this->defaultDays() or $this->maxDays() before end time if start is not present.
		$daysOffset = $this->defaultDays() ?? $this->maxDays();
		if ( $startTime === false && $daysOffset ) {
			$startTime = strtotime( "-$daysOffset days", $endTime );
		}

		// Default to $this->defaultDays() or $this->maxDays() after start time if end is not present.
		if ( $end === false && $daysOffset ) {
			$endTime = min(
				strtotime( "+$daysOffset days", $startTime ),
				$today
			);
		}

		// Reverse if start date is after end date.
		if ( $startTime > $endTime && $startTime !== false && $end !== false ) {
			$newEndTime = $startTime;
			$startTime = $endTime;
			$endTime = $newEndTime;
		}

		// Finally, don't let the date range exceed $this->maxDays().
		$startObj = DateTime::createFromFormat( 'U', (string)$startTime );
		$endObj = DateTime::createFromFormat( 'U', (string)$endTime );
		if ( $this->maxDays() && $startObj->diff( $endObj )->days > $this->maxDays() ) {
			// Show warnings that the date range was truncated.
			$this->addFlashMessage( 'warning', 'date-range-too-wide', [ $this->maxDays() ] );

			$startTime = strtotime( '-' . $this->maxDays() . ' days', $endTime );
		}

		return [ $startTime, $endTime ];
	}

	/**
	 * Given the params hash, normalize any legacy parameters to their modern equivalent.
	 * @param string[] $params
	 * @return string[]
	 */
	private function convertLegacyParams( array $params ): array {
		$paramMap = [
			'user' => 'username',
			'name' => 'username',
			'article' => 'page',
			'begin' => 'start',

			// Copy super legacy project params to legacy so we can concatenate below.
			'wikifam' => 'wiki',
			'wikilang' => 'lang',
		];

		// Copy legacy parameters to modern equivalent.
		foreach ( $paramMap as $legacy => $modern ) {
			if ( isset( $params[$legacy] ) ) {
				$params[$modern] = $params[$legacy];
				unset( $params[$legacy] );
			}
		}

		// Separate parameters for language and wiki.
		if ( isset( $params['wiki'] ) && isset( $params['lang'] ) ) {
			// 'wikifam' may be like '.wikipedia.org', vs just 'wikipedia',
			// so we must remove leading periods and trailing .org's.
			$params['project'] = $params['lang'] . '.' . rtrim( ltrim( $params['wiki'], '.' ), '.org' ) . '.org';
			unset( $params['wiki'] );
			unset( $params['lang'] );
		}

		return $params;
	}

	/************************
	 * FORMATTING RESPONSES *
	 */

	/**
	 * Get the rendered template for the requested format. This method also updates the cookies.
	 * @param string $templatePath Path to template without format,
	 *   such as '/editCounter/latest_global'.
	 * @param array $ret Data that should be passed to the view.
	 * @return Response
	 * @codeCoverageIgnore
	 */
	public function getFormattedResponse( string $templatePath, array $ret ): Response {
		$format = $this->request->query->get( 'format', 'html' );
		if ( $format == '' ) {
			// The default above doesn't work when the 'format' parameter is blank.
			$format = 'html';
		}

		// Merge in common default parameters, giving $ret (from the caller) the priority.
		$ret = array_merge( [
			'project' => $this->project,
			'user' => $this->user,
			'page' => $this->page ?? null,
			'namespace' => $this->namespace,
			'start' => $this->start,
			'end' => $this->end,
		], $ret );

		$formatMap = [
			'wikitext' => 'text/plain',
			'csv' => 'text/csv',
			'tsv' => 'text/tab-separated-values',
			'json' => 'application/json',
		];

		$response = new Response();

		// Set cookies. Note this must be done before rendering the view, as the view may invoke subrequests.
		$this->setCookies( $response );

		// If requested format does not exist, assume HTML.
		if ( $this->twig->getLoader()->exists( "$templatePath.$format.twig" ) === false ) {
			$format = 'html';
		}

		$response = $this->render( "$templatePath.$format.twig", $ret, $response );

		$contentType = $formatMap[$format] ?? 'text/html';
		$response->headers->set( 'Content-Type', $contentType );

		if ( in_array( $format, [ 'csv', 'tsv' ] ) ) {
			$filename = $this->getFilenameForRequest();
			$response->headers->set(
				'Content-Disposition',
				"attachment; filename=\"{$filename}.$format\""
			);
		}

		return $response;
	}

	/**
	 * Returns given filename from the current Request, with problematic characters filtered out.
	 * @return string
	 */
	private function getFilenameForRequest(): string {
		$filename = trim( $this->request->getPathInfo(), '/' );
		return trim( preg_replace( '/[-\/:;*?|<>%#"]+/', '-', $filename ) );
	}

	/**
	 * Return a JsonResponse object pre-supplied with the requested params.
	 * @param array $data
	 * @param int $responseCode
	 * @return JsonResponse
	 */
	public function getFormattedApiResponse( array $data, int $responseCode = Response::HTTP_OK ): JsonResponse {
		$response = new JsonResponse();
		$response->setEncodingOptions( JSON_NUMERIC_CHECK );
		$response->setStatusCode( $responseCode );

		// Normalize display of IP ranges (they are prefixed with 'ipr-' in the params).
		if ( $this->user && $this->user->isIpRange() ) {
			$this->params['username'] = $this->user->getUsername();
		}

		$ret = array_merge( $this->params, [
			// In some controllers, $this->params['project'] may be overridden with a Project object.
			'project' => $this->project->getDomain(),
		], $data );

		// Merge in flash messages, putting them at the top.
		$flashes = $this->getFlashBag()?->peekAll() ?? [];
		$ret = array_merge( $flashes, $ret );

		// Flashes now can be cleared after merging into the response.
		$this->getFlashBag()?->clear();

		// Normalize path param values.
		$ret = self::normalizeApiProperties( $ret );

		$response->setData( $ret );

		return $response;
	}

	/**
	 * Normalize the response data, adding in the elapsed_time.
	 * @param array $params
	 * @return array
	 */
	public static function normalizeApiProperties( array $params ): array {
		foreach ( $params as $param => $value ) {
			if ( $value === false ) {
				// False values must be empty params.
				unset( $params[$param] );
			} elseif ( is_string( $value ) && str_contains( $value, '|' ) ) {
				// Any pipe-separated values should be returned as an array.
				$params[$param] = explode( '|', $value );
			} elseif ( $value instanceof DateTime ) {
				// Convert DateTime objects to ISO 8601 strings.
				$params[$param] = $value->format( 'Y-m-d\TH:i:s\Z' );
			}
		}

		$elapsedTime = round(
			microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'],
			3
		);
		return array_merge( $params, [ 'elapsed_time' => $elapsedTime ] );
	}

	/**
	 * Parse a boolean value from the query string, treating 'false' and '0' as false.
	 * @param string $param
	 * @return bool
	 */
	public function getBoolVal( string $param ): bool {
		return isset( $this->params[$param] ) &&
			!in_array( $this->params[$param], [ 'false', '0' ] );
	}

	/**
	 * Used to standardized the format of API responses that contain revisions.
	 * Adds a 'full_page_title' key and value to each entry in $data.
	 * If there are as many entries in $data as there are $this->limit, pagination is assumed
	 *   and a 'continue' key is added to the end of the response body.
	 * @param string $key Key accessing the list of revisions in $data.
	 * @param array $out Whatever data needs to appear above the $data in the response body.
	 * @param array $data The data set itself.
	 * @return array
	 */
	public function addFullPageTitlesAndContinue( string $key, array $out, array $data ): array {
		// Add full_page_title (in addition to the existing page_title and namespace keys).
		$out[$key] = array_map( function ( $rev ) {
			return array_merge( [
				'full_page_title' => $this->getPageFromNsAndTitle(
					(int)$rev['namespace'],
					$rev['page_title'],
					true
				),
			], $rev );
		}, $data );

		// Check if pagination is needed.
		if ( count( $out[$key] ) === $this->limit && count( $out[$key] ) > 0 ) {
			// Use the timestamp of the last Edit as the value for the 'continue' return key,
			//   which can be used as a value for 'offset' in order to paginate results.
			$timestamp = array_slice( $out[$key], -1, 1 )[0]['timestamp'];
			$out['continue'] = ( new DateTime( $timestamp ) )->format( 'Y-m-d\TH:i:s\Z' );
		}

		return $out;
	}

	/*********
	 * OTHER *
	 */

	/**
	 * Record usage of an API endpoint.
	 * @param string $endpoint
	 * @codeCoverageIgnore
	 */
	public function recordApiUsage( string $endpoint ): void {
		/** @var Connection $conn */
		$conn = $this->managerRegistry->getConnection( 'default' );
		$date = date( 'Y-m-d' );

		// Increment count in timeline
		try {
			$sql = "INSERT INTO usage_api_timeline
                    VALUES(NULL, :date, :endpoint, 1)
                    ON DUPLICATE KEY UPDATE `count` = `count` + 1";
			$conn->executeStatement( $sql, [
				'date' => $date,
				'endpoint' => $endpoint,
			] );
		} catch ( Exception $e ) {
			// Do nothing. API response should still be returned rather than erroring out.
		}
	}

	/**
	 * Get the FlashBag instance from the current session, if available.
	 * @return ?FlashBagInterface
	 */
	public function getFlashBag(): ?FlashBagInterface {
		if ( $this->request->getSession() instanceof FlashBagAwareSessionInterface ) {
			return $this->request->getSession()->getFlashBag();
		}
		return null;
	}

	/**
	 * Add a flash message.
	 * @param string $type
	 * @param string|Markup $key i18n key or raw message.
	 * @param array $vars
	 */
	public function addFlashMessage( string $type, string|Markup $key, array $vars = [] ): void {
		if ( $key instanceof Markup || !$this->i18n->msgExists( $key, $vars ) ) {
			$msg = $key;
		} else {
			$msg = $this->i18n->msg( $key, $vars );
		}
		$this->addFlash( $type, $msg );
	}
}
