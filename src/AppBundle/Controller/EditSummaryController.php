<?php
/**
 * This file contains only the SimpleEditCounterController class.
 */

namespace AppBundle\Controller;

use AppBundle\Helper\I18nHelper;
use Doctrine\DBAL\Connection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xtools\EditSummary;
use Xtools\EditSummaryRepository;

/**
 * This controller handles the Simple Edit Counter tool.
 */
class EditSummaryController extends XtoolsController
{
    /**
     * Get the name of the tool's index route.
     * This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute()
    {
        return 'EditSummary';
    }

    /**
     * The Edit Summary search form.
     *
     * @param Request $request The HTTP request.
     *
     * @Route("/editsummary",           name="EditSummary")
     * @Route("/editsummary/",          name="EditSummarySlash")
     * @Route("/editsummary/index.php", name="EditSummaryIndexPhp")
     * @Route("/editsummary/{project}", name="EditSummaryProject")
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $params = $this->parseQueryParams($request);

        // If we've got a project, user, and namespace, redirect to results.
        if (isset($params['project']) && isset($params['username'])) {
            return $this->redirectToRoute('EditSummaryResult', $params);
        }

        // Convert the given project (or default project) into a Project instance.
        $params['project'] = $this->getProjectFromQuery($params);

        // Show the form.
        return $this->render('editSummary/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-editsummary',
            'xtSubtitle' => 'tool-editsummary-desc',
            'xtPage' => 'editsummary',

            // Defaults that will get overriden if in $params.
            'namespace' => 0,
        ], $params));
    }

    /**
     * Display the Edit Summary results
     * @Route("/editsummary/{project}/{username}/{namespace}", name="EditSummaryResult")
     * @param Request $request The HTTP request.
     * @param string $namespace Namespace ID or 'all' for all namespaces.
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request, $namespace = 0)
    {
        $ret = $this->validateProjectAndUser($request, 'es');
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($project, $user) = $ret;
        }

        // Instantiate an EditSummary, treating the past 150 edits as 'recent'.
        $editSummary = new EditSummary($project, $user, $namespace, 150);
        $editSummaryRepo = new EditSummaryRepository();
        $editSummaryRepo->setContainer($this->container);
        $editSummary->setRepository($editSummaryRepo);
        $editSummary->setI18nHelper($this->container->get('app.i18n_helper'));

        $editSummary->prepareData();

        // Assign the values and display the template
        return $this->render(
            'editSummary/result.html.twig',
            [
                'xtPage' => 'editsummary',
                'xtTitle' => $user->getUsername(),
                'user' => $user,
                'project' => $project,
                'namespace' => $namespace,
                'es' => $editSummary,
            ]
        );
    }

    /************************ API endpoints ************************/

    /**
     * Get basic stats on the edit summary usage of a user.
     * @Route("/api/user/edit_summaries/{project}/{username}/{namespace}", name="UserApiEditSummaries")
     * @param Request $request The HTTP request.
     * @param string $namespace Namespace ID or 'all' for all namespaces.
     * @return Response
     * @codeCoverageIgnore
     */
    public function editSummariesApiAction(Request $request, $namespace = 0)
    {
        $this->recordApiUsage('user/edit_summaries');

        $ret = $this->validateProjectAndUser($request);
        if ($ret instanceof RedirectResponse) {
            // FIXME: needs to render as JSON, fetching the message from the FlashBag.
            return $ret;
        } else {
            list($project, $user) = $ret;
        }

        // Instantiate an EditSummary, treating the past 150 edits as 'recent'.
        $editSummary = new EditSummary($project, $user, $namespace, 150, $this->container);
        $editSummaryRepo = new EditSummaryRepository();
        $editSummaryRepo->setContainer($this->container);
        $editSummary->setRepository($editSummaryRepo);
        $editSummary->prepareData();

        return new JsonResponse(
            $editSummary->getData(),
            Response::HTTP_OK
        );
    }
}
