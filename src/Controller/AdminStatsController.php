<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\I18nHelper;
use App\Model\AdminStats;
use App\Model\Project;
use App\Repository\AdminStatsRepository;
use App\Repository\UserRightsRepository;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The AdminStatsController serves the search form and results page of the AdminStats tool.
 */
class AdminStatsController extends XtoolsController
{
    protected AdminStats $adminStats;

    public const DEFAULT_DAYS = 31;
    public const MAX_DAYS_UI = 365;
    public const MAX_DAYS_API = 31;

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'AdminStats';
    }

    /**
     * Set the max length for the date range. Value is smaller for API requests.
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function maxDays(): ?int
    {
        return $this->isApi ? self::MAX_DAYS_API : self::MAX_DAYS_UI;
    }

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function defaultDays(): ?int
    {
        return self::DEFAULT_DAYS;
    }

    /**
     * Method for rendering the AdminStats Main Form.
     * This method redirects if valid parameters are found, making it a valid form endpoint as well.
     */
    #[Route(
        "/adminstats",
        name: "AdminStats",
        requirements: ["group" => "admin|patroller|steward"],
        defaults: ["group" => "admin"]
    )]
    #[Route(
        "/patrollerstats",
        name: "PatrollerStats",
        requirements: ["group" => "admin|patroller|steward"],
        defaults: ["group" => "patroller"]
    )]
    #[Route(
        "/stewardstats",
        name: "StewardStats",
        requirements: ["group" => "admin|patroller|steward"],
        defaults: ["group" => "steward"]
    )]
    public function indexAction(AdminStatsRepository $adminStatsRepo): Response
    {
        $this->getAndSetRequestedActions();

        // Redirect if we have a project.
        if (isset($this->params['project'])) {
            // We want pretty URLs.
            if ($this->getActionNames($this->params['group']) === explode('|', $this->params['actions'])) {
                unset($this->params['actions']);
            }
            $route = $this->generateUrl('AdminStatsResult', $this->params);
            $url = str_replace('%7C', '|', $route);
            return $this->redirect($url);
        }

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
            $this->getParameter('app.is_wmf')
        ) {
            $this->project = $this->projectRepo->getProject('meta.wikimedia.org');
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
        /** @var string $group The requested 'group'. See keys at admin_stats.yaml for possible values. */
        $group = $this->params['group'] = $this->params['group'] ?? 'admin';

        // Query param for sections gets priority.
        $actionsQuery = $this->request->get('actions', '');

        // Either a pipe-separated string or an array.
        $actionsRequested = is_array($actionsQuery) ? $actionsQuery : array_filter(explode('|', $actionsQuery));

        // Filter out any invalid action names.
        $actions = array_filter($actionsRequested, function ($action) use ($group) {
            return in_array($action, $this->getActionNames($group));
        });

        // Warn about unsupported actions in the API.
        if ($this->isApi) {
            foreach (array_diff($actionsRequested, $actions) as $value) {
                $this->addFlashMessage('warning', 'error-param', [$value, 'actions']);
            }
        }

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
     * @param string $group Corresponds to the groups specified in admin_stats.yaml
     * @return string[]
     * @codeCoverageIgnore
     */
    private function getActionNames(string $group): array
    {
        $actionsConfig = $this->getParameter('admin_stats');
        return array_keys($actionsConfig[$group]['actions']);
    }

    /**
     * Every action in this controller (other than 'index') calls this first.
     * @codeCoverageIgnore
     */
    public function setUpAdminStats(AdminStatsRepository $adminStatsRepo): AdminStats
    {
        $group = $this->params['group'] ?? 'admin';

        $this->adminStats = new AdminStats(
            $adminStatsRepo,
            $this->normalizeProject($group),
            (int)$this->start,
            (int)$this->end,
            $group ?? 'admin',
            $this->getAndSetRequestedActions()
        );

        // For testing purposes.
        return $this->adminStats;
    }

    /**
     * Method for rendering the AdminStats results.
     * @codeCoverageIgnore
     */
    #[Route(
        "/{group}stats/{project}/{start}/{end}",
        name: "AdminStatsResult",
        requirements: [
            "start" => "|\d{4}-\d{2}-\d{2}",
            "end" => "|\d{4}-\d{2}-\d{2}",
            "group" => "admin|patroller|steward",
        ],
        defaults: [
            "start" => false,
            "end" => false,
            "group" => "admin",
        ]
    )]
    public function resultAction(
        AdminStatsRepository $adminStatsRepo,
        UserRightsRepository $userRightsRepo,
        I18nHelper $i18n
    ): Response {
        $this->setUpAdminStats($adminStatsRepo);

        $this->adminStats->prepareStats();

        // For the HTML view, we want the localized name of the user groups.
        // These are in the 'title' attribute of the icons for each user group.
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
     * Get users of the project that are capable of making admin, patroller, or steward actions.
     * @OA\Tag(name="Project API")
     * @OA\Parameter(ref="#/components/parameters/Project")
     * @OA\Parameter(ref="#/components/parameters/Group")
     * @OA\Response(
     *     response=200,
     *     description="List of users and their groups.",
     *     @OA\JsonContent(
     *         @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
     *         @OA\Property(property="group", ref="#/components/parameters/Group/schema"),
     *         @OA\Property(property="users_and_groups",
     *             type="object",
     *             title="username",
     *             example={"Jimbo Wales":{"sysop", "steward"}}
     *         ),
     *         @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
     *     )
     * )
     * @OA\Response(response=404, ref="#/components/responses/404")
     * @OA\Response(response=503, ref="#/components/responses/503")
     * @OA\Response(response=504, ref="#/components/responses/504")
     * @codeCoverageIgnore
     */
    #[Route(
        "/api/project/{group}_groups/{project}",
        name: "ProjectApiAdminsGroups",
        requirements: ["group" => "admin|patroller|steward"],
        defaults: ["group" => "admin"],
        methods: ["GET"]
    )]
    public function adminsGroupsApiAction(AdminStatsRepository $adminStatsRepo): JsonResponse
    {
        $this->recordApiUsage('project/admin_groups');

        $this->setUpAdminStats($adminStatsRepo);

        unset($this->params['actions']);
        unset($this->params['start']);
        unset($this->params['end']);

        return $this->getFormattedApiResponse([
            'users_and_groups' => $this->adminStats->getUsersAndGroups(),
        ]);
    }

    /**
     * Get counts of logged actions by admins, patrollers, or stewards.
     * @OA\Tag(name="Project API")
     * @OA\Parameter(ref="#/components/parameters/Project")
     * @OA\Parameter(ref="#/components/parameters/Group")
     * @OA\Parameter(ref="#/components/parameters/Start")
     * @OA\Parameter(ref="#/components/parameters/End")
     * @OA\Parameter(ref="#/components/parameters/Actions")
     * @OA\Response(
     *     response=200,
     *     description="List of users and counts of their logged actions.",
     *     @OA\JsonContent(
     *         @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
     *         @OA\Property(property="group", ref="#/components/parameters/Group/schema"),
     *         @OA\Property(property="start", ref="#/components/parameters/Start/schema"),
     *         @OA\Property(property="end", ref="#/components/parameters/End/schema"),
     *         @OA\Property(property="actions", ref="#/components/parameters/Actions/schema"),
     *         @OA\Property(property="users",
     *             type="object",
     *             example={"Jimbo Wales":{
     *                 "username": "Jimbo Wales",
     *                 "delete": 10,
     *                 "re-block": 15,
     *                 "re-protect": 5,
     *                 "total": 30,
     *                 "user-groups": {"sysop"}
     *             }}
     *         ),
     *         @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
     *     )
     * )
     * @OA\Response(response=404, ref="#/components/responses/404")
     * @OA\Response(response=503, ref="#/components/responses/503")
     * @OA\Response(response=504, ref="#/components/responses/504")
     * @codeCoverageIgnore
     */
    #[Route(
        "/api/project/{group}_stats/{project}/{start}/{end}",
        name: "ProjectApiAdminStats",
        requirements: [
            "start" => "|\d{4}-\d{2}-\d{2}",
            "end" => "|\d{4}-\d{2}-\d{2}",
            "group" => "admin|patroller|steward",
        ],
        defaults: [
            "start" => false,
            "end" => false,
            "group" => "admin",
        ],
        methods: ["GET"]
    )]
    public function adminStatsApiAction(AdminStatsRepository $adminStatsRepo): JsonResponse
    {
        $this->recordApiUsage('project/adminstats');

        $this->setUpAdminStats($adminStatsRepo);
        $this->adminStats->prepareStats();

        return $this->getFormattedApiResponse([
            'users' => $this->adminStats->getStats(),
        ]);
    }
}
