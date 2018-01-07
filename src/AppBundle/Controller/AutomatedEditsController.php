<?php
/**
 * This file contains only the AutomatedEditsController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Xtools\AutoEdits;
use Xtools\AutoEditsRepository;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;
use Xtools\Edit;

/**
 * This controller serves the AutomatedEdits tool.
 */
class AutomatedEditsController extends XtoolsController
{

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
            return $this->redirectToRoute('autoeditsResult', $params);
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
     * Display the results.
     * @Route(
     *     "/autoedits/{project}/{username}/{namespace}/{start}/{end}", name="autoeditsResult",
     *     requirements={
     *         "start" = "|\d{4}-\d{2}-\d{2}",
     *         "end" = "|\d{4}-\d{2}-\d{2}",
     *         "namespace" = "|all|\d+"
     *     }
     * )
     * @param Request $request The HTTP request.
     * @param int|string $namespace
     * @param null|string $start
     * @param null|string $end
     * @return RedirectResponse|Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request, $namespace = 0, $start = null, $end = null)
    {
        // Will redirect back to index if the user has too high of an edit count.
        $ret = $this->validateProjectAndUser($request, 'autoedits');
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($project, $user) = $ret;
        }

        // 'false' means the dates are optional and returned as 'false' if empty.
        list($start, $end) = $this->getUTCFromDateParams($start, $end, false);

        // We'll want to conditionally show some things in the view if there is a start date.
        $hasStartDate = $start > 0;

        // Format dates as needed by User model, if the date is present.
        if ($start !== false) {
            $start = date('Y-m-d', $start);
        }
        if ($end !== false) {
            $end = date('Y-m-d', $end);
        }

        // Normalize default namespace.
        if ($namespace == '') {
            $namespace = 0;
        }

        $autoEdits = new AutoEdits($project, $user, $namespace, $start, $end);
        $autoEditsRepo = new AutoEditsRepository();
        $autoEditsRepo->setContainer($this->container);
        $autoEdits->setRepository($autoEditsRepo);

        $editCount = $user->countEdits($project, $namespace, $start, $end);

        // Get individual counts of how many times each tool was used.
        // This also includes a wikilink to the tool.
        $toolCounts = $autoEdits->getAutomatedCounts();
        $toolsTotal = array_reduce($toolCounts, function ($a, $b) {
            return $a + $b['count'];
        });

        // Query to get combined (semi)automated using for all edits
        //   as some automated edits overlap.
        $autoCount = $autoEdits->countAutomatedEdits();

        $ret = [
            'xtPage' => 'autoedits',
            'user' => $user,
            'project' => $project,
            'toolCounts' => $toolCounts,
            'toolsTotal' => $toolsTotal,
            'autoCount' => $autoCount,
            'editCount' => $editCount,
            'autoPct' => $editCount ? ($autoCount / $editCount) * 100 : 0,
            'hasStartDate' => $hasStartDate,
            'start' => $start,
            'end' => $end,
            'namespace' => $namespace,
        ];

        // Render the view with all variables set.
        return $this->render('autoEdits/result.html.twig', $ret);
    }

    /************************ API endpoints ************************/

    /**
     * Count the number of automated edits the given user has made.
     * @Route(
     *   "/api/user/automated_editcount/{project}/{username}/{namespace}/{start}/{end}/{tools}",
     *   requirements={"start" = "|\d{4}-\d{2}-\d{2}", "end" = "|\d{4}-\d{2}-\d{2}"}
     * )
     * @param Request $request The HTTP request.
     * @param int|string $namespace ID of the namespace, or 'all' for all namespaces
     * @param string $start In the format YYYY-MM-DD
     * @param string $end In the format YYYY-MM-DD
     * @param string $tools Non-blank to show which tools were used and how many times.
     * @return Response
     * @codeCoverageIgnore
     */
    public function automatedEditCountApiAction(
        Request $request,
        $namespace = 'all',
        $start = '',
        $end = '',
        $tools = ''
    ) {
        $this->recordApiUsage('user/automated_editcount');

        list($project, $user) = $this->validateProjectAndUser($request);

        $res = [
            'project' => $project->getDomain(),
            'username' => $user->getUsername(),
            'total_editcount' => $user->countEdits($project, $namespace, $start, $end),
        ];

        $autoEdits = new AutoEdits($project, $user, $namespace, $start, $end);
        $autoEditsRepo = new AutoEditsRepository();
        $autoEditsRepo->setContainer($this->container);
        $autoEdits->setRepository($autoEditsRepo);

        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);

        if ($tools != '') {
            $tools = $autoEdits->getAutomatedCounts();
            $res['automated_editcount'] = 0;
            foreach ($tools as $tool) {
                $res['automated_editcount'] += $tool['count'];
            }
            $res['automated_tools'] = $tools;
        } else {
            $res['automated_editcount'] = $autoEdits->countAutomatedEdits();
        }

        $res['nonautomated_editcount'] = $res['total_editcount'] - $res['automated_editcount'];

        $response->setData($res);
        return $response;
    }

    /**
     * Get non-automated edits for the given user.
     * @Route(
     *   "/api/user/nonautomated_edits/{project}/{username}/{namespace}/{start}/{end}/{offset}",
     *   requirements={
     *       "start" = "|\d{4}-\d{2}-\d{2}",
     *       "end" = "|\d{4}-\d{2}-\d{2}",
     *       "offset" = "\d*"
     *   }
     * )
     * @param Request $request The HTTP request.
     * @param int|string $namespace ID of the namespace, or 'all' for all namespaces
     * @param string $start In the format YYYY-MM-DD
     * @param string $end In the format YYYY-MM-DD
     * @param int $offset For pagination, offset results by N edits
     * @return Response
     * @codeCoverageIgnore
     */
    public function nonAutomatedEditsApiAction(
        Request $request,
        $namespace = 0,
        $start = '',
        $end = '',
        $offset = 0
    ) {
        $this->recordApiUsage('user/nonautomated_edits');

        // Second parameter causes it return a Redirect to the index if the user has too many edits.
        // We only want to do this when looking at the user's overall edits, not just to a specific article.
        list($project, $user) = $this->validateProjectAndUser($request);

        // Reject if they've made too many edits.
        if ($user->hasTooManyEdits($project)) {
            if ($request->query->get('format') !== 'html') {
                return new JsonResponse(
                    [
                        'error' => 'Unable to show any data. User has made over ' .
                            $user->maxEdits() . ' edits.',
                    ],
                    Response::HTTP_FORBIDDEN
                );
            }

            $edits = [];
        } else {
            $autoEdits = new AutoEdits($project, $user, $namespace, $start, $end);
            $autoEditsRepo = new AutoEditsRepository();
            $autoEditsRepo->setContainer($this->container);
            $autoEdits->setRepository($autoEditsRepo);

            $edits = $autoEdits->getNonautomatedEdits($offset);
        }

        if ($request->query->get('format') === 'html') {
            if ($edits) {
                $edits = array_map(function ($attrs) use ($project, $user) {
                    $page = $project->getRepository()
                        ->getPage($project, $attrs['full_page_title']);
                    $pageTitles[] = $attrs['full_page_title'];
                    $attrs['id'] = $attrs['rev_id'];
                    $attrs['username'] = $user->getUsername();
                    return new Edit($page, $attrs);
                }, $edits);
            }

            $response = $this->render('autoEdits/nonautomated_edits.html.twig', [
                'edits' => $edits,
                'project' => $project,
                'maxEdits' => $user->maxEdits(),
            ]);
            $response->headers->set('Content-Type', 'text/html');
            return $response;
        }

        $ret = [
            'project' => $project->getDomain(),
            'username' => $user->getUsername(),
        ];
        if ($namespace != '' && $namespace !== 'all') {
            $ret['namespace'] = $namespace;
        }
        if ($start != '') {
            $ret['start'] = $start;
        }
        if ($end != '') {
            $ret['end'] = $end;
        }
        $ret['offset'] = $offset;
        $ret['nonautomated_edits'] = $edits;

        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);

        $response->setData($ret);
        return $response;
    }
}
