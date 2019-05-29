<?php
/**
 * This file contains only the EditCounterController class.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Exception\XtoolsHttpException;
use AppBundle\Helper\I18nHelper;
use AppBundle\Model\EditCounter;
use AppBundle\Repository\EditCounterRepository;
use AppBundle\Repository\ProjectRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Class EditCounterController
 */
class EditCounterController extends XtoolsController
{
    /**
     * Available statistic sections. These can be hand-picked on the index form so that you only get the data you
     * want and hence speed up the tool. Keys are the i18n messages (and DOM IDs), values are the action names.
     */
    private const AVAILABLE_SECTIONS = [
        'general-stats' => 'EditCounterGeneralStats',
        'namespace-totals' => 'EditCounterNamespaceTotals',
        'year-counts' => 'EditCounterYearCounts',
        'month-counts' => 'EditCounterMonthCounts',
        'timecard' => 'EditCounterTimecard',
        'top-edited-pages' => 'TopEditsResultNamespace',
        'rights-changes' => 'EditCounterRightsChanges',
        'latest-global-edits' => 'EditCounterLatestGlobalContribs',
    ];

    /** @var EditCounter The edit-counter, that does all the work. */
    protected $editCounter;

    /** @var string[] Which sections to show. */
    protected $sections;

    /**
     * Get the name of the tool's index route. This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'EditCounter';
    }

    /**
     * EditCounterController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     * @param I18nHelper $i18n
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container, I18nHelper $i18n)
    {
        // Causes the tool to redirect to the Simple Edit Counter if the user has too high of an edit count.
        $this->tooHighEditCountAction = 'SimpleEditCounterResult';

        // The rightsChanges action is exempt from the edit count limitation.
        $this->tooHighEditCountActionBlacklist = ['rightsChanges'];

        $this->restrictedActions = ['monthCountsApi', 'timecardApi'];

        parent::__construct($requestStack, $container, $i18n);
    }

    /**
     * Every action in this controller (other than 'index') calls this first.
     * If a response is returned, the calling action is expected to return it.
     * @return null
     * @throws AccessDeniedException If attempting to access internal endpoint.
     * @throws XtoolsHttpException If an API request to restricted endpoint when user has not opted in.
     * @codeCoverageIgnore
     */
    protected function setUpEditCounter()
    {
        // Whether we're making a subrequest (the view makes a request to another action).
        // Subrequests to the same controller do not re-instantiate a new controller, and hence
        // this flag would not be set in XtoolsController::__construct(), so we must do it here as well.
        $this->isSubRequest = $this->request->get('htmlonly')
            || null !== $this->get('request_stack')->getParentRequest();

        // Return the EditCounter if we already have one.
        if (isset($this->editCounter)) {
            return null;
        }

        // Will redirect to Simple Edit Counter if they have too many edits, as defined self::construct.
        $this->validateUser($this->user->getUsername());

        // Store which sections of the Edit Counter they requested.
        $this->sections = $this->getRequestedSections();

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
     * @Route("/ec/index.php", name="EditCounterIndexPhp")
     * @Route("/ec/{project}", name="EditCounterProject")
     * @return RedirectResponse|Response
     */
    public function indexAction()
    {
        if (isset($this->params['project']) && isset($this->params['username'])) {
            return $this->redirectFromSections();
        }

        $this->sections = $this->getRequestedSections(true);

        // Otherwise fall through.
        return $this->render('editCounter/index.html.twig', [
            'xtPageTitle' => 'tool-editcounter',
            'xtSubtitle' => 'tool-editcounter-desc',
            'xtPage' => 'EditCounter',
            'project' => $this->project,
            'sections' => $this->sections,
            'availableSections' => $this->getSectionNames(),
            'isAllSections' => $this->sections === $this->getSectionNames(),
        ]);
    }

    /**
     * Get the requested sections either from the URL, cookie, or the defaults (all sections).
     * @param bool $useCookies Whether or not to check cookies for the preferred sections.
     *   This option should not be true except on the index form.
     * @return array|mixed|string[]
     * @codeCoverageIgnore
     */
    private function getRequestedSections(bool $useCookies = false)
    {
        // Happens from sub-tool index pages, e.g. see self::generalStatsIndexAction().
        if (isset($this->sections)) {
            return $this->sections;
        }

        // Query param for sections gets priority.
        $sectionsQuery = $this->request->get('sections', '');

        // If not present, try the cookie, and finally the defaults (all sections).
        if ($useCookies && '' == $sectionsQuery) {
            $sectionsQuery = $this->request->cookies->get('XtoolsEditCounterOptions', '');
        }

        // Either a pipe-separated string or an array.
        $sections = is_array($sectionsQuery) ? $sectionsQuery : explode('|', $sectionsQuery);

        // Filter out any invalid section IDs.
        $sections = array_filter($sections, function ($section) {
            return in_array($section, $this->getSectionNames());
        });

        // Fallback for when no valid sections were requested or provided by the cookie.
        if (0 === count($sections)) {
            $sections = $this->getSectionNames();
        }

        return $sections;
    }

    /**
     * Get the names of the available sections.
     * @return string[]
     * @codeCoverageIgnore
     */
    private function getSectionNames(): array
    {
        return array_keys(self::AVAILABLE_SECTIONS);
    }

    /**
     * Redirect to the appropriate action based on what sections are being requested.
     * @return RedirectResponse
     * @codeCoverageIgnore
     */
    private function redirectFromSections(): RedirectResponse
    {
        $this->sections = $this->getRequestedSections();

        if (1 === count($this->sections)) {
            // Redirect to dedicated route.
            $response = $this->redirectToRoute(self::AVAILABLE_SECTIONS[$this->sections[0]], $this->params);
        } elseif ($this->sections === $this->getSectionNames()) {
            $response = $this->redirectToRoute('EditCounterResult', $this->params);
        } else {
            // Add sections to the params, which $this->generalUrl() will append to the URL.
            $this->params['sections'] = implode('|', $this->sections);

            // We want a pretty URL, with pipes | instead of the encoded value %7C
            $url = str_replace('%7C', '|', $this->generateUrl('EditCounterResult', $this->params));

            $response = $this->redirect($url);
        }

        // Save the preferred sections in a cookie.
        $response->headers->setCookie(
            new Cookie('XtoolsEditCounterOptions', implode('|', $this->sections))
        );

        return $response;
    }

    /**
     * Display all results.
     * @Route("/ec/{project}/{username}", name="EditCounterResult")
     * @return Response|RedirectResponse
     * @codeCoverageIgnore
     */
    public function resultAction()
    {
        $this->setUpEditCounter();

        if (1 === count($this->sections)) {
            // Redirect to dedicated route.
            return $this->redirectToRoute(self::AVAILABLE_SECTIONS[$this->sections[0]], $this->params);
        }

        $ret = [
            'xtTitle' => $this->user->getUsername() . ' - ' . $this->project->getTitle(),
            'xtPage' => 'EditCounter',
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
            'sections' => $this->sections,
            'isAllSections' => $this->sections === $this->getSectionNames(),
        ];

        // Used when querying for global rights changes.
        if ((bool)$this->container->hasParameter('app.is_labs')) {
            $ret['metaProject'] = ProjectRepository::getProject('metawiki', $this->container);
        }

        $response = $this->getFormattedResponse('editCounter/result', $ret);

        return $response;
    }

    /**
     * Display the general statistics section.
     * @Route("/ec-generalstats/{project}/{username}", name="EditCounterGeneralStats")
     * @return Response
     * @codeCoverageIgnore
     */
    public function generalStatsAction(): Response
    {
        $this->setUpEditCounter();

        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'EditCounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse('editCounter/general_stats', $ret);
    }

    /**
     * Search form for general stats.
     * @Route("/ec-generalstats", name="EditCounterGeneralStatsIndex")
     * @return Response
     */
    public function generalStatsIndexAction(): Response
    {
        $this->sections = ['general-stats'];
        return $this->indexAction();
    }

    /**
     * Display the namespace totals section.
     * @Route("/ec-namespacetotals/{project}/{username}", name="EditCounterNamespaceTotals")
     * @return Response
     * @codeCoverageIgnore
     */
    public function namespaceTotalsAction(): Response
    {
        $this->setUpEditCounter();

        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'EditCounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse('editCounter/namespace_totals', $ret);
    }

    /**
     * Search form for namespace totals.
     * @Route("/ec-namespacetotals", name="EditCounterNamespaceTotalsIndex")
     * @return Response
     */
    public function namespaceTotalsIndexAction(): Response
    {
        $this->sections = ['namespace-totals'];
        return $this->indexAction();
    }

    /**
     * Display the timecard section.
     * @Route("/ec-timecard/{project}/{username}", name="EditCounterTimecard")
     * @return Response
     * @codeCoverageIgnore
     */
    public function timecardAction(): Response
    {
        $this->setUpEditCounter();

        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'EditCounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
            'opted_in_page' => $this->getOptedInPage(),
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse('editCounter/timecard', $ret);
    }

    /**
     * Search form for timecard.
     * @Route("/ec-timecard", name="EditCounterTimecardIndex")
     * @return Response
     */
    public function timecardIndexAction(): Response
    {
        $this->sections = ['timecard'];
        return $this->indexAction();
    }

    /**
     * Display the year counts section.
     * @Route("/ec-yearcounts/{project}/{username}", name="EditCounterYearCounts")
     * @return Response
     * @codeCoverageIgnore
     */
    public function yearCountsAction(): Response
    {
        $this->setUpEditCounter();

        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'EditCounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse('editCounter/yearcounts', $ret);
    }

    /**
     * Search form for year counts.
     * @Route("/ec-yearcounts", name="EditCounterYearCountsIndex")
     * @return Response
     */
    public function yearCountsIndexAction(): Response
    {
        $this->sections = ['year-counts'];
        return $this->indexAction();
    }

    /**
     * Display the month counts section.
     * @Route("/ec-monthcounts/{project}/{username}", name="EditCounterMonthCounts")
     * @return Response
     * @codeCoverageIgnore
     */
    public function monthCountsAction(): Response
    {
        $this->setUpEditCounter();

        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'EditCounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
            'opted_in_page' => $this->getOptedInPage(),
        ];

        // Output the relevant format template.
        return $this->getFormattedResponse('editCounter/monthcounts', $ret);
    }

    /**
     * Search form for month counts.
     * @Route("/ec-monthcounts", name="EditCounterMonthCountsIndex")
     * @return Response
     */
    public function monthCountsIndexAction(): Response
    {
        $this->sections = ['month-counts'];
        return $this->indexAction();
    }

    /**
     * Display the user rights changes section.
     * @Route("/ec-rightschanges/{project}/{username}", name="EditCounterRightsChanges")
     * @return Response
     * @codeCoverageIgnore
     */
    public function rightsChangesAction(): Response
    {
        $this->setUpEditCounter();

        $ret = [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'EditCounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ];

        if ((bool)$this->container->hasParameter('app.is_labs')) {
            $ret['metaProject'] = ProjectRepository::getProject('metawiki', $this->container);
        }

        // Output the relevant format template.
        return $this->getFormattedResponse('editCounter/rights_changes', $ret);
    }

    /**
     * Search form for rights changes.
     * @Route("/ec-rightschanges", name="EditCounterRightsChangesIndex")
     * @return Response
     */
    public function rightsChangesIndexAction(): Response
    {
        $this->sections = ['rights-changes'];
        return $this->indexAction();
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
    public function latestGlobalAction(): Response
    {
        $this->setUpEditCounter();

        return $this->render('editCounter/latest_global.html.twig', [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'EditCounter',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
            'offset' => $this->request->get('offset'),
            'pageSize' => $this->request->get('pagesize'),
        ]);
    }

    /**
     * Search form for latest global edits.
     * @Route("/ec-latestglobal-contributions", name="EditCounterLatestGlobalContribsIndex")
     * @Route("/ec-latestglobal", name="EditCounterLatestGlobalIndex")
     * @Route("/ec-latestglobaledits", name="EditCounterLatestGlobalEditsIndex")
     * @return Response
     */
    public function latestGlobalIndexAction(): Response
    {
        $this->sections = ['latest-global-edits'];
        return $this->indexAction();
    }

    /************************ API endpoints ************************/

    /**
     * Get various log counts for the user as JSON.
     * @Route("/api/user/log_counts/{project}/{username}", name="UserApiLogCounts")
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function logCountsApiAction(): JsonResponse
    {
        $this->setUpEditCounter();

        return $this->getFormattedApiResponse([
            'log_counts' => $this->editCounter->getLogCounts(),
        ]);
    }

    /**
     * Get the namespace totals for the user as JSON.
     * @Route("/api/user/namespace_totals/{project}/{username}", name="UserApiNamespaceTotals")
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function namespaceTotalsApiAction(): JsonResponse
    {
        $this->setUpEditCounter();

        return $this->getFormattedApiResponse([
            'namespace_totals' => $this->editCounter->namespaceTotals(),
        ]);
    }

    /**
     * Get the month counts for the user as JSON.
     * @Route("/api/user/month_counts/{project}/{username}", name="UserApiMonthCounts")
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function monthCountsApiAction(): JsonResponse
    {
        $this->setUpEditCounter();

        $ret = $this->editCounter->monthCounts();

        // Remove labels that are only needed by Twig views, and not consumers of the API.
        unset($ret['yearLabels']);
        unset($ret['monthLabels']);

        return $this->getFormattedApiResponse($ret);
    }

    /**
     * Get the timecard data as JSON.
     * @Route("/api/user/timecard/{project}/{username}", name="UserApiTimeCard")
     * @return JsonResponse
     */
    public function timecardApiAction(): JsonResponse
    {
        $this->setUpEditCounter();

        return $this->getFormattedApiResponse([
            'timecard' => $this->editCounter->timeCard(),
        ]);
    }
}
