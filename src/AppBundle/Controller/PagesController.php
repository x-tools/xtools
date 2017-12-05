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
use Xtools\Pages;

/**
 * This controller serves the Pages tool.
 */
class PagesController extends XtoolsController
{
    const RESULTS_PER_PAGE = 1000;

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
     * @Route("/pages/{project}/{username}/{namespace}/{redirects}/{offset}", name="PagesResult")
     * @param Request $request
     * @param string|int $namespace The ID of the namespace, or 'all' for all namespaces.
     * @param string $redirects Whether to follow redirects or not.
     * @param int $offset Which page of results to show, when the results are so large they are paginated.
     * @return RedirectResponse|Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request, $namespace = '0', $redirects = 'noredirects', $offset = 0)
    {
        $ret = $this->validateProjectAndUser($request, 'pages');
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($projectData, $user) = $ret;
        }

        $pages = new Pages(
            $projectData,
            $user,
            $namespace,
            $redirects,
            $offset
        );
        $pages->prepareData();

        // Assign the values and display the template
        return $this->render('pages/result.html.twig', [
            'xtPage' => 'pages',
            'xtTitle' => $user->getUsername(),
            'project' => $projectData,
            'user' => $user,
            'summaryColumns' => $this->getSummaryColumns($redirects),
            'pages' => $pages,
        ]);
    }

    /**
     * What columns to show in namespace totals table.
     * @param  string $redirects One of 'noredirects', 'onlyredirects' or blank for both.
     * @return string[]
     * @codeCoverageIgnore
     */
    protected function getSummaryColumns($redirects)
    {
        $summaryColumns = ['namespace'];
        if ($redirects == 'onlyredirects') {
            // Don't show redundant pages column if only getting data on redirects.
            $summaryColumns[] = 'redirects';
        } elseif ($redirects == 'noredirects') {
            // Don't show redundant redirects column if only getting data on non-redirects.
            $summaryColumns[] = 'pages';
        } else {
            // Order is important here.
            $summaryColumns[] = 'pages';
            $summaryColumns[] = 'redirects';
        }

        // Always show deleted as the last column.
        $summaryColumns[] = 'deleted';

        return $summaryColumns;
    }
}
