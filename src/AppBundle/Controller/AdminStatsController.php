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
     * @Route("/adminstats", name="AdminStats")
     * @Route("/adminstats/index.php", name="AdminStatsIndexPhp")
     * @return Response
     */
    public function indexAction(): Response
    {
        // Redirect if we have a project.
        if (isset($this->params['project'])) {
            return $this->redirectToRoute('AdminStatsResult', $this->params);
        }

        // Otherwise render form.
        return $this->render('adminStats/index.html.twig', array_merge([
            'xtPage' => 'AdminStats',
            'xtPageTitle' => 'tool-adminstats',
            'xtSubtitle' => 'tool-adminstats-desc',

            // Defaults that will get overridden if in $params.
            'start' => '',
            'end' => '',
        ], $this->params));
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
        $this->adminStats = new AdminStats($this->project, $this->start, $this->end);
        $this->adminStats->setRepository($adminStatsRepo);

        // For testing purposes.
        return $this->adminStats;
    }

    /**
     * Method for rendering the AdminStats results.
     * @Route(
     *     "/adminstats/{project}/{start}/{end}", name="AdminStatsResult",
     *     requirements={"start"="|\d{4}-\d{2}-\d{2}", "end"="|\d{4}-\d{2}-\d{2}"},
     *     defaults={"start"=false, "end"=false}
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(): Response
    {
        $this->setUpAdminStats();

        $this->adminStats->prepareStats();

        return $this->getFormattedResponse('adminStats/result', [
            'xtPage' => 'AdminStats',
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
