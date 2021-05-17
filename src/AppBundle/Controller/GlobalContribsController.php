<?php
declare(strict_types = 1);

namespace AppBundle\Controller;

use AppBundle\Model\GlobalContribs;
use AppBundle\Repository\GlobalContribsRepository;
use AppBundle\Repository\ProjectRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller serves the search form and results for the Global Contributions tool.
 * @codeCoverageIgnore
 */
class GlobalContribsController extends XtoolsController
{
    /**
     * Get the name of the tool's index route. This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'GlobalContribs';
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
        $project = ProjectRepository::getProject($this->i18n->getLang().'.wikipedia', $this->container);
        if (!$project->exists()) {
            $project = ProjectRepository::getProject(
                $this->container->getParameter('central_auth_project'),
                $this->container
            );
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
     * Display the latest global edits tool. First two routes are legacy.
     * @Route(
     *     "/ec-latestglobal-contributions/{project}/{username}",
     *     name="EditCounterLatestGlobalContribs",
     *     requirements={
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
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
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
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
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "namespace" = "|all|\d+",
     *         "start" = "|\d*|\d{4}-\d{2}-\d{2}",
     *         "end" = "|\d{4}-\d{2}-\d{2}",
     *         "offset"="|\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}",
     *     },
     *     defaults={
     *         "namespace"="all",
     *         "start"=false,
     *         "end"=false,
     *         "offset"=false,
     *     }
     * ),
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultsAction(): Response
    {
        $globalContribsRepo = new GlobalContribsRepository();
        $globalContribsRepo->setContainer($this->container);
        $globalContribs = new GlobalContribs($this->user, $this->namespace, $this->start, $this->end, $this->offset);
        $globalContribs->setRepository($globalContribsRepo);
        $defaultProject = ProjectRepository::getProject(
            $this->container->getParameter('central_auth_project'),
            $this->container
        );
        $defaultProject->getRepository()->setContainer($this->container);

        return $this->render('globalContribs/result.html.twig', [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'GlobalContribs',
            'is_sub_request' => $this->isSubRequest,
            'user' => $this->user,
            'project' => $defaultProject,
            'gc' => $globalContribs,
        ]);
    }
}
