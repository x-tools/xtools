<?php
/**
 * This file contains only the AutomatedEditsController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;

/**
 * This controller serves the AutomatedEdits tool.
 */
class AutomatedEditsController extends Controller
{

    /**
     * Get the tool's shortname.
     * @return string
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
     * @param string $project The project name.
     * @return Response
     */
    public function indexAction(Request $request, $project = null)
    {
        // Pull the values out of the query string. These values default to empty strings.
        $projectName = $request->query->get('project') ?: $project;
        $username = $request->query->get('username', $request->query->get('user'));
        $namespace = $request->query->get('namespace');
        $startDate = $request->query->get('start');
        $endDate = $request->query->get('end');

        // Legacy XTools.
        $begin = $request->query->get('begin');
        if (empty($startDate) && isset($begin)) {
            $startDate = $begin;
        }
        if (empty($namespace)) {
            $namespace = '0';
        }

        $redirectParams = [
            'project' => $projectName,
            'username' => $username,
        ];

        // Redirect if at minimum project and username are provided.
        if ($project != '' && $username != '') {
            return $this->redirectToRoute('autoeditsResult', [
                'project' => $projectName,
                'username' => $username,
                'namespace' => $namespace,
                'start' => $startDate,
                'end' => $endDate,
            ]);
        }

        // Set default project so we can populate the namespace selector.
        if (!$projectName) {
            $projectName = $this->container->getParameter('default_project');
        }
        $project = ProjectRepository::getProject($projectName, $this->container);

        return $this->render('autoEdits/index.html.twig', [
            'xtPageTitle' => 'tool-autoedits',
            'xtSubtitle' => 'tool-autoedits-desc',
            'xtPage' => 'autoedits',
            'project' => $project,
            'namespace' => (int) $namespace,
            'start' => $startDate,
            'end' => $endDate,
        ]);
    }

    /**
     * Display the results.
     * @Route(
     *     "/autoedits/{project}/{username}/{namespace}/{start}/{end}", name="autoeditsResult",
     *     requirements={
     *         "start" = "|\d{4}-\d{2}-\d{2}",
     *         "end" = "|\d{4}-\d{2}-\d{2}",
     *         "namespace" = "|all|\d"
     *     }
     * )
     * @param string $project
     * @param string $username
     * @param int|string [$namespace]
     * @param null|string [$start]
     * @param null|string [$end]
     * @return RedirectResponse|Response
     */
    public function resultAction($project, $username, $namespace = 0, $start = null, $end = null)
    {
        // Pull information about the project
        $projectData = ProjectRepository::getProject($project, $this->container);

        if (!$projectData->exists()) {
            $this->addFlash('notice', ['invalid-project', $project]);
            return $this->redirectToRoute('autoedits');
        }

        // Validating the dates. If the dates are invalid, we'll redirect
        // to the project and username view.
        $invalidDates = (
            ($start != '' && strtotime($start) === false) ||
            ($end != '' && strtotime($end) === false)
        );
        if ($invalidDates) {
            // Make sure to add the flash notice first.
            $this->addFlash('warning', ['invalid-date']);

            // Then redirect us!
            return $this->redirectToRoute(
                'autoeditsResult',
                [
                    'project' => $project,
                    'username' => $username,
                ]
            );
        }

        // Normalize default namespace.
        if ($namespace == '') {
            $namespace = 0;
        }

        $user = UserRepository::getUser($username, $this->container);

        $editCount = $user->countEdits($projectData, $namespace, $start, $end);

        // Inform user if no revisions found.
        if ($editCount === 0) {
            $this->addFlash('danger', ['no-contribs']);
            return $this->redirectToRoute('autoedits');
        }

        // Get individual counts of how many times each tool was used.
        // This also includes a wikilink to the tool.
        $toolCounts = $user->getAutomatedCounts($projectData, $namespace, $start, $end);
        $toolsTotal = array_reduce($toolCounts, function ($a, $b) {
            return $a + $b['count'];
        });

        // Query to get combined (semi)automated using for all edits
        //   as some automated edits overlap.
        $autoCount = $user->countAutomatedEdits($projectData, $namespace, $start, $end);

        $ret = [
            'xtPage' => 'autoedits',
            'user' => $user,
            'project' => $projectData,
            'toolCounts' => $toolCounts,
            'toolsTotal' => $toolsTotal,
            'autoCount' => $autoCount,
            'editCount' => $editCount,
            'autoPct' => $editCount ? ($autoCount / $editCount) * 100 : 0,
            'start' => $start ?: '',
            'end' => $end ?: '',
            'namespace' => $namespace,
        ];

        // Render the view with all variables set.
        return $this->render('autoEdits/result.html.twig', $ret);
    }
}
