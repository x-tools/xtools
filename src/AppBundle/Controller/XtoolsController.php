<?php
/**
 * This file contains the abstract XtoolsController, which all other controllers will extend.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Exception\XtoolsHttpException;
use AppBundle\Helper\I18nHelper;
use AppBundle\Model\Page;
use AppBundle\Model\Project;
use AppBundle\Model\User;
use AppBundle\Repository\PageRepository;
use AppBundle\Repository\ProjectRepository;
use AppBundle\Repository\UserRepository;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * XtoolsController supplies a variety of methods around parsing and validating parameters, and initializing
 * Project/User instances. These are used in other controllers in the AppBundle\Controller namespace.
 * @abstract
 */
abstract class XtoolsController extends Controller
{
    /** @var I18nHelper i18n helper. */
    protected $i18n;

    /** @var Request The request object. */
    protected $request;

    /** @var string Name of the action within the child controller that is being executed. */
    protected $controllerAction;

    /** @var array Hash of params parsed from the Request. */
    protected $params;

    /** @var bool Whether this is a request to an API action. */
    protected $isApi;

    /** @var Project Relevant Project parsed from the Request. */
    protected $project;

    /** @var User Relevant User parsed from the Request. */
    protected $user;

    /** @var Page Relevant Page parsed from the Request. */
    protected $page;

    /** @var int|false Start date parsed from the Request. */
    protected $start = false;

    /** @var int|false End date parsed from the Request. */
    protected $end = false;

    /**
     * Default days from current day, to use as the start date if none was provided.
     * If this is null and $maxDays is non-null, the latter will be used as the default.
     * Is public visibility evil here? I don't think so.
     * @var int|null
     */
    public $defaultDays = null;

    /**
     * Maximum number of days allowed for the given date range.
     * Set this in the controller's constructor to enforce the given date range to be within this range.
     * This will be used as the default date span unless $defaultDays is defined.
     * @see XtoolsController::getUTCFromDateParams()
     * @var int|null
     */
    public $maxDays = null;

    /** @var int|string Namespace parsed from the Request, ID as int or 'all' for all namespaces. */
    protected $namespace;

    /** @var int Pagination offset parsed from the Request. */
    protected $offset = 0;

    /** @var int Number of results to return. */
    protected $limit;

    /** @var bool Is the current request a subrequest? */
    protected $isSubRequest;

    /**
     * Stores user preferences such default project.
     * This may get altered from the Request and updated in the Response.
     * @var array
     */
    protected $cookies = [
        'XtoolsProject' => null,
    ];

    /**
     * This activates the 'too high edit count' functionality. This property represents the
     * action that should be redirected to if the user has too high of an edit count.
     * @var string
     */
    protected $tooHighEditCountAction;

    /**
     * @var array Actions that are exempt from edit count limitations.
     */
    protected $tooHighEditCountActionBlacklist = [];

    /**
     * Require the tool's index route (initial form) be defined here. This should also
     * be the name of the associated model, if present.
     * @return string
     */
    abstract protected function getIndexRoute(): string;

    /**
     * XtoolsController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     * @param I18nHelper $i18n
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container, I18nHelper $i18n)
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->container = $container;
        $this->i18n = $i18n;
        $this->params = $this->parseQueryParams();

        // Parse out the name of the controller and action.
        $pattern = "#::([a-zA-Z]*)Action#";
        $matches = [];
        // The blank string here only happens in the unit tests, where the request may not be made to an action.
        preg_match($pattern, $this->request->get('_controller') ?? '', $matches);
        $this->controllerAction = $matches[1] ?? '';

        // Whether the action is an API action.
        $this->isApi = 'Api' === substr($this->controllerAction, -3) || 'recordUsage' === $this->controllerAction;

        // Whether we're making a subrequest (the view makes a request to another action).
        $this->isSubRequest = $this->request->get('htmlonly')
            || null !== $this->get('request_stack')->getParentRequest();

        // Disallow AJAX (unless it's an API or subrequest).
        $this->checkIfAjax();

        // Load user options from cookies.
        $this->loadCookies();

        // Set the class-level properties based on params.
        if (false !== strpos(strtolower($this->controllerAction), 'index')) {
            // Index pages should only set the project, and no other class properties.
            $this->setProject($this->getProjectFromQuery());
        } else {
            $this->setProperties(); // Includes the project.
        }
    }

    /**
     * Check if the request is AJAX, and disallow it unless they're using the API or if it's a subrequest.
     */
    private function checkIfAjax(): void
    {
        if ($this->request->isXmlHttpRequest() && !$this->isApi && !$this->isSubRequest) {
            throw new HttpException(
                403,
                $this->i18n->msg('error-automation', ['https://xtools.readthedocs.io/en/stable/api/'])
            );
        }
    }

    /***********
     * COOKIES *
     ***********/

    /**
     * Load user preferences from the associated cookies.
     */
    private function loadCookies(): void
    {
        // Not done for subrequests.
        if ($this->isSubRequest) {
            return;
        }

        foreach (array_keys($this->cookies) as $name) {
            $this->cookies[$name] = $this->request->cookies->get($name);
        }
    }

    /**
     * Set cookies on the given Response.
     * @param Response $response
     */
    private function setCookies(Response &$response): void
    {
        // Not done for subrequests.
        if ($this->isSubRequest) {
            return;
        }

        foreach ($this->cookies as $name => $value) {
            $response->headers->setCookie(
                new Cookie($name, $value)
            );
        }
    }

    /**
     * Sets the project, with the domain in $this->cookies['XtoolsProject'] that will
     * later get set on the Response headers in self::getFormattedResponse().
     * @param Project $project
     */
    private function setProject(Project $project): void
    {
        $this->project = $project;
        $this->cookies['XtoolsProject'] = $project->getDomain();
    }

    /****************************
     * SETTING CLASS PROPERTIES *
     ****************************/

    /**
     * Normalize all common parameters used by the controllers and set class properties.
     */
    private function setProperties(): void
    {
        $this->namespace = $this->params['namespace'] ?? null;

        // Offset and limit need to be ints.
        foreach (['offset', 'limit'] as $param) {
            if (isset($this->params[$param])) {
                $this->{$param} = (int)$this->params[$param];
            }
        }

        if (isset($this->params['project'])) {
            $this->setProject($this->validateProject($this->params['project']));
        } elseif (null !== $this->cookies['XtoolsProject']) {
            // Set from cookie.
            $this->setProject(
                $this->validateProject($this->cookies['XtoolsProject'])
            );
        }

        if (isset($this->params['username'])) {
            $this->user = $this->validateUser($this->params['username']);
        }
        if (isset($this->params['page'])) {
            $this->page = $this->getPageFromNsAndTitle($this->namespace, $this->params['page']);
        }

        $this->setDates();
    }

    /**
     * Set class properties for dates, if such params were passed in.
     */
    private function setDates(): void
    {
        $start = $this->params['start'] ?? false;
        $end = $this->params['end'] ?? false;
        if ($start || $end || null !== $this->maxDays) {
            [$this->start, $this->end] = $this->getUTCFromDateParams($start, $end);

            // Set $this->params accordingly too, so that for instance API responses will include it.
            $this->params['start'] = is_int($this->start) ? date('Y-m-d', $this->start) : false;
            $this->params['end'] = is_int($this->end) ? date('Y-m-d', $this->end) : false;
        }
    }

    /**
     * Construct a fully qualified page title given the namespace and title.
     * @param int|string $ns Namespace ID.
     * @param string $title Page title.
     * @param bool $rawTitle Return only the title (and not a Page).
     * @return Page|string
     */
    protected function getPageFromNsAndTitle($ns, string $title, bool $rawTitle = false)
    {
        if (0 === (int)$ns) {
            return $rawTitle ? $title : $this->validatePage($title);
        }

        // Prepend namespace and strip out duplicates.
        $nsName = $this->project->getNamespaces()[$ns] ?? $this->i18n->msg('unknown');
        $title = $nsName.':'.preg_replace('/^'.$nsName.':/', '', $title);
        return $rawTitle ? $title : $this->validatePage($title);
    }

    /**
     * Get a Project instance from the project string, using defaults if the given project string is invalid.
     * @return Project
     */
    public function getProjectFromQuery(): Project
    {
        // Set default project so we can populate the namespace selector on index pages.
        // Defaults to project stored in cookie, otherwise project specified in parameters.yml.
        if (isset($this->params['project'])) {
            $project = $this->params['project'];
        } elseif (null !== $this->cookies['XtoolsProject']) {
            $project = $this->cookies['XtoolsProject'];
        } else {
            $project = $this->container->getParameter('default_project');
        }

        $projectData = ProjectRepository::getProject($project, $this->container);

        // Revert back to defaults if we've established the given project was invalid.
        if (!$projectData->exists()) {
            $projectData = ProjectRepository::getProject(
                $this->container->getParameter('default_project'),
                $this->container
            );
        }

        return $projectData;
    }

    /*************************
     * GETTERS / VALIDATIONS *
     *************************/

    /**
     * Validate the given project, returning a Project if it is valid or false otherwise.
     * @param string $projectQuery Project domain or database name.
     * @return Project
     * @throws XtoolsHttpException
     */
    public function validateProject(string $projectQuery): Project
    {
        /** @var Project $project */
        $project = ProjectRepository::getProject($projectQuery, $this->container);

        if (!$project->exists()) {
            $this->throwXtoolsException(
                $this->getIndexRoute(),
                'invalid-project',
                [$this->params['project']],
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
    public function validateUser(string $username): User
    {
        $user = UserRepository::getUser($username, $this->container);

        // Allow querying for any IP, currently with no edit count limitation...
        // Once T188677 is resolved IPs will be affected by the EXPLAIN results.
        if ($user->isAnon()) {
            return $user;
        }

        $originalParams = $this->params;

        // Don't continue if the user doesn't exist.
        if (!$user->existsOnProject($this->project)) {
            $this->throwXtoolsException($this->getIndexRoute(), 'user-not-found', [], 'username');
        }

        // Reject users with a crazy high edit count.
        if (isset($this->tooHighEditCountAction) &&
            !in_array($this->controllerAction, $this->tooHighEditCountActionBlacklist) &&
            $user->hasTooManyEdits($this->project)
        ) {
            /** TODO: Somehow get this to use self::throwXtoolsException */

            $this->addFlashMessage('danger', 'too-many-edits', [
                $this->i18n->numberFormat($user->maxEdits()),
            ]);

            // If redirecting to a different controller, show an informative message accordingly.
            if ($this->tooHighEditCountAction !== $this->getIndexRoute()) {
                // FIXME: This is currently only done for Edit Counter, redirecting to Simple Edit Counter,
                // so this bit is hardcoded. We need to instead give the i18n key of the route.
                $this->addFlashMessage('info', 'too-many-edits-redir', [
                    $this->i18n->msg('tool-simpleeditcounter'),
                ]);
            } else {
                // Redirecting back to index, so remove username (otherwise we'd get a redirect loop).
                unset($this->params['username']);
            }

            throw new XtoolsHttpException(
                'User has made too many edits! Maximum '.$user->maxEdits(),
                $this->generateUrl($this->tooHighEditCountAction, $this->params),
                $originalParams,
                $this->isApi
            );
        }

        return $user;
    }

    /**
     * Get a Page instance from the given page title, and validate that it exists.
     * @param string $pageTitle
     * @return Page
     * @throws XtoolsHttpException
     */
    public function validatePage(string $pageTitle): Page
    {
        $page = new Page($this->project, $pageTitle);
        $pageRepo = new PageRepository();
        $pageRepo->setContainer($this->container);
        $page->setRepository($pageRepo);

        if (!$page->exists()) {
            $this->throwXtoolsException(
                $this->getIndexRoute(),
                'no-result',
                [$this->params['page'] ?? null],
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
     * @param string $invalidParam This will be removed from $this->params. Omit if you don't want this to happen.
     * @throws XtoolsHttpException
     */
    public function throwXtoolsException(
        string $redirectAction,
        string $message,
        array $messageParams = [],
        ?string $invalidParam = null
    ): void {
        $this->addFlashMessage('danger', $message, $messageParams);
        $originalParams = $this->params;

        // Remove invalid parameter if it was given.
        if (is_string($invalidParam)) {
            unset($this->params[$invalidParam]);
        }

        // We sometimes are redirecting to the index page, so also remove project (otherwise we'd get a redirect loop).
        /**
         * FIXME: Index pages should have a 'nosubmit' parameter to prevent submission.
         * Then we don't even need to remove $invalidParam.
         * Better, we should show the error on the results page, with no results.
         */
        unset($this->params['project']);

        // Throw exception which will redirect to $redirectAction.
        throw new XtoolsHttpException(
            $this->i18n->msgIfExists($message, $messageParams),
            $this->generateUrl($redirectAction, $this->params),
            $originalParams,
            $this->isApi
        );
    }

    /**
     * Get the first error message stored in the session's FlashBag.
     * @return string
     */
    public function getFlashMessage(): string
    {
        $key = $this->get('session')->getFlashBag()->get('danger')[0];
        $param = null;

        if (is_array($key)) {
            [$key, $param] = $key;
        }

        return $this->render('message.twig', [
            'key' => $key,
            'params' => [$param],
        ])->getContent();
    }

    /******************
     * PARSING PARAMS *
     ******************/

    /**
     * Get all standardized parameters from the Request, either via URL query string or routing.
     * @return string[]
     */
    public function getParams(): array
    {
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

        foreach ($paramsToCheck as $param) {
            // Pull in either from URL query string or route.
            $value = $this->request->query->get($param) ?: $this->request->get($param);

            // Only store if value is given ('namespace' or 'username' could be '0').
            if (null !== $value && '' !== $value) {
                $params[$param] = rawurldecode((string)$value);
            }
        }

        return $params;
    }

    /**
     * Parse out common parameters from the request. These include the 'project', 'username', 'namespace' and 'page',
     * along with their legacy counterparts (e.g. 'lang' and 'wiki').
     * @return string[] Normalized parameters (no legacy params).
     */
    public function parseQueryParams(): array
    {
        /** @var string[] $params Each parameter and value that was detected. */
        $params = $this->getParams();

        // Covert any legacy parameters, if present.
        $params = $this->convertLegacyParams($params);

        // Remove blank values.
        return array_filter($params, function ($param) {
            // 'namespace' or 'username' could be '0'.
            return null !== $param && '' !== $param;
        });
    }

    /**
     * Get UTC timestamps from given start and end string parameters. This also makes $start $maxDays before
     * $end if not present, and makes $end the current time if not present.
     * The date range will not exceed $this->maxDays days, if this public class property is set.
     * @param int|string|false $start Unix timestamp or string accepted by strtotime.
     * @param int|string|false $end Unix timestamp or string accepted by strtotime.
     * @return int[] Start and end date as UTC timestamps.
     */
    public function getUTCFromDateParams($start, $end): array
    {
        $today = strtotime('today midnight');

        // start time should not be in the future.
        $startTime = min(
            is_int($start) ? $start : strtotime((string)$start),
            $today
        );

        // end time defaults to now, and will not be in the future.
        $endTime = min(
            (is_int($end) ? $end : strtotime((string)$end)) ?: $today,
            $today
        );

        // Default to $this->defaultDays or $this->maxDays before end time if start is not present.
        $daysOffset = $this->defaultDays ?? $this->maxDays;
        if (false === $start && is_int($daysOffset)) {
            $startTime = strtotime("-$daysOffset days", $endTime);
        }

        // Default to $this->defaultDays or $this->maxDays after start time if end is not present.
        if (false === $end && is_int($daysOffset)) {
            $endTime = min(
                strtotime("+$daysOffset days", $startTime),
                $today
            );
        }

        // Reverse if start date is after end date.
        if ($startTime > $endTime && false !== $startTime && false !== $end) {
            $newEndTime = $startTime;
            $startTime = $endTime;
            $endTime = $newEndTime;
        }

        // Finally, don't let the date range exceed $this->maxDays.
        $startObj = DateTime::createFromFormat('U', (string)$startTime);
        $endObj = DateTime::createFromFormat('U', (string)$endTime);
        if (is_int($this->maxDays) && $startObj->diff($endObj)->days > $this->maxDays) {
            // Show warnings that the date range was truncated.
            $this->addFlashMessage('warning', 'date-range-too-wide', [$this->maxDays]);

            $startTime = strtotime("-$this->maxDays days", $endTime);
        }

        return [$startTime, $endTime];
    }

    /**
     * Given the params hash, normalize any legacy parameters to their modern equivalent.
     * @param string[] $params
     * @return string[]
     */
    private function convertLegacyParams(array $params): array
    {
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
        foreach ($paramMap as $legacy => $modern) {
            if (isset($params[$legacy])) {
                $params[$modern] = $params[$legacy];
                unset($params[$legacy]);
            }
        }

        // Separate parameters for language and wiki.
        if (isset($params['wiki']) && isset($params['lang'])) {
            // 'wikifam' will be like '.wikipedia.org', vs just 'wikipedia',
            // so we must remove leading periods and trailing .org's.
            $params['project'] = rtrim(ltrim($params['wiki'], '.'), '.org').'.org';

            /** @var string[] $languagelessProjects Projects for which there is no specific language association. */
            $languagelessProjects = $this->container->getParameter('languageless_wikis');

            // Prepend language if applicable.
            if (isset($params['lang']) && !in_array($params['wiki'], $languagelessProjects)) {
                $params['project'] = $params['lang'].'.'.$params['project'];
            }

            unset($params['wiki']);
            unset($params['lang']);
        }

        return $params;
    }

    /************************
     * FORMATTING RESPONSES *
     ************************/

    /**
     * Get the rendered template for the requested format. This method also updates the cookies.
     * @param string $templatePath Path to template without format,
     *   such as '/editCounter/latest_global'.
     * @param array $ret Data that should be passed to the view.
     * @return Response
     * @codeCoverageIgnore
     */
    public function getFormattedResponse(string $templatePath, array $ret): Response
    {
        $format = $this->request->query->get('format', 'html');
        if ('' == $format) {
            // The default above doesn't work when the 'format' parameter is blank.
            $format = 'html';
        }

        // Merge in common default parameters, giving $ret (from the caller) the priority.
        $ret = array_merge([
            'project' => $this->project,
            'user' => $this->user,
            'page' => $this->page,
        ], $ret);

        $formatMap = [
            'wikitext' => 'text/plain',
            'csv' => 'text/csv',
            'tsv' => 'text/tab-separated-values',
            'json' => 'application/json',
        ];

        $response = new Response();

        // Set cookies. Note this must be done before rendering the view, as the view may invoke subrequests.
        $this->setCookies($response);

        // If requested format does not exist, assume HTML.
        if (false === $this->get('twig')->getLoader()->exists("$templatePath.$format.twig")) {
            $format = 'html';
        }

        $response = $this->render("$templatePath.$format.twig", $ret, $response);

        $contentType = $formatMap[$format] ?? 'text/html';
        $response->headers->set('Content-Type', $contentType);

        return $response;
    }

    /**
     * Return a JsonResponse object pre-supplied with the requested params.
     * @param array $data
     * @return JsonResponse
     */
    public function getFormattedApiResponse(array $data): JsonResponse
    {
        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);
        $response->setStatusCode(Response::HTTP_OK);

        $elapsedTime = round(
            microtime(true) - $this->request->server->get('REQUEST_TIME_FLOAT'),
            3
        );

        // Any pipe-separated values should be returned as an array.
        foreach ($this->params as $param => $value) {
            if (is_string($value) && false !== strpos($value, '|')) {
                $this->params[$param] = explode('|', $value);
            }
        }

        $ret = array_merge($this->params, [
            // In some controllers, $this->params['project'] may be overridden with a Project object.
            'project' => $this->project->getDomain(),
        ], $data, ['elapsed_time' => $elapsedTime]);

        // Merge in flash messages, putting them at the top.
        $flashes = $this->get('session')->getFlashBag()->peekAll();
        $ret = array_merge($flashes, $ret);

        // Flashes now can be cleared after merging into the response.
        $this->get('session')->getFlashBag()->clear();

        $response->setData($ret);

        return $response;
    }

    /*********
     * OTHER *
     *********/

    /**
     * Record usage of an API endpoint.
     * @param string $endpoint
     * @codeCoverageIgnore
     */
    public function recordApiUsage(string $endpoint): void
    {
        /** @var \Doctrine\DBAL\Connection $conn */
        $conn = $this->container->get('doctrine')
            ->getManager('default')
            ->getConnection();
        $date =  date('Y-m-d');

        // Increment count in timeline
        $existsSql = "SELECT 1 FROM usage_api_timeline
                      WHERE date = '$date'
                      AND endpoint = '$endpoint'";

        if (0 === count($conn->query($existsSql)->fetchAll())) {
            $createSql = "INSERT INTO usage_api_timeline
                          VALUES(NULL, '$date', '$endpoint', 1)";
            $conn->query($createSql);
        } else {
            $updateSql = "UPDATE usage_api_timeline
                          SET count = count + 1
                          WHERE endpoint = '$endpoint'
                          AND date = '$date'";
            $conn->query($updateSql);
        }
    }

    /**
     * Add a flash message.
     * @param string $type
     * @param string $key
     * @param array $vars
     */
    public function addFlashMessage(string $type, string $key, array $vars = []): void
    {
        $this->addFlash(
            $type,
            $this->i18n->msg($key, $vars)
        );
    }
}
