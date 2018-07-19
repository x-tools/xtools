<?php
/**
 * This file contains only the EditCounterController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Xtools\EditCounter;
use Xtools\EditCounterRepository;
use Xtools\ProjectRepository;

/**
 * Class EditCounterController
 */
class EditCounterController extends XtoolsController
{
    /** @var EditCounter The edit-counter, that does all the work. */
    protected $editCounter;

    /**
     * Get the name of the tool's index route. This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute()
    {
        return 'EditCounter';
    }

    /**
     * EditCounterController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container)
    {
        // Causes the tool to redirect to the Simple Edit Counter if the user has too high of an edit count.
        $this->tooHighEditCountAction = 'SimpleEditCounterResult';

        // The rightsChanges action is exempt from the edit count limitation.
        $this->tooHighEditCountActionBlacklist = ['rightsChanges'];

        parent::__construct($requestStack, $container);
    }

    /**
     * Every action in this controller (other than 'index') calls this first.
     * If a response is returned, the calling action is expected to return it.
     * @param string $key API key, as given in the request. Omit this for actions
     *   that are public (only /api/ec actions should pass this in).
     * @return RedirectResponse|null
     * @throws AccessDeniedException If attempting to access internal endpoint.
     * @codeCoverageIgnore
     */
    protected function setUpEditCounter($key = null)
    {
        // Return the EditCounter if we already have one.
        if ($this->editCounter instanceof EditCounter) {
            return null;
        }

        // Validate key if attempted to make internal API request.
        if ($key && (string)$key !== (string)$this->container->getParameter('secret')) {
            throw $this->createAccessDeniedException('This endpoint is for internal use only.');
        }

        // Will redirect to Simple Edit Counter if they have too many edits, as defined self::construct.
        $this->validateUser($this->user->getUsername());

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
     * @Route("/ec", name="EditCounter")
     * @Route("/ec/", name="EditCounterSlash")
     * @Route("/ec/index.php", name="EditCounterIndexPhp")
     * @Route("/ec/{project}", name="EditCounterProject")
     * @return RedirectResponse|Response
     */
    public function indexAction()
    {
        if (isset($this->params['project']) && isset($this->params['username'])) {
            return $this->redirectToRoute('EditCounterResult', $this->params);
        }

        // Otherwise fall through.
        return $this->render('editCounter/index.html.twig', [
            'xtPageTitle' => 'tool-editcounter',
            'xtSubtitle' => 'tool-editcounter-desc',
            'xtPage' => 'editcounter',
            'project' => $this->project,
        ]);
    }

    /**
     * Display all results.
     * @Route("/ec/{project}/{username}", name="EditCounterResult")
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction()
    {
        $this->setUpEditCounter();

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
        return $this->getFormattedResponse($this->request, 'editCounter/result', $ret);
    }

    /**
     * Display the general statistics section.
     * @Route("/ec-generalstats/{project}/{username}", name="EditCounterGeneralStats")
     * @return Response
     * @codeCoverageIgnore
     */
    public function generalStatsAction()
    {
        $this->setUpEditCounter();

        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse($this->request, 'editCounter/general_stats', $ret);
    }

    /**
     * Display the namespace totals section.
     * @Route("/ec-namespacetotals/{project}/{username}", name="EditCounterNamespaceTotals")
     * @return Response
     * @codeCoverageIgnore
     */
    public function namespaceTotalsAction()
    {
        $this->setUpEditCounter();

        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse($this->request, 'editCounter/namespace_totals', $ret);
    }

    /**
     * Display the timecard section.
     * @Route("/ec-timecard/{project}/{username}", name="EditCounterTimecard")
     * @return Response
     * @codeCoverageIgnore
     */
    public function timecardAction()
    {
        $this->setUpEditCounter();

        $optedInPage = $this->project
            ->getRepository()
            ->getPage($this->project, $this->project->userOptInPage($this->user));

        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
            'opted_in_page' => $optedInPage,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse($this->request, 'editCounter/timecard', $ret);
    }

    /**
     * Display the year counts section.
     * @Route("/ec-yearcounts/{project}/{username}", name="EditCounterYearCounts")
     * @return Response
     * @codeCoverageIgnore
     */
    public function yearCountsAction()
    {
        $this->setUpEditCounter();

        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse($this->request, 'editCounter/yearcounts', $ret);
    }

    /**
     * Display the month counts section.
     * @Route("/ec-monthcounts/{project}/{username}", name="EditCounterMonthCounts")
     * @return Response
     * @codeCoverageIgnore
     */
    public function monthCountsAction()
    {
        $this->setUpEditCounter();

        $optedInPage = $this->project
            ->getRepository()
            ->getPage($this->project, $this->project->userOptInPage($this->user));
        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
            'opted_in_page' => $optedInPage,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse($this->request, 'editCounter/monthcounts', $ret);
    }

    /**
     * Display the user rights changes section.
     * @Route("/ec-rightschanges/{project}/{username}", name="EditCounterRightsChanges")
     * @return Response
     * @codeCoverageIgnore
     */
    public function rightsChangesAction()
    {
        $this->setUpEditCounter();

        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ];

        if ((bool)$this->container->hasParameter('app.is_labs')) {
            $ret['metaProject'] = ProjectRepository::getProject('metawiki', $this->container);
        }

        // Output the relevant format template.
        return $this->getFormattedResponse($this->request, 'editCounter/rights_changes', $ret);
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
     * @return Response
     * @codeCoverageIgnore
     */
    public function latestGlobalAction()
    {
        $this->setUpEditCounter();

        return $this->render('editCounter/latest_global.html.twig', [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'editcounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
            'offset' => $this->request->get('offset'),
            'pageSize' => $this->request->get('pagesize'),
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
     * @param string $key API key.
     * @return JsonResponse|RedirectResponse
     * @codeCoverageIgnore
     */
    public function pairDataApiAction($key)
    {
        $this->setUpEditCounter($key);

        return new JsonResponse(
            $this->editCounter->getPairData(),
            Response::HTTP_OK
        );
    }

    /**
     * Get various log counts for the user as JSON.
     * @Route("/api/ec/logcounts/{project}/{username}/{key}", name="EditCounterApiLogCounts")
     * @param string $key API key.
     * @return JsonResponse|RedirectResponse
     * @codeCoverageIgnore
     */
    public function logCountsApiAction($key)
    {
        $this->setUpEditCounter($key);

        return new JsonResponse(
            $this->editCounter->getLogCounts(),
            Response::HTTP_OK
        );
    }

    /**
     * Get edit sizes for the user as JSON.
     * @Route("/api/ec/editsizes/{project}/{username}/{key}", name="EditCounterApiEditSizes")
     * @param string $key API key.
     * @return JsonResponse|RedirectResponse
     * @codeCoverageIgnore
     */
    public function editSizesApiAction($key)
    {
        $this->setUpEditCounter($key);

        return new JsonResponse(
            $this->editCounter->getEditSizeData(),
            Response::HTTP_OK
        );
    }

    /**
     * Get the namespace totals for the user as JSON.
     * @Route("/api/ec/namespacetotals/{project}/{username}/{key}", name="EditCounterApiNamespaceTotals")
     * @param string $key API key.
     * @return Response|RedirectResponse
     * @codeCoverageIgnore
     */
    public function namespaceTotalsApiAction($key)
    {
        $this->setUpEditCounter($key);

        return new JsonResponse(
            $this->editCounter->namespaceTotals(),
            Response::HTTP_OK
        );
    }

    /**
     * Display or fetch the month counts for the user.
     * @Route("/api/ec/monthcounts/{project}/{username}/{key}", name="EditCounterApiMonthCounts")
     * @param string $key API key.
     * @return Response
     * @codeCoverageIgnore
     */
    public function monthCountsApiAction($key)
    {
        $this->setUpEditCounter($key);

        return new JsonResponse(
            $this->editCounter->monthCounts(),
            Response::HTTP_OK
        );
    }
}
