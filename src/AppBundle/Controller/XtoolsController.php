<?php
/**
 * This file contains the abstract XtoolsController,
 * which all other controllers will extend.
 */

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Xtools\ProjectRepository;
use Xtools\UserRepository;
use Xtools\Project;
use Xtools\Page;
use Xtools\PageRepository;

/**
 * XtoolsController supplies a variety of methods around parsing and validing
 * parameters, and initializing Project/User instances. These are used in
 * other controllers in the AppBundle\Controller namespace.
 * @abstract
 */
abstract class XtoolsController extends Controller
{
    /**
     * Given the request object, parse out common parameters. These include the
     * 'project', 'username', 'namespace' and 'article', along with their legacy
     * counterparts (e.g. 'lang' and 'wiki').
     * @param  Request $request
     * @return string[] Normalized parameters (no legacy params).
     */
    public function parseQueryParams(Request $request)
    {
        /** @var string[] Each parameter and value that was detected. */
        $params = $this->getParams($request);

        // Covert any legacy parameters, if present.
        $params = $this->convertLegacyParams($params);

        // Remove blank values.
        return array_filter($params, function ($param) {
            // 'namespace' or 'username' could be '0'.
            return $param !== null && $param !== '';
        });
    }

    /**
     * Get a Project instance from the project string, using defaults if the
     * given project string is invalid.
     * @param  string[] $params Query params.
     * @return Project
     */
    public function getProjectFromQuery($params)
    {
        // Set default project so we can populate the namespace selector
        // on index pages.
        if (empty($params['project'])) {
            $project = $this->container->getParameter('default_project');
        } else {
            $project = $params['project'];
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
     * If the project and username in the given params hash are valid, Project and User instances
     * are returned. User validation only occurs if 'username' is in the params.
     * Otherwise a redirect is returned that goes back to the index page.
     * @param Request $request The HTTP request.
     * @param string $tooHighEditCountAction If the requested user has more than the configured
     *   max edit count, they will be redirect to this route, passing in available params.
     * @return RedirectResponse|array Array contains [Project|null, User|null]
     */
    public function validateProjectAndUser(Request $request, $tooHighEditCountAction = null)
    {
        $params = $this->getParams($request);

        $projectData = $this->validateProject($params);
        if ($projectData instanceof RedirectResponse) {
            return $projectData;
        }

        $userData = null;

        if (isset($params['username'])) {
            $userData = $this->validateUser($params, $projectData, $tooHighEditCountAction);
            if ($userData instanceof RedirectResponse) {
                return $userData;
            }
        }

        return [$projectData, $userData];
    }

    /**
     * Validate the given project, returning a Project if it is valid or false otherwise.
     * @param string|string[] $params Project domain or database name, or params hash as
     *   retrieved by self::getParams().
     * @return Project|false
     */
    public function validateProject($params)
    {
        if (is_string($params)) {
            $params = ['project' => $params];
        }

        $projectData = ProjectRepository::getProject($params['project'], $this->container);

        if (!$projectData->exists()) {
            $this->addFlash('danger', ['invalid-project', $params['project']]);
            unset($params['project']); // Remove invalid parameter.
            return $this->redirectToRoute($this->getToolShortname(), $params);
        }

        return $projectData;
    }

    /**
     * Validate the given user, returning a User or Redirect if they don't exist.
     * @param string|string[] $params Username or params hash as retrieved by self::getParams().
     * @param Project $project Project to get check against.
     * @param string $tooHighEditCountAction If the requested user has more than the configured
     *   max edit count, they will be redirect to this route, passing in available params.
     * @return RedirectResponse|User
     */
    public function validateUser($params, Project $project, $tooHighEditCountAction = null)
    {
        if (is_string($params)) {
            $params = ['username' => $params];
        }

        $userData = UserRepository::getUser($params['username'], $this->container);

        // Don't continue if the user doesn't exist.
        if (!$userData->existsOnProject($project)) {
            $this->addFlash('danger', 'user-not-found');
            unset($params['username']);
            return $this->redirectToRoute($this->getToolShortname(), $params);
        }

        // Reject users with a crazy high edit count.
        if ($tooHighEditCountAction && $userData->hasTooManyEdits($project)) {
            $this->addFlash('danger', ['too-many-edits', number_format($userData->maxEdits())]);

            // If redirecting to a different controller, show an informative message accordingly.
            if ($tooHighEditCountAction !== $this->getToolShortname()) {
                // FIXME: This is currently only done for Edit Counter, redirecting to Simple Edit Counter,
                // so this bit is hardcoded. We need to instead give the i18n key of the route.
                $this->addFlash('info', ['too-many-edits-redir', 'Simple Counter']);
            } else {
                // Redirecting back to index, so remove username (otherwise we'd get a redirect loop).
                unset($params['username']);
            }

            return $this->redirectToRoute($tooHighEditCountAction, $params);
        }

        return $userData;
    }

    /**
     * Get a Page instance from the given page title, and validate that it exists.
     * @param  Project $project
     * @param  string $pageTitle
     * @return Page|RedirectResponse Page or redirect back to index if page doesn't exist.
     */
    public function getAndValidatePage($project, $pageTitle)
    {
        $page = new Page($project, $pageTitle);
        $pageRepo = new PageRepository();
        $pageRepo->setContainer($this->container);
        $page->setRepository($pageRepo);

        if (!$page->exists()) {
            // Redirect if the page doesn't exist.
            $this->addFlash('notice', ['no-result', $pageTitle]);
            return $this->redirectToRoute($this->getToolShortname());
        }

        return $page;
    }

    /**
     * Get all standardized parameters from the Request, either via URL query string or routing.
     * @param Request $request
     * @return string[]
     */
    public function getParams(Request $request)
    {
        $paramsToCheck = [
            'project',
            'username',
            'namespace',
            'article',
            'redirects',
            'deleted',
            'start',
            'end',
            'offset',
            'format',

            // Legacy parameters.
            'user',
            'name',
            'page',
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
            $value = $request->query->get($param) ?: $request->get($param);

            // Only store if value is given ('namespace' or 'username' could be '0').
            if ($value !== null && $value !== '') {
                $params[$param] = rawurldecode($value);
            }
        }

        return $params;
    }

    /**
     * Get UTC timestamps from given start and end string parameters.
     * This also makes $start on month before $end if not present,
     * and makes $end the current time if not present.
     * @param  string $start
     * @param  string $end
     * @param  bool   $useDefaults Whether to use defaults if the values
     *   are blank. The start date is set to one month before the end date,
     *   and the end date is set to the present.
     * @return mixed[] Start and end date as UTC timestamps or 'false' if empty.
     */
    public function getUTCFromDateParams($start, $end, $useDefaults = true)
    {
        $start = strtotime($start);
        $end = strtotime($end);

        // Use current time if end is not present (and is required),
        // or if it exceeds the current time.
        if (($useDefaults && $end === false) || $end > time()) {
            $end = time();
        }

        // Default to one month before end time if start is not present,
        // as is not optional.
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
     * @param  string[] $params
     * @return string[]
     */
    private function convertLegacyParams($params)
    {
        $paramMap = [
            'user' => 'username',
            'name' => 'username',
            'page' => 'article',
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
     * @param  Request $request
     * @param  string  $templatePath Path to template without format,
     *   such as '/editCounter/latest_global'.
     * @param  array   $ret Data that should be passed to the view.
     * @return array
     * @codeCoverageIgnore
     */
    public function getFormattedReponse(Request $request, $templatePath, $ret)
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
}
