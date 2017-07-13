<?php
/**
 * This file contains only the PagesController class.
 */

namespace AppBundle\Controller;

use DateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xtools\ProjectRepository;
use Xtools\UserRepository;

/**
 * This controller serves the Pages tool.
 */
class PagesController extends Controller
{

    /**
     * Get the tool's shortname.
     * @return string
     */
    public function getToolShortname()
    {
        return 'pages';
    }

    /**
     * Display the form.
     * @Route("/pages", name="pages")
     * @Route("/pages", name="Pages")
     * @Route("/pages/", name="PagesSlash")
     * @Route("/pages/index.php", name="PagesIndexPhp")
     * @Route("/pages/{project}", name="PagesProject")
     * @param string $project The project domain name.
     * @return Response
     */
    public function indexAction($project = null)
    {
        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $projectQuery = $request->query->get('project');
        $username = $request->query->get('username', $request->query->get('user'));
        $namespace = $request->query->get('namespace');
        $redirects = $request->query->get('redirects');

        // if values for required parameters are present, redirect to result action
        if ($projectQuery != "" && $username != "" && $namespace != "" && $redirects != "") {
            return $this->redirectToRoute("PagesResult", [
                'project'=>$projectQuery,
                'username' => $username,
                'namespace'=>$namespace,
                'redirects'=>$redirects,
            ]);
        } elseif ($projectQuery != "" && $username != "" && $namespace != "") {
            return $this->redirectToRoute("PagesResult", [
                'project'=>$projectQuery,
                'username' => $username,
                'namespace'=>$namespace,
            ]);
        } elseif ($projectQuery != "" && $username != "" && $redirects != "") {
            return $this->redirectToRoute("PagesResult", [
                'project'=>$projectQuery,
                'username' => $username,
                'redirects'=>$redirects,
            ]);
        } elseif ($projectQuery != "" && $username != "") {
            return $this->redirectToRoute("PagesResult", [
                'project'=>$projectQuery,
                'username' => $username,
            ]);
        } elseif ($projectQuery != "") {
            return $this->redirectToRoute("PagesProject", [ 'project'=>$projectQuery ]);
        }

        // set default wiki so we can populate the namespace selector
        if (!$project) {
            $project = $this->getParameter('default_project');
        }

        $projectData = ProjectRepository::getProject($project, $this->container);

        $namespaces = null;

        if ($projectData->exists()) {
            $namespaces = $projectData->getNamespaces();
        }

        // Otherwise fall through.
        return $this->render('pages/index.html.twig', [
            'xtPageTitle' => 'tool-pages',
            'xtSubtitle' => 'tool-pages-desc',
            'xtPage' => 'pages',
            'project' => $projectData,
            'namespaces' => $namespaces,
        ]);
    }

    /**
     * Display the results.
     * @Route("/pages/{project}/{username}/{namespace}/{redirects}", name="PagesResult")
     * @param string $project The project domain name.
     * @param string $username The username.
     * @param string $namespace The ID of the namespace.
     * @param string $redirects Whether to follow redirects or not.
     * @return RedirectResponse|Response
     */
    public function resultAction($project, $username, $namespace = '0', $redirects = 'noredirects')
    {
        $user = UserRepository::getUser($username, $this->container);
        $username = $user->getUsername(); // use normalized user name

        $projectData = ProjectRepository::getProject($project, $this->container);
        $projectRepo = $projectData->getRepository();

        // If the project exists, actually populate the values
        if (!$projectData->exists()) {
            $this->addFlash('notice', ['invalid-project', $project]);
            return $this->redirectToRoute('pages');
        }
        if (!$user->existsOnProject($projectData)) {
            $this->addFlash('notice', ['user-not-found']);
            return $this->redirectToRoute('pages');
        }

        // what columns to show in namespace totals table
        $summaryColumns = ['namespace'];
        if ($redirects == 'onlyredirects') {
            // don't show redundant pages column if only getting data on redirects
            $summaryColumns[] = 'redirects';
        } elseif ($redirects == 'noredirects') {
            // don't show redundant redirects column if only getting data on non-redirects
            $summaryColumns[] = 'pages';
        } else {
            // order is important here
            $summaryColumns[] = 'pages';
            $summaryColumns[] = 'redirects';
        }
        $summaryColumns[] = 'deleted'; // always show deleted column

        $result = $user->getRepository()->getPagesCreated($projectData, $user, $namespace, $redirects);

        $hasPageAssessments = $projectRepo->isLabs() && $projectData->hasPageAssessments();
        $pagesByNamespaceByDate = [];
        $pageTitles = [];
        $countsByNamespace = [];
        $total = 0;
        $redirectTotal = 0;
        $deletedTotal = 0;

        foreach ($result as $row) {
            $datetime = DateTime::createFromFormat('YmdHis', $row['rev_timestamp']);
            $datetimeKey = $datetime->format('Ymdhi');
            $datetimeHuman = $datetime->format('Y-m-d H:i');

            $pageData = array_merge($row, [
                'raw_time' => $row['rev_timestamp'],
                'human_time' => $datetimeHuman,
                'page_title' => str_replace('_', ' ', $row['page_title'])
            ]);

            if ($hasPageAssessments) {
                $pageData['badge'] = $projectData->getAssessmentBadgeURL($pageData['pa_class']);
            }

            $pagesByNamespaceByDate[$row['namespace']][$datetimeKey][] = $pageData;

            $pageTitles[] = $row['page_title'];

            // Totals
            if (isset($countsByNamespace[$row['namespace']]['total'])) {
                $countsByNamespace[$row['namespace']]['total']++;
            } else {
                $countsByNamespace[$row['namespace']]['total'] = 1;
                $countsByNamespace[$row['namespace']]['redirect'] = 0;
                $countsByNamespace[$row['namespace']]['deleted'] = 0;
            }
            $total++;

            if ($row['page_is_redirect']) {
                $redirectTotal++;
                // Redirects
                if (isset($countsByNamespace[$row['namespace']]['redirect'])) {
                    $countsByNamespace[$row['namespace']]['redirect']++;
                } else {
                    $countsByNamespace[$row['namespace']]['redirect'] = 1;
                }
            }

            if ($row['type'] === 'arc') {
                $deletedTotal++;
                // Deleted
                if (isset($countsByNamespace[$row['namespace']]['deleted'])) {
                    $countsByNamespace[$row['namespace']]['deleted']++;
                } else {
                    $countsByNamespace[$row['namespace']]['deleted'] = 1;
                }
            }
        }

        if ($total < 1) {
            $this->addFlash('notice', [ 'no-result', $username ]);
            return $this->redirectToRoute('PagesProject', [ 'project' => $project ]);
        }

        ksort($pagesByNamespaceByDate);
        ksort($countsByNamespace);

        foreach (array_keys($pagesByNamespaceByDate) as $key) {
            krsort($pagesByNamespaceByDate[$key]);
        }

        // Retrieve the namespaces
        $namespaces = $projectData->getNamespaces();

        // Assign the values and display the template
        return $this->render('pages/result.html.twig', [
            'xtPage' => 'pages',
            'xtTitle' => $username,
            'project' => $projectData,
            'user' => $user,
            'namespace' => $namespace,
            'redirect' => $redirects,
            'summaryColumns' => $summaryColumns,
            'namespaces' => $namespaces,
            'pages' => $pagesByNamespaceByDate,
            'count' => $countsByNamespace,
            'total' => $total,
            'redirectTotal' => $redirectTotal,
            'deletedTotal' => $deletedTotal,
            'hasPageAssessments' => $hasPageAssessments,
        ]);
    }
}
