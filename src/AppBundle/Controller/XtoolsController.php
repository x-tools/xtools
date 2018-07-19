<?php
/**
 * This file contains the abstract XtoolsController, which all other controllers will extend.
 */

namespace AppBundle\Controller;

use AppBundle\Exception\XtoolsHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Xtools\ProjectRepository;
use Xtools\UserRepository;
use Xtools\Project;
use Xtools\Page;
use Xtools\PageRepository;
use Xtools\User;

/**
 * XtoolsController supplies a variety of methods around parsing and validating parameters, and initializing
 * Project/User instances. These are used in other controllers in the AppBundle\Controller namespace.
 * @abstract
 */
abstract class XtoolsController extends Controller
{
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

    /** @var int|string Namespace parsed from the Request, ID as int or 'all' for all namespaces. */
    protected $namespace;

    /** @var int Pagination offset parsed from the Request. */
    protected $offset = 0;

    /** @var int Number of results to return. */
    protected $limit;

    /** @var bool Is the current request a subrequest? */
    protected $isSubRequest;

    /**
     * @var string This activates the 'too high edit count' functionality. This property represents the
     *   action that should be redirected to if the user has too high of an edit count.
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
    abstract protected function getIndexRoute();

    /**
     * XtoolsController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container)
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->container = $container;
        $this->params = $this->parseQueryParams();

        $pattern = "#::([a-zA-Z]*)Action#";
        $matches = [];
        preg_match($pattern, $this->request->get('_controller'), $matches);
        // The blank string here only happens in the unit tests, where the request may not be made to an action.
        $this->controllerAction = isset($matches[1]) ? $matches[1] : '';

        // Whether the action is an API action.
        $this->isApi = substr($this->controllerAction, -3) === 'Api';

        // Whether we're making a subrequest (the view makes a request to another action).
        $this->isSubRequest = $this->request->get('htmlonly')
            || $this->get('request_stack')->getParentRequest() !== null;

        if ($this->controllerAction === 'index') {
            $this->project = $this->getProjectFromQuery();
        } else {
            $this->setProperties();
        }
    }

    /**
     * Normalize all common parameters used by the controllers and set class properties.
     */
    private function setProperties()
    {
        // No normalization needed for these params.
        foreach (['namespace', 'offset', 'limit'] as $param) {
            if (isset($this->params[$param])) {
                $this->{$param} = $this->params[$param];
            }
        }

        if (isset($this->params['project'])) {
            $this->project = $this->validateProject($this->params['project']);
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
    private function setDates()
    {
        $start = isset($this->params['start']) ? $this->params['start'] : false;
        $end = isset($this->params['end']) ? $this->params['end'] : false;
        if ($start || $end) {
            list($this->start, $this->end) = $this->getUTCFromDateParams($start, $end);
        }
    }

    /**
     * Construct a fully qualified page title given the namespace and title.
     * @param int $ns Namespace ID.
     * @param string $title Page title.
     * @param bool $rawTitle Return only the title (and not a Page).
     * @return Page|string
     */
    protected function getPageFromNsAndTitle($ns, $title, $rawTitle = false)
    {
        if ((int)$ns === 0) {
            return $rawTitle ? $title : $this->validatePage($title);
        }

        // Prepend namespace and strip out duplicates.
        $nsName = $this->project->getNamespaces()[$ns];
        $title = $nsName.':'.ltrim($title, $nsName.':');
        return $rawTitle ? $title : $this->validatePage($title);
    }

    /**
     * Validate the given project, returning a Project if it is valid or false otherwise.
     * @param string $projectQuery Project domain or database name.
     * @return Project
     * @throws XtoolsHttpException
     */
    public function validateProject($projectQuery)
    {
        /** @var Project $project */
        $project = ProjectRepository::getProject($projectQuery, $this->container);

        if ($project->exists()) {
            return $project;
        }

        $this->throwXtoolsException(
            $this->getIndexRoute(),
            'Invalid project',
            ['invalid-project', $this->params['project']],
            'project'
        );
    }

    /**
     * Parse out common parameters from the request. These include the
     * 'project', 'username', 'namespace' and 'page', along with their legacy
     * counterparts (e.g. 'lang' and 'wiki').
     * @return string[] Normalized parameters (no legacy params).
     */
    public function parseQueryParams()
    {
        /** @var string[] Each parameter and value that was detected. */
        $params = $this->getParams();

        // Covert any legacy parameters, if present.
        $params = $this->convertLegacyParams($params);

        // Remove blank values.
        return array_filter($params, function ($param) {
            // 'namespace' or 'username' could be '0'.
            return $param !== null && $param !== '';
        });
    }

    /**
     * Get a Project instance from the project string, using defaults if the given project string is invalid.
     * @return Project
     */
    public function getProjectFromQuery()
    {
        // Set default project so we can populate the namespace selector
        // on index pages.
        if (empty($this->params['project'])) {
            $project = $this->container->getParameter('default_project');
        } else {
            $project = $this->params['project'];
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

    /**
     * Validate the given user, returning a User or Redirect if they don't exist.
     * @param string $username
     * @return User
     * @throws XtoolsHttpException
     */
    public function validateUser($username)
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
            $this->throwXtoolsException($this->getIndexRoute(), 'User not found', 'user-not-found', 'username');
        }

        // Reject users with a crazy high edit count.
        if (isset($this->tooHighEditCountAction) &&
            !in_array($this->controllerAction, $this->tooHighEditCountActionBlacklist) &&
            $user->hasTooManyEdits($this->project)
        ) {
            /** TODO: Somehow get this to use self::throwXtoolsException */

            // FIXME: i18n!!
            $this->addFlash('danger', ['too-many-edits', number_format($user->maxEdits())]);

            // If redirecting to a different controller, show an informative message accordingly.
            if ($this->tooHighEditCountAction !== $this->getIndexRoute()) {
                // FIXME: This is currently only done for Edit Counter, redirecting to Simple Edit Counter,
                // so this bit is hardcoded. We need to instead give the i18n key of the route.
                $this->addFlash('info', ['too-many-edits-redir', 'Simple Counter']);
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
    public function validatePage($pageTitle)
    {
        $page = new Page($this->project, $pageTitle);
        $pageRepo = new PageRepository();
        $pageRepo->setContainer($this->container);
        $page->setRepository($pageRepo);

        if ($page->exists()) { // Page is valid.
            return $page;
        }

        $this->throwXtoolsException(
            $this->getIndexRoute(),
            'Page not found',
            isset($this->params['page']) ? ['no-result', $this->params['page']] : null,
            'page'
        );
    }

    /**
     * Throw an XtoolsHttpException, which the given error message and redirects to specified action.
     * @param string $redirectAction Name of action to redirect to.
     * @param string $message Shown in API responses (TODO: this should be i18n'd too?)
     * @param array|string|null $flashParams
     * @param string $invalidParam This will be removed from $this->params. Omit if you don't want this to happen.
     * @throws XtoolsHttpException
     */
    public function throwXtoolsException($redirectAction, $message, $flashParams = null, $invalidParam = null)
    {
        if (null !== $flashParams) {
            $this->addFlash('danger', $flashParams);
        }
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
            $message,
            $this->generateUrl($redirectAction, $this->params),
            $originalParams,
            $this->isApi
        );
    }

    /**
     * Get the first error message stored in the session's FlashBag.
     * @return string
     */
    public function getFlashMessage()
    {
        $key = $this->get('session')->getFlashBag()->get('danger')[0];
        $param = null;

        if (is_array($key)) {
            list($key, $param) = $key;
        }

        return $this->render('message.twig', [
            'key' => $key,
            'params' => [$param]
        ])->getContent();
    }

    /**
     * Get all standardized parameters from the Request, either via URL query string or routing.
     * @return string[]
     */
    public function getParams()
    {
        $paramsToCheck = [
            'project',
            'username',
            'namespace',
            'page',
            'categories',
            'redirects',
            'deleted',
            'start',
            'end',
            'offset',
            'limit',
            'format',

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

        /** @var string[] Each parameter that was detected along with its value. */
        $params = [];

        foreach ($paramsToCheck as $param) {
            // Pull in either from URL query string or route.
            $value = $this->request->query->get($param) ?: $this->request->get($param);

            // Only store if value is given ('namespace' or 'username' could be '0').
            if ($value !== null && $value !== '') {
                $params[$param] = rawurldecode($value);
            }
        }

        return $params;
    }

    /**
     * Get UTC timestamps from given start and end string parameters. This also makes $start on month before
     * $end if not present, and makes $end the current time if not present.
     * @param int|string $start Unix timestamp or string accepted by strtotime.
     * @param int|string $end Unix timestamp or string accepted by strtotime.
     * @param bool $useDefaults Whether to use defaults if the values are blank. The start date is set to one month
     *   before the end date, and the end date is set to the present.
     * @return mixed[] Start and end date as UTC timestamps or 'false' if empty.
     */
    public function getUTCFromDateParams($start, $end, $useDefaults = false)
    {
        $start = is_int($start) ? $start : strtotime($start);
        $end = is_int($end) ? $end : strtotime($end);

        // Use current time if end is not present (and is required), or if it exceeds the current time.
        if (($useDefaults && $end === false) || $end > time()) {
            $end = time();
        }

        // Default to one month before end time if start is not present, as is not optional.
        if ($useDefaults && $start === false) {
            $start = strtotime('-1 month', $end);
        }

        // Reverse if start date is after end date.
        if ($start > $end && $start !== false && $end !== false) {
            $newEnd = $start;
            $start = $end;
            $end = $newEnd;
        }

        return [$start, $end];
    }

    /**
     * Given the params hash, normalize any legacy parameters to thier modern equivalent.
     * @param string[] $params
     * @return string[]
     */
    private function convertLegacyParams($params)
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

            /** @var string[] Projects for which there is no specific language association. */
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

    /**
     * Record usage of an API endpoint.
     * @param string $endpoint
     * @codeCoverageIgnore
     */
    public function recordApiUsage($endpoint)
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

        if (count($conn->query($existsSql)->fetchAll()) === 0) {
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
     * Get the rendered template for the requested format.
     * @param Request $request
     * @param string $templatePath Path to template without format,
     *   such as '/editCounter/latest_global'.
     * @param array $ret Data that should be passed to the view.
     * @return \Symfony\Component\HttpFoundation\Response
     * @codeCoverageIgnore
     */
    public function getFormattedResponse(Request $request, $templatePath, $ret)
    {
        $format = $request->query->get('format', 'html');
        if ($format == '') {
            // The default above doesn't work when the 'format' parameter is blank.
            $format = 'html';
        }

        $formatMap = [
            'wikitext' => 'text/plain',
            'csv' => 'text/csv',
            'tsv' => 'text/tab-separated-values',
            'json' => 'application/json',
        ];

        $response = $this->render("$templatePath.$format.twig", $ret);

        $contentType = isset($formatMap[$format]) ? $formatMap[$format] : 'text/html';
        $response->headers->set('Content-Type', $contentType);

        return $response;
    }

    /**
     * Return a JsonResponse object pre-supplied with the requested params.
     * @param $data
     * @return JsonResponse
     */
    public function getFormattedApiResponse($data)
    {
        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);
        $response->setStatusCode(Response::HTTP_OK);

        $response->setData(array_merge($this->params, [
            // In some controllers, $this->params['project'] may be overriden with a Project object.
            'project' => $this->project->getDomain(),
        ], $data));

        return $response;
    }
}
