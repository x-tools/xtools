<?php

declare(strict_types = 1);

namespace App\Controller;

use App\Model\Edit;
use App\Model\GlobalContribs;
use App\Repository\EditRepository;
use App\Repository\GlobalContribsRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller serves the search form and results for the Global Contributions tool.
 */
class GlobalContribsController extends XtoolsController
{

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'GlobalContribs';
    }

    /**
     * GlobalContribs can be very slow, especially for wide IP ranges, so limit to max 500 results.
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function maxLimit(): int
    {
        return 500;
    }

    /**
     * The search form.
     * @Route("/globalcontribs", name="GlobalContribs")
     * @Route("/ec-latestglobal", name="EditCounterLatestGlobalIndex")
     * @Route("/ec-latestglobal-contributions", name="EditCounterLatestGlobalContribsIndex")
     * @Route("/ec-latestglobaledits", name="EditCounterLatestGlobalEditsIndex")
     * @return Response
     */
    public function indexAction(): Response
    {
        // Redirect if username is given.
        if (isset($this->params['username'])) {
            return $this->redirectToRoute('GlobalContribsResult', $this->params);
        }

        // FIXME: Nasty hack until T226072 is resolved.
        $project = $this->projectRepo->getProject($this->i18n->getLang().'.wikipedia');
        if (!$project->exists()) {
            $project = $this->projectRepo->getProject($this->getParameter('central_auth_project'));
        }

        return $this->render('globalContribs/index.html.twig', array_merge([
            'xtPage' => 'GlobalContribs',
            'xtPageTitle' => 'tool-globalcontribs',
            'xtSubtitle' => 'tool-globalcontribs-desc',
            'project' => $project,

            // Defaults that will get overridden if in $this->params.
            'namespace' => 'all',
            'start' => '',
            'end' => '',
        ], $this->params));
    }

    /**
     * @param GlobalContribsRepository $globalContribsRepo
     * @param EditRepository $editRepo
     * @return GlobalContribs
     * @codeCoverageIgnore
     */
    public function getGlobalContribs(
        GlobalContribsRepository $globalContribsRepo,
        EditRepository $editRepo
    ): GlobalContribs {
        return new GlobalContribs(
            $globalContribsRepo,
            $this->pageRepo,
            $this->userRepo,
            $editRepo,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end,
            $this->offset,
            $this->limit
        );
    }

    /**
     * Display the latest global edits tool. First two routes are legacy.
     * @Route(
     *     "/ec-latestglobal-contributions/{project}/{username}",
     *     name="EditCounterLatestGlobalContribs",
     *     requirements={
     *         "username"="(ipr-.+\/\d+[^\/])|([^\/]+)",
     *     },
     *     defaults={
     *         "project"="",
     *         "namespace"="all"
     *     }
     * )
     * @Route(
     *     "/ec-latestglobal/{project}/{username}",
     *     name="EditCounterLatestGlobal",
     *     requirements={
     *         "username"="(ipr-.+\/\d+[^\/])|([^\/]+)",
     *     },
     *     defaults={
     *         "project"="",
     *         "namespace"="all"
     *     }
     * ),
     * @Route(
     *     "/globalcontribs/{username}/{namespace}/{start}/{end}/{offset}",
     *     name="GlobalContribsResult",
     *     requirements={
     *         "username"="(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "namespace"="|all|\d+",
     *         "start"="|\d*|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *         "offset"="|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}",
     *     },
     *     defaults={
     *         "namespace"="all",
     *         "start"=false,
     *         "end"=false,
     *         "offset"=false,
     *     }
     * ),
     * @param GlobalContribsRepository $globalContribsRepo
     * @param EditRepository $editRepo
     * @param string $centralAuthProject
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultsAction(
        GlobalContribsRepository $globalContribsRepo,
        EditRepository $editRepo,
        string $centralAuthProject
    ): Response {
        $globalContribs = $this->getGlobalContribs($globalContribsRepo, $editRepo);
        $defaultProject = $this->projectRepo->getProject($centralAuthProject);

        return $this->render('globalContribs/result.html.twig', [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'GlobalContribs',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $defaultProject,
            'gc' => $globalContribs,
        ]);
    }

    /************************ API endpoints ************************/

    /**
     * Get global edits made by a user, IP or IP range.
     * @Route(
     *     "/api/user/globalcontribs/{username}/{namespace}/{start}/{end}/{offset}",
     *     name="UserApiGlobalContribs",
     *     requirements={
     *         "username"="(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "namespace"="|all|\d+",
     *         "start"="|\d*|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}",
     *         "offset"="|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}",
     *     },
     *     defaults={
     *         "namespace"="all",
     *         "start"=false,
     *         "end"=false,
     *         "offset"=false,
     *         "limit"=50,
     *     },
     * )
     * @param GlobalContribsRepository $globalContribsRepo
     * @param EditRepository $editRepo
     * @param string $centralAuthProject
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function resultsApiAction(
        GlobalContribsRepository $globalContribsRepo,
        EditRepository $editRepo,
        string $centralAuthProject
    ): JsonResponse {
        $this->recordApiUsage('user/globalcontribs');

        $globalContribs = $this->getGlobalContribs($globalContribsRepo, $editRepo);
        $defaultProject = $this->projectRepo->getProject($centralAuthProject);
        $this->project = $defaultProject;

        $results = $globalContribs->globalEdits();
        $results = array_map(function (Edit $edit) {
            return $edit->getForJson(true, true);
        }, array_values($results));
        $results = $this->addFullPageTitlesAndContinue('globalcontribs', [], $results);

        return $this->getFormattedApiResponse($results);
    }
}
