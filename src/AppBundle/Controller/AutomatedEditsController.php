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
     * @Route("/automatededits", name="autoeditsLong")
     * @Route("/autoedits/index.php", name="autoeditsIndexPhp")
     * @Route("/automatededits/index.php", name="autoeditsLongIndexPhp")
     * @param Request $request The HTTP request.
     * @return Response
     */
    public function indexAction(Request $request)
    {
        // Pull the values out of the query string. These values default to empty strings.
        $projectName = $request->query->get('project');
        $username = $request->query->get('username', $request->query->get('user'));
        $namespace = $request->query->get('namespace');
        $startDate = $request->query->get('start');
        $endDate = $request->query->get('end');

        // Redirect if the values are set.
        if ($projectName != '' && $username != '' && $namespace != '' && ($startDate != '' || $endDate != '')) {
            // Set start date to beginning of time if end date is provided
            // This is nasty, but necessary given URL structure
            if ($startDate === '') {
                $startDate = date('Y-m-d', 0);
            }

            return $this->redirectToRoute(
                'autoeditsResult',
                [
                    'project' => $projectName,
                    'username' => $username,
                    'namespace' => $namespace,
                    'start' => $startDate,
                    'end' => $endDate,
                ]
            );
        } elseif ($projectName != '' && $username != '' && $namespace != '') {
            return $this->redirectToRoute(
                'autoeditsResult',
                [
                    'project' => $projectName,
                    'username' => $username,
                    'namespace' => $namespace,
                ]
            );
        } elseif ($projectName != '' && $username != '') {
            return $this->redirectToRoute(
                'autoeditsResult',
                [
                    'project' => $projectName,
                    'username' => $username,
                ]
            );
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
        ]);
    }

    /**
     * Display the results.
     * @Route("/autoedits/{project}/{username}/{namespace}/{start}/{end}", name="autoeditsResult")
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
            (isset($start) && strtotime($start) === false) ||
            (isset($end) && strtotime($end) === false)
        );
        if ($invalidDates) {
            // Make sure to add the flash notice first.
            $this->addFlash('notice', ['invalid-date']);

            // Then redirect us!
            return $this->redirectToRoute(
                'autoeditsResult',
                [
                    'project' => $project,
                    'username' => $username,
                ]
            );
        }

        $user = UserRepository::getUser($username, $this->container);

        $editCount = $user->countEdits($projectData, $namespace, $start, $end);

        // Inform user if no revisions found.
        if ($editCount === 0) {
            $this->addFlash('notice', ['no-contribs']);
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
