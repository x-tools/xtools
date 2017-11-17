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
class PagesController extends XtoolsController
{

    /**
     * Get the tool's shortname.
     * @return string
     * @codeCoverageIgnore
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
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $params = $this->parseQueryParams($request);

        // Redirect if at minimum project and username are given.
        if (isset($params['project']) && isset($params['username'])) {
            return $this->redirectToRoute('PagesResult', $params);
        }

        // Convert the given project (or default project) into a Project instance.
        $params['project'] = $this->getProjectFromQuery($params);

        // Otherwise fall through.
        return $this->render('pages/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-pages',
            'xtSubtitle' => 'tool-pages-desc',
            'xtPage' => 'pages',

            // Defaults that will get overriden if in $params.
            'namespace' => 0,
            'redirects' => 'noredirects',
        ], $params));
    }

    /**
     * Display the results.
     * @Route("/pages/{project}/{username}/{namespace}/{redirects}", name="PagesResult")
     * @param Request $request
     * @param string $namespace The ID of the namespace.
     * @param string $redirects Whether to follow redirects or not.
     * @return RedirectResponse|Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request, $namespace = '0', $redirects = 'noredirects')
    {
        $ret = $this->validateProjectAndUser($request, 'pages');
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($projectData, $user) = $ret;
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

        $hasPageAssessments = $projectData->hasPageAssessments();
        $pagesByNamespaceByDate = [];
        $pageTitles = [];
        $countsByNamespace = [];
        $total = 0;
        $redirectTotal = 0;
        $deletedTotal = 0;

        foreach ($result as $row) {
            $datetime = DateTime::createFromFormat('YmdHis', $row['rev_timestamp']);
            $datetimeKey = $datetime->format('YmdHi');
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
            'xtTitle' => $user->getUsername(),
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
