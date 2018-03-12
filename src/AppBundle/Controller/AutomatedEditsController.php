<?php
/**
 * This file contains only the AutomatedEditsController class.
 */

namespace AppBundle\Controller;

use AppBundle\Helper\AutomatedEditsHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xtools\AutoEdits;
use Xtools\AutoEditsRepository;
use Xtools\Edit;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;

/**
 * This controller serves the AutomatedEdits tool.
 */
class AutomatedEditsController extends XtoolsController
{
    /** @var AutoEdits The AutoEdits instance. */
    protected $autoEdits;

    /** @var Project The project. */
    protected $project;

    /** @var User The user. */
    protected $user;

    /** @var string The start date. */
    protected $start;

    /** @var string The end date. */
    protected $end;

    /** @var int|string The namespace ID or 'all' for all namespaces. */
    protected $namespace;

    /** @var bool Whether or not there is a start date. */
    protected $hasStartDate;

    /** @var bool Whether or not this is a subrequest. */
    protected $isSubRequest;

    /** @var array Data that is passed to the view. */
    private $output;

    /**
     * Get the tool's shortname.
     * @return string
     * @codeCoverageIgnore
     */
    public function getToolShortname()
    {
        return 'autoedits';
    }

    /**
     * Display the search form.
     * @Route("/autoedits", name="autoedits")
     * @Route("/autoedits/", name="autoeditsSlash")
     * @Route("/automatededits", name="autoeditsLong")
     * @Route("/automatededits/", name="autoeditsLongSlash")
     * @Route("/autoedits/index.php", name="autoeditsIndexPhp")
     * @Route("/automatededits/index.php", name="autoeditsLongIndexPhp")
     * @Route("/autoedits/{project}", name="autoeditsProject")
     * @param Request $request The HTTP request.
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $params = $this->parseQueryParams($request);

        // Redirect if at minimum project and username are provided.
        if (isset($params['project']) && isset($params['username'])) {
            return $this->redirectToRoute('AutoEditsResult', $params);
        }

        // Convert the given project (or default project) into a Project instance.
        $params['project'] = $this->getProjectFromQuery($params);

        return $this->render('autoEdits/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-autoedits',
            'xtSubtitle' => 'tool-autoedits-desc',
            'xtPage' => 'autoedits',

            // Defaults that will get overriden if in $params.
            'namespace' => 0,
            'start' => '',
            'end' => '',
        ], $params));
    }

    /**
     * Set defaults, and instantiate the AutoEdits model. This is called at
     * the top of every view action.
     * @param Request $request The HTTP request.
     * @codeCoverageIgnore
     */
    private function setupAutoEdits(Request $request)
    {
        // Will redirect back to index if the user has too high of an edit count.
        $ret = $this->validateProjectAndUser($request, 'autoedits');
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($this->project, $this->user) = $ret;
        }

        $namespace = $request->get('namespace');
        $start = $request->get('start');
        $end = $request->get('end');
        $offset = $request->get('offset', 0);

        // 'false' means the dates are optional and returned as 'false' if empty.
        list($this->start, $this->end) = $this->getUTCFromDateParams($start, $end, false);

        // We'll want to conditionally show some things in the view if there is a start date.
        $this->hasStartDate = $this->start > 0;

        // Format dates as needed by User model, if the date is present.
        if ($this->start !== false) {
            $this->start = date('Y-m-d', $this->start);
        }
        if ($this->end !== false) {
            $this->end = date('Y-m-d', $this->end);
        }

        // Normalize default namespace.
        if ($namespace == '') {
            $this->namespace = 0;
        } else {
            $this->namespace = $namespace;
        }

        // Check query param for the tool name.
        $tool = $request->query->get('tool', null);

        $this->autoEdits = new AutoEdits(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end,
            $tool,
            $offset
        );
        $autoEditsRepo = new AutoEditsRepository();
        $autoEditsRepo->setContainer($this->container);
        $this->autoEdits->setRepository($autoEditsRepo);

        $this->isSubRequest = $request->get('htmlonly')
            || $this->get('request_stack')->getParentRequest() !== null;

        $this->output = [
            'xtPage' => 'autoedits',
            'xtTitle' => $this->user->getUsername(),
            'project' => $this->project,
            'user' => $this->user,
            'ae' => $this->autoEdits,
            'hasStartDate' => $this->hasStartDate,
            'is_sub_request' => $this->isSubRequest,
        ];
    }

    /**
     * Display the results.
     * @Route(
     *     "/autoedits/{project}/{username}/{namespace}/{start}/{end}", name="AutoEditsResult",
     *     requirements={
     *         "namespace" = "|all|\d+",
     *         "start" = "|\d{4}-\d{2}-\d{2}",
     *         "end" = "|\d{4}-\d{2}-\d{2}",
     *         "namespace" = "|all|\d+"
     *     },
     *     defaults={"namespace" = 0, "start" = "", "end" = ""}
     * )
     * @param Request $request The HTTP request.
     * @return RedirectResponse|Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request)
    {
        // Will redirect back to index if the user has too high of an edit count.
        $ret = $this->setupAutoEdits($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        // Render the view with all variables set.
        return $this->render('autoEdits/result.html.twig', $this->output);
    }

    /**
     * Get non-automated edits for the given user.
     * @Route(
     *   "/nonautoedits-contributions/{project}/{username}/{namespace}/{start}/{end}/{offset}",
     *   name="NonAutoEditsContributionsResult",
     *   requirements={
     *       "namespace" = "|all|\d+",
     *       "start" = "|\d{4}-\d{2}-\d{2}",
     *       "end" = "|\d{4}-\d{2}-\d{2}",
     *       "offset" = "\d*"
     *   },
     *   defaults={"namespace" = 0, "start" = "", "end" = "", "offset" = 0}
     * )
     * @param Request $request The HTTP request.
     * @return Response|RedirectResponse
     * @codeCoverageIgnore
     */
    public function nonAutomatedEditsAction(Request $request)
    {
        // Will redirect back to index if the user has too high of an edit count.
        $ret = $this->setupAutoEdits($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        return $this->render('autoEdits/nonautomated_edits.html.twig', $this->output);
    }

    /**
     * Get automated edits for the given user using the given tool.
     * @Route(
     *   "/autoedits-contributions/{project}/{username}/{namespace}/{start}/{end}/{offset}",
     *   name="AutoEditsContributionsResult",
     *   requirements={
     *       "namespace" = "|all|\d+",
     *       "start" = "|\d{4}-\d{2}-\d{2}",
     *       "end" = "|\d{4}-\d{2}-\d{2}",
     *       "offset" = "\d*"
     *   },
     *   defaults={"namespace" = 0, "start" = "", "end" = "", "offset" = 0}
     * )
     * @param Request $request The HTTP request.
     * @return Response|RedirectResponse
     * @codeCoverageIgnore
     */
    public function automatedEditsAction(Request $request)
    {
        // Will redirect back to index if the user has too high of an edit count.
        $ret = $this->setupAutoEdits($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        return $this->render('autoEdits/automated_edits.html.twig', $this->output);
    }

    /************************ API endpoints ************************/

    /**
     * Get a list of the automated tools and their regex/tags/etc.
     * @Route("/api/user/automated_tools/{project}")
     * @param AutomatedEditsHelper $aeh Provided by dependency injection.
     * @param string $project The project domain or database name.
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function automatedToolsApiAction(AutomatedEditsHelper $aeh, $project)
    {
        $this->recordApiUsage('user/automated_tools');
        $projectData = $this->validateProject($project);

        if ($projectData instanceof RedirectResponse) {
            return new JsonResponse(
                [
                    'error' => "$project is not a valid project",
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse($aeh->getTools($projectData));
    }

    /**
     * Count the number of automated edits the given user has made.
     * @Route(
     *   "/api/user/automated_editcount/{project}/{username}/{namespace}/{start}/{end}/{tools}",
     *   requirements={
     *       "namespace" = "|all|\d+",
     *       "start" = "|\d{4}-\d{2}-\d{2}",
     *       "end" = "|\d{4}-\d{2}-\d{2}"
     *   },
     *   defaults={"namespace" = "all", "start" = "", "end" = ""}
     * )
     * @param Request $request The HTTP request.
     * @param string $tools Non-blank to show which tools were used and how many times.
     * @return Response
     * @codeCoverageIgnore
     */
    public function automatedEditCountApiAction(Request $request, $tools = '')
    {
        $this->recordApiUsage('user/automated_editcount');

        $ret = $this->setupAutoEdits($request);
        if ($ret instanceof RedirectResponse) {
            // FIXME: Refactor JSON errors/responses, use Intuition as a service.
            return new JsonResponse(
                [
                    'error' => $this->getFlashMessage(),
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        $res = $this->getJsonData();
        $res['total_editcount'] = $this->autoEdits->getEditCount();

        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);

        $res['automated_editcount'] = $this->autoEdits->getAutomatedCount();
        $res['nonautomated_editcount'] = $res['total_editcount'] - $res['automated_editcount'];

        if ($tools != '') {
            $tools = $this->autoEdits->getToolCounts();
            $res['automated_tools'] = $tools;
        }

        $response->setData($res);
        return $response;
    }

    /**
     * Get non-automated edits for the given user.
     * @Route(
     *   "/api/user/nonautomated_edits/{project}/{username}/{namespace}/{start}/{end}/{offset}",
     *   requirements={
     *       "namespace" = "|all|\d+",
     *       "start" = "|\d{4}-\d{2}-\d{2}",
     *       "end" = "|\d{4}-\d{2}-\d{2}",
     *       "offset" = "\d*"
     *   },
     *   defaults={"namespace" = 0, "start" = "", "end" = "", "offset" = 0}
     * )
     * @param Request $request The HTTP request.
     * @return Response
     * @codeCoverageIgnore
     */
    public function nonAutomatedEditsApiAction(Request $request)
    {
        $this->recordApiUsage('user/nonautomated_edits');

        $ret = $this->setupAutoEdits($request);
        if ($ret instanceof RedirectResponse) {
            // FIXME: Refactor JSON errors/responses, use Intuition as a service.
            return new JsonResponse(
                [
                    'error' => $this->getFlashMessage(),
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $ret = $this->getJsonData();
        $ret['nonautomated_edits'] = $this->autoEdits->getNonAutomatedEdits(true);

        $namespaces = $this->project->getNamespaces();

        $ret['nonautomated_edits'] = array_map(function ($rev) use ($namespaces) {
            $pageTitle = $rev['page_title'];
            if ((int)$rev['page_namespace'] === 0) {
                $fullPageTitle = $pageTitle;
            } else {
                $fullPageTitle = $namespaces[$rev['page_namespace']].":$pageTitle";
            }

            return array_merge(['full_page_title' => $fullPageTitle], $rev);
        }, $ret['nonautomated_edits']);

        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);

        $response->setData($ret);
        return $response;
    }

    /**
     * Get (semi-)automated edits for the given user, optionally using the given tool.
     * @Route(
     *   "/api/user/automated_edits/{project}/{username}/{namespace}/{start}/{end}/{offset}",
     *   requirements={
     *       "namespace" = "|all|\d+",
     *       "start" = "|\d{4}-\d{2}-\d{2}",
     *       "end" = "|\d{4}-\d{2}-\d{2}",
     *       "offset" = "\d*"
     *   },
     *   defaults={"namespace" = 0, "start" = "", "end" = "", "offset" = 0}
     * )
     * @param Request $request The HTTP request.
     * @return Response
     * @codeCoverageIgnore
     */
    public function automatedEditsApiAction(Request $request)
    {
        $this->recordApiUsage('user/automated_edits');

        $ret = $this->setupAutoEdits($request);
        if ($ret instanceof RedirectResponse) {
            // FIXME: Refactor JSON errors/responses, use Intuition as a service.
            return new JsonResponse(
                [
                    'error' => $this->getFlashMessage(),
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $ret = $this->getJsonData();
        $ret['nonautomated_edits'] = $this->autoEdits->getAutomatedEdits(true);

        $namespaces = $this->project->getNamespaces();

        $ret['nonautomated_edits'] = array_map(function ($rev) use ($namespaces) {
            $pageTitle = $rev['page_title'];
            if ((int)$rev['page_namespace'] === 0) {
                $fullPageTitle = $pageTitle;
            } else {
                $fullPageTitle = $namespaces[$rev['page_namespace']].":$pageTitle";
            }

            return array_merge(['full_page_title' => $fullPageTitle], $rev);
        }, $ret['nonautomated_edits']);

        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);

        $response->setData($ret);
        return $response;
    }

    /**
     * Get data that will be used in API responses.
     * @return array
     * @codeCoverageIgnore
     */
    private function getJsonData()
    {
        $ret = [
            'project' => $this->project->getDomain(),
            'username' => $this->user->getUsername(),
        ];

        foreach (['namespace', 'start', 'end', 'offset'] as $param) {
            if (isset($this->{$param}) && $this->{$param} != '') {
                $ret[$param] = $this->{$param};
            }
        }

        return $ret;
    }
}
