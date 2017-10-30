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
class AutomatedEditsController extends XtoolsController
{

    /**
     * Get the tool's shortname.
     * @return string
     * @codeCoverageIgnore
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
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $params = $this->parseQueryParams($request);

        // Redirect if at minimum project and username are provided.
        if (isset($params['project']) && isset($params['username'])) {
            return $this->redirectToRoute('autoeditsResult', $params);
        }

        // Convert the given project (or default project) into a Project instance.
        $params['project'] = $this->getProjectFromQuery($params);

        return $this->render('autoEdits/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-autoedits',
            'xtSubtitle' => 'tool-autoedits-desc',
            'xtPage' => 'autoedits',

            // Defaults that will get overriden if in $params.
            'namespace' => 0,
            'start' => '',
            'end' => '',
        ], $params));
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
     * @param Request $request The HTTP request.
     * @param int|string $namespace
     * @param null|string $start
     * @param null|string $end
     * @return RedirectResponse|Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request, $namespace = 0, $start = null, $end = null)
    {
        // Will redirect back to index if the user has too high of an edit count.
        $ret = $this->validateProjectAndUser($request, 'autoedits');
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($projectData, $user) = $ret;
        }

        // 'false' means the dates are optional and returned as 'false' if empty.
        list($start, $end) = $this->getUTCFromDateParams($start, $end, false);

        // We'll want to conditionally show some things in the view if there is a start date.
        $hasStartDate = $start > 0;

        // Format dates as needed by User model, if the date is present.
        if ($start !== false) {
            $start = date('Y-m-d', $start);
        }
        if ($end !== false) {
            $end = date('Y-m-d', $end);
        }

        // Normalize default namespace.
        if ($namespace == '') {
            $namespace = 0;
        }

        $editCount = $user->countEdits($projectData, $namespace, $start, $end);

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
            'hasStartDate' => $hasStartDate,
            'start' => $start,
            'end' => $end,
            'namespace' => $namespace,
        ];

        // Render the view with all variables set.
        return $this->render('autoEdits/result.html.twig', $ret);
    }
}
