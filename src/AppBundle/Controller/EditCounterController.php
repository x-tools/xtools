<?php
/**
 * This file contains only the EditCounterController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Xtools\EditCounter;
use Xtools\EditCounterRepository;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;

/**
 * Class EditCounterController
 */
class EditCounterController extends XtoolsController
{

    /** @var User The user being queried. */
    protected $user;

    /** @var Project The project being queried. */
    protected $project;

    /** @var EditCounter The edit-counter, that does all the work. */
    protected $editCounter;

    /**
     * Get the name of the tool's index route.
     * This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute()
    {
        return 'EditCounter';
    }

    /**
     * Every action in this controller (other than 'index') calls this first.
     * If a response is returned, the calling action is expected to return it.
     * @param Request $request
     * @param string $key API key, as given in the request. Omit this for actions
     *   that are public (only /api/ec actions should pass this in).
     * @param bool $tooHighEditCount Whether to redirect if the user has too high of an edit count.
     * @return RedirectResponse|null
     * @throws AccessDeniedException If attempting to access internal endpoint.
     * @codeCoverageIgnore
     */
    protected function setUpEditCounter(Request $request, $key = null, $tooHighEditCount = true)
    {
        // Return the EditCounter if we already have one.
        if ($this->editCounter instanceof EditCounter) {
            return null;
        }

        // Validate key if attempted to make internal API request.
        if ($key && (string)$key !== (string)$this->container->getParameter('secret')) {
            throw $this->createAccessDeniedException('This endpoint is for internal use only.');
        }

        // Will redirect to Simple Edit Counter if they have too many edits.
        $ret = $this->validateProjectAndUser($request, $tooHighEditCount ? 'SimpleEditCounterResult' : null);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            // Get Project and User instances.
            list($this->project, $this->user) = $ret;
        }

        // Instantiate EditCounter.
        $editCounterRepo = new EditCounterRepository();
        $editCounterRepo->setContainer($this->container);
        $this->editCounter = new EditCounter(
            $this->project,
            $this->user,
            $this->container->get('app.i18n_helper')
        );
        $this->editCounter->setRepository($editCounterRepo);
    }

    /**
     * The initial GET request that displays the search form.
     *
     * @Route("/ec", name="EditCounter")
     * @Route("/ec/", name="EditCounterSlash")
     * @Route("/ec/index.php", name="EditCounterIndexPhp")
     * @Route("/ec/{project}", name="EditCounterProject")
     *
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function indexAction(Request $request)
    {
        $params = $this->parseQueryParams($request);

        if (isset($params['project']) && isset($params['username'])) {
            return $this->redirectToRoute('EditCounterResult', $params);
        }

        // Convert the given project (or default project) into a Project instance.
        $params['project'] = $this->getProjectFromQuery($params);

        // Otherwise fall through.
        return $this->render('editCounter/index.html.twig', [
            'xtPageTitle' => 'tool-editcounter',
            'xtSubtitle' => 'tool-editcounter-desc',
            'xtPage' => 'editcounter',
            'project' => $params['project'],
        ]);
    }

    /**
     * Display all results.
     * @Route("/ec/{project}/{username}", name="EditCounterResult")
     * @param Request $request
     * @param string $project
     * @param string $username
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request, $project, $username)
    {
        $ret = $this->setUpEditCounter($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        $ret = [
            'xtTitle' => $this->user->getUsername() . ' - ' . $this->project->getTitle(),
            'xtPage' => 'editcounter',
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ];

        // Used when querying for global rights changes.
        if ((bool)$this->container->hasParameter('app.is_labs')) {
            $ret['metaProject'] = ProjectRepository::getProject('metawiki', $this->container);
        }

        // Output the relevant format template.
        return $this->getFormattedResponse($request, 'editCounter/result', $ret);
    }

    /**
     * Display the general statistics section.
     * @Route("/ec-generalstats/{project}/{username}", name="EditCounterGeneralStats")
     * @param Request $request
     * @return Response
     * @codeCoverageIgnore
     */
    public function generalStatsAction(Request $request)
    {
        $ret = $this->setUpEditCounter($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        $isSubRequest = $this->get('request_stack')->getParentRequest() !== null;
        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse($request, 'editCounter/general_stats', $ret);
    }

    /**
     * Display the namespace totals section.
     * @Route("/ec-namespacetotals/{project}/{username}", name="EditCounterNamespaceTotals")
     * @param Request $request
     * @return Response
     * @codeCoverageIgnore
     */
    public function namespaceTotalsAction(Request $request)
    {
        $ret = $this->setUpEditCounter($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        $isSubRequest = $this->get('request_stack')->getParentRequest() !== null;
        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse($request, 'editCounter/namespace_totals', $ret);
    }

    /**
     * Display the timecard section.
     * @Route("/ec-timecard/{project}/{username}", name="EditCounterTimecard")
     * @param Request $request
     * @return Response
     * @codeCoverageIgnore
     */
    public function timecardAction(Request $request)
    {
        $ret = $this->setUpEditCounter($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        $isSubRequest = $this->get('request_stack')->getParentRequest() !== null;
        $optedInPage = $this->project
            ->getRepository()
            ->getPage($this->project, $this->project->userOptInPage($this->user));

        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
            'opted_in_page' => $optedInPage,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse($request, 'editCounter/timecard', $ret);
    }

    /**
     * Display the year counts section.
     * @Route("/ec-yearcounts/{project}/{username}", name="EditCounterYearCounts")
     * @param Request $request
     * @return Response
     * @codeCoverageIgnore
     */
    public function yearcountsAction(Request $request)
    {
        $ret = $this->setUpEditCounter($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        $isSubRequest = $this->container->get('request_stack')->getParentRequest() !== null;
        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse($request, 'editCounter/yearcounts', $ret);
    }

    /**
     * Display the month counts section.
     * @Route("/ec-monthcounts/{project}/{username}", name="EditCounterMonthCounts")
     * @param Request $request
     * @return Response
     * @codeCoverageIgnore
     */
    public function monthcountsAction(Request $request)
    {
        $ret = $this->setUpEditCounter($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        $isSubRequest = $this->container->get('request_stack')->getParentRequest() !== null;
        $optedInPage = $this->project
            ->getRepository()
            ->getPage($this->project, $this->project->userOptInPage($this->user));
        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
            'opted_in_page' => $optedInPage,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse($request, 'editCounter/monthcounts', $ret);
    }

    /**
     * Display the user rights changes section.
     * @Route("/ec-rightschanges/{project}/{username}", name="EditCounterRightsChanges")
     * @param Request $request
     * @return Response
     * @codeCoverageIgnore
     */
    public function rightschangesAction(Request $request)
    {
        $ret = $this->setUpEditCounter($request, null, false);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        $isSubRequest = $this->container->get('request_stack')->getParentRequest() !== null;
        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ];

        if ((bool)$this->container->hasParameter('app.is_labs')) {
            $ret['metaProject'] = ProjectRepository::getProject('metawiki', $this->container);
        }

        // Output the relevant format template.
        return $this->getFormattedResponse($request, 'editCounter/rights_changes', $ret);
    }

    /**
     * Display the latest global edits section.
     * @Route(
     *     "/ec-latestglobal-contributions/{project}/{username}/{offset}",
     *     name="EditCounterLatestGlobalContribs",
     *     requirements={"offset" = "|\d*"},
     *     defaults={"offset" = 0}
     * )
     * @Route(
     *     "/ec-latestglobal/{project}/{username}/{offset}",
     *     name="EditCounterLatestGlobal",
     *     requirements={"offset" = "|\d*"},
     *     defaults={"offset" = 0}
     * ),
     * @param Request $request The HTTP request.
     * @return Response
     * @codeCoverageIgnore
     */
    public function latestglobalAction(Request $request)
    {
        $ret = $this->setUpEditCounter($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        $isSubRequest = $request->get('htmlonly')
                        || $this->container->get('request_stack')->getParentRequest() !== null;
        return $this->render('editCounter/latest_global.html.twig', [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
            'offset' => $request->get('offset'),
            'pageSize' => $request->get('pagesize'),
        ]);
    }


    /**
     * Below are internal API endpoints for the Edit Counter.
     * All only respond with JSON and only to requests passing in the value
     * of the 'secret' parameter. This should not be used in JavaScript or clientside
     * applications, rather only used internally.
     */

    /**
     * Get (most) of the general statistics as JSON.
     * @Route("/api/ec/pairdata/{project}/{username}/{key}", name="EditCounterApiPairData")
     * @param Request $request
     * @param string $key API key.
     * @return JsonResponse|RedirectResponse
     * @codeCoverageIgnore
     */
    public function pairDataApiAction(Request $request, $key)
    {
        $ret = $this->setUpEditCounter($request, $key);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        return new JsonResponse(
            $this->editCounter->getPairData(),
            Response::HTTP_OK
        );
    }

    /**
     * Get various log counts for the user as JSON.
     * @Route("/api/ec/logcounts/{project}/{username}/{key}", name="EditCounterApiLogCounts")
     * @param Request $request
     * @param string $key API key.
     * @return JsonResponse|RedirectResponse
     * @codeCoverageIgnore
     */
    public function logCountsApiAction(Request $request, $key)
    {
        $ret = $this->setUpEditCounter($request, $key);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        return new JsonResponse(
            $this->editCounter->getLogCounts(),
            Response::HTTP_OK
        );
    }

    /**
     * Get edit sizes for the user as JSON.
     * @Route("/api/ec/editsizes/{project}/{username}/{key}", name="EditCounterApiEditSizes")
     * @param Request $request
     * @param string $key API key.
     * @return JsonResponse|RedirectResponse
     * @codeCoverageIgnore
     */
    public function editSizesApiAction(Request $request, $key)
    {
        $ret = $this->setUpEditCounter($request, $key);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        return new JsonResponse(
            $this->editCounter->getEditSizeData(),
            Response::HTTP_OK
        );
    }

    /**
     * Get the namespace totals for the user as JSON.
     * @Route("/api/ec/namespacetotals/{project}/{username}/{key}", name="EditCounterApiNamespaceTotals")
     * @param Request $request
     * @param string $key API key.
     * @return Response|RedirectResponse
     * @codeCoverageIgnore
     */
    public function namespaceTotalsApiAction(Request $request, $key)
    {
        $ret = $this->setUpEditCounter($request, $key);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        return new JsonResponse(
            $this->editCounter->namespaceTotals(),
            Response::HTTP_OK
        );
    }

    /**
     * Display or fetch the month counts for the user.
     * @Route("/api/ec/monthcounts/{project}/{username}/{key}", name="EditCounterApiMonthCounts")
     * @param Request $request
     * @param string $key API key.
     * @return Response
     * @codeCoverageIgnore
     */
    public function monthcountsApiAction(Request $request, $key)
    {
        $ret = $this->setUpEditCounter($request, $key);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        }

        return new JsonResponse(
            $this->editCounter->monthCounts(),
            Response::HTTP_OK
        );
    }
}
