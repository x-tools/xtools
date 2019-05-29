<?php
/**
 * This file contains the code that powers the AdminStats page of XTools.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Helper\I18nHelper;
use AppBundle\Model\AdminStats;
use AppBundle\Model\Project;
use AppBundle\Repository\AdminStatsRepository;
use AppBundle\Repository\ProjectRepository;
use AppBundle\Repository\UserRightsRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The AdminStatsController serves the search form and results page of the AdminStats tool.
 */
class AdminStatsController extends XtoolsController
{
    /** @var AdminStats The admin stats instance that does all the work. */
    protected $adminStats;

    public const DEFAULT_DAYS = 31;
    public const MAX_DAYS_UI = 365;
    public const MAX_DAYS_API = 31;

    /**
     * Get the name of the tool's index route. This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'AdminStats';
    }

    /**
     * AdminStatsController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     * @param I18nHelper $i18n
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container, I18nHelper $i18n)
    {
        // Set the max length for the date range. Value is smaller for API requests.
        $isApi = '/api/' === substr($requestStack->getCurrentRequest()->getPathInfo(), 0, 5);
        $this->maxDays = $isApi ? self::MAX_DAYS_API : self::MAX_DAYS_UI;
        $this->defaultDays = self::DEFAULT_DAYS;

        parent::__construct($requestStack, $container, $i18n);
    }

    /**
     * Method for rendering the AdminStats Main Form.
     * This method redirects if valid parameters are found, making it a valid form endpoint as well.
     * @Route(
     *     "/{group}stats", name="AdminStats",
     *     requirements={"group"="admin|patroller|steward"},
     *     defaults={"group"="admin"}
     * )
     * @return Response
     */
    public function indexAction(): Response
    {
        $this->getAndSetRequestedActions();

        // Redirect if we have a project.
        if (isset($this->params['project'])) {
            // We want pretty URLs.
            $route = $this->generateUrl('AdminStatsResult', $this->params);
            $url = str_replace('%7C', '|', $route);
            return $this->redirect($url);
        }

        $adminStatsRepo = new AdminStatsRepository();
        $adminStatsRepo->setContainer($this->container);
        $actionsConfig = $adminStatsRepo->getConfig($this->project);
        $group = $this->params['group'];
        $xtPage = lcfirst($group).'Stats';

        $params = array_merge([
            'xtPage' => $xtPage,
            'xtPageTitle' => "tool-{$group}stats",
            'xtSubtitle' => "tool-{$group}stats-desc",
            'actionsConfig' => $actionsConfig,

            // Defaults that will get overridden if in $params.
            'start' => '',
            'end' => '',
            'group' => 'admin',
        ], $this->params);
        $params['project'] = $this->normalizeProject($params['group']);

        $params['isAllActions'] = $params['actions'] === implode('|', $this->getActionNames($params['group']));

        // Otherwise render form.
        return $this->render('adminStats/index.html.twig', $params);
    }

    /**
     * Normalize the Project to be Meta if viewing Steward Stats.
     * @param string $group
     * @return Project
     */
    private function normalizeProject(string $group): Project
    {
        if ('meta.wikimedia.org' !== $this->project->getDomain() &&
            'steward' === $group &&
            $this->getParameter('app.is_labs')
        ) {
            $this->project = ProjectRepository::getProject('meta.wikimedia.org', $this->container);
        }

        return $this->project;
    }

    /**
     * Get the requested actions and set the class property.
     * @return string[]
     * @codeCoverageIgnore
     */
    private function getAndSetRequestedActions(): array
    {
        /** @var string $group The requested 'group'. See keys at admin_stats.yml for possible values. */
        $group = $this->params['group'] = $this->params['group'] ?? 'admin';

        // Query param for sections gets priority.
        $actionsQuery = $this->request->get('actions', '');

        // Either a pipe-separated string or an array.
        $actions = is_array($actionsQuery) ? $actionsQuery : explode('|', $actionsQuery);

        // Filter out any invalid section IDs.
        $actions = array_filter($actions, function ($action) use ($group) {
            return in_array($action, $this->getActionNames($group));
        });

        // Fallback for when no valid sections were requested.
        if (0 === count($actions)) {
            $actions = $this->getActionNames($group);
        }

        // Store as pipe-separated string for prettier URLs.
        $this->params['actions'] = str_replace('%7C', '|', implode('|', $actions));

        return $actions;
    }

    /**
     * Get the names of the available sections.
     * @param string $group Corresponds to the groups specified in admin_stats.yml
     * @return string[]
     * @codeCoverageIgnore
     */
    private function getActionNames(string $group): array
    {
        $actionsConfig = $this->container->getParameter('admin_stats');
        return array_keys($actionsConfig[$group]['actions']);
    }

    /**
     * Every action in this controller (other than 'index') calls this first.
     * @return AdminStats
     * @codeCoverageIgnore
     */
    public function setUpAdminStats(): AdminStats
    {
        $adminStatsRepo = new AdminStatsRepository();
        $adminStatsRepo->setContainer($this->container);
        $group = $this->params['group'] ?? 'admin';

        $this->adminStats = new AdminStats(
            $this->normalizeProject($group),
            (int)$this->start,
            (int)$this->end,
            $group ?? 'admin',
            $this->getAndSetRequestedActions()
        );
        $this->adminStats->setRepository($adminStatsRepo);

        // For testing purposes.
        return $this->adminStats;
    }

    /**
     * Method for rendering the AdminStats results.
     * @Route(
     *     "/{group}stats/{project}/{start}/{end}", name="AdminStatsResult",
     *     requirements={"start"="|\d{4}-\d{2}-\d{2}", "end"="|\d{4}-\d{2}-\d{2}", "group"="admin|patroller|steward"},
     *     defaults={"start"=false, "end"=false, "group"="admin"}
     * )
     * @param I18nHelper $i18n
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(I18nHelper $i18n): Response
    {
        $this->setUpAdminStats();

        $this->adminStats->prepareStats();

        // For the HTML view, we want the localized name of the user groups.
        // These are in the 'title' attribute of the icons for each user group.
        $userRightsRepo = new UserRightsRepository();
        $userRightsRepo->setContainer($this->container);
        $rightsNames = $userRightsRepo->getRightsNames($this->project, $i18n->getLang());

        return $this->getFormattedResponse('adminStats/result', [
            'xtPage' => lcfirst($this->params['group']).'Stats',
            'xtTitle' => $this->project->getDomain(),
            'as' => $this->adminStats,
            'rightsNames' => $rightsNames,
        ]);
    }

    /************************ API endpoints ************************/

    /**
     * Get users of the project that are capable of making 'admin actions',
     * keyed by user name with a list of the relevant user groups as the values.
     * @Route("/api/project/admins_groups/{project}", name="ProjectApiAdminsGroups")
     * @Route("/api/project/users_groups/{project}/{group}")
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function adminsGroupsApiAction(): JsonResponse
    {
        $this->recordApiUsage('project/admins_groups');

        $this->setUpAdminStats();

        unset($this->params['actions']);
        unset($this->params['start']);
        unset($this->params['end']);

        return $this->getFormattedApiResponse([
            'users_and_groups' => $this->adminStats->getUsersAndGroups(),
        ]);
    }

    /**
     * Same as adminStatsApiAction except it accepts dates.
     * @Route(
     *     "/api/project/{group}_stats/{project}/{start}/{end}",
     *     name="ProjectApiAdminStatsDates",
     *     requirements={"start"="|\d{4}-\d{2}-\d{2}", "end"="|\d{4}-\d{2}-\d{2}", "group"="admin|patroller|steward"},
     *     defaults={"start"=false, "end"=false, "group"="admin"}
     * )
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function adminStatsDatesApiAction(): JsonResponse
    {
        $this->recordApiUsage('project/adminstats');

        $this->setUpAdminStats();
        $this->adminStats->prepareStats();

        return $this->getFormattedApiResponse([
            'users' => $this->adminStats->getStats(),
        ]);
    }
}
