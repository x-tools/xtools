<?php
/**
 * This file contains the code that powers the AdminStats page of XTools.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Model\AdminStats;
use AppBundle\Repository\AdminStatsRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The AdminStatsController serves the search form and results page of the AdminStats tool.
 */
class AdminStatsController extends XtoolsController
{
    /** @var AdminStats The admin stats instance that does all the work. */
    protected $adminStats;

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
     * Method for rendering the AdminStats Main Form.
     * This method redirects if valid parameters are found, making it a valid form endpoint as well.
     * @Route(
     *     "/{group}stats", name="AdminStats",
     *     requirements={"group"="admin"},
     *     defaults={"group"="admin"}
     * )
     * @Route(
     *     "/{group}stats", name="PatrollerStats",
     *     requirements={"group"="patroller"},
     *     defaults={"group"="patroller"}
     * )
     * @Route(
     *     "/{group}stats", name="StewardStats",
     *     requirements={"group"="steward"},
     *     defaults={"group"="steward"}
     * )
     * @return Response
     */
    public function indexAction(): Response
    {
        $this->getAndSetRequestedActions();

        // Redirect if we have a project.
        if (isset($this->params['project'])) {
            // We want pretty URLs.
            $route = $this->generateUrl(ucfirst($this->params['group']).'StatsResult', $this->params);
            $url = str_replace('%7C', '|', $route);
            return $this->redirect($url);
        }

        $actionsConfig = $this->container->getParameter('admin_stats');
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
        ], $this->params, ['project' => $this->project]);

        $params['isAllActions'] = $params['actions'] === implode('|', $this->getActionNames($params['group']));

        // Otherwise render form.
        return $this->render('adminStats/index.html.twig', $params);
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
        unset($actionsConfig[$group]['permissions']);
        $actions = array_keys($actionsConfig[$group]);
        return $actions;
    }

    /**
     * Every action in this controller (other than 'index') calls this first.
     * If a response is returned, the calling action is expected to return it.
     * @return AdminStats
     * @codeCoverageIgnore
     */
    public function setUpAdminStats(): AdminStats
    {
        // $this->start and $this->end are already set by the parent XtoolsController, but here we want defaults,
        // so we run XtoolsController::getUTCFromDateParams() once more but with the $useDefaults flag set.
        [$this->start, $this->end] = $this->getUTCFromDateParams($this->start, $this->end, true);

        $adminStatsRepo = new AdminStatsRepository();
        $adminStatsRepo->setContainer($this->container);
        $this->adminStats = new AdminStats(
            $this->project,
            $this->start,
            $this->end,
            $this->params['group'] ?? 'admin',
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
     *     requirements={"start"="|\d{4}-\d{2}-\d{2}", "end"="|\d{4}-\d{2}-\d{2}", "group"="admin"},
     *     defaults={"start"=false, "end"=false, "group"="admin"}
     * )
     * @Route(
     *     "/{group}stats/{project}/{start}/{end}", name="PatrollerStatsResult",
     *     requirements={"start"="|\d{4}-\d{2}-\d{2}", "end"="|\d{4}-\d{2}-\d{2}", "group"="patroller"},
     *     defaults={"start"=false, "end"=false, "group"="patroller"}
     * )
     * @Route(
     *     "/{group}stats/{project}/{start}/{end}", name="StewardStatsResult",
     *     requirements={"start"="|\d{4}-\d{2}-\d{2}", "end"="|\d{4}-\d{2}-\d{2}", "group"="steward"},
     *     defaults={"start"=false, "end"=false, "group"="steward"}
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(): Response
    {
        $this->setUpAdminStats();

        $this->adminStats->prepareStats();

        return $this->getFormattedResponse('adminStats/result', [
            'xtPage' => lcfirst($this->params['group']).'Stats',
            'xtTitle' => $this->project->getDomain(),
            'as' => $this->adminStats,
        ]);
    }

    /************************ API endpoints ************************/

    /**
     * Get users of the project that are capable of making 'admin actions',
     * keyed by user name with a list of the relevant user groups as the values.
     * @Route("/api/project/admins_groups/{project}", name="ProjectApiAdminsGroups")
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function adminsGroupsApiAction(): JsonResponse
    {
        $this->recordApiUsage('project/admins_groups');

        $this->setUpAdminStats();

        return new JsonResponse(
            $this->adminStats->getAdminsAndGroups(false),
            Response::HTTP_OK
        );
    }

    /**
     * Get users of the project that are capable of making 'admin actions',
     * along with various stats about which actions they took. Time period is limited
     * to one month.
     * @Route(
     *     "/api/project/adminstats/{project}/{days}",
     *     name="ProjectApiAdminStats",
     *     requirements={"days"="\d+"},
     *     defaults={"days"=31}
     * )
     * @Route(
     *     "/api/project/admin_stats/{project}/{days}",
     *     name="ProjectApiAdminStatsUnderscored",
     *     requirements={"days"="\d+"},
     *     defaults={"days"=31}
     * )
     * @param int $days Number of days from present to grab data for. Maximum 31.
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function adminStatsApiAction(int $days = 31): JsonResponse
    {
        $this->recordApiUsage('project/adminstats');

        $this->setUpAdminStats();

        // Maximum 31 days.
        $days = min((int) $days, 31);
        $start = date('Y-m-d', strtotime("-$days days"));
        $end = date('Y-m-d');

        $this->adminStats->prepareStats(false);

        return $this->getFormattedApiResponse([
            'start' => $start,
            'end' => $end,
            'users' => $this->adminStats->getStats(false),
        ]);
    }
}
