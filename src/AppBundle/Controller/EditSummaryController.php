<?php
/**
 * This file contains only the SimpleEditCounterController class.
 */

namespace AppBundle\Controller;

use Doctrine\DBAL\Connection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Xtools\EditSummary;
use Xtools\EditSummaryRepository;

/**
 * This controller handles the Simple Edit Counter tool.
 */
class EditSummaryController extends XtoolsController
{
    /**
     * Get the tool's shortname.
     * @return string
     * @codeCoverageIgnore
     */
    public function getToolShortname()
    {
        return 'es';
    }

    /**
     * The Edit Summary search form.
     * @Route("/editsummary",           name="es")
     * @Route("/editsummary",           name="EditSummary")
     * @Route("/editsummary/",          name="EditSummarySlash")
     * @Route("/editsummary/index.php", name="EditSummaryIndexPhp")
     * @Route("/editsummary/{project}", name="EditSummaryProject")
     * @return Response
     */
    public function indexAction()
    {
        // If we've got a project, user, and namespace, redirect to results.
        if (isset($this->params['project']) && isset($this->params['username'])) {
            return $this->redirectToRoute('EditSummaryResult', $this->params);
        }

        // Convert the given project (or default project) into a Project instance.
        $this->params['project'] = $this->getProjectFromQuery($this->params);

        // Show the form.
        return $this->render('editSummary/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-es',
            'xtSubtitle' => 'tool-es-desc',
            'xtPage' => 'es',

            // Defaults that will get overriden if in $this->params.
            'namespace' => 0,
        ], $this->params));
    }

    /**
     * Display the Edit Summary results
     * @Route("/editsummary/{project}/{username}/{namespace}", name="EditSummaryResult")
     * @param string $namespace Namespace ID or 'all' for all namespaces.
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction($namespace = 0)
    {
        $ret = $this->validateProjectAndUser('es');
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

        $editSummary->prepareData();

        // Assign the values and display the template
        return $this->render(
            'editSummary/result.html.twig',
            [
                'xtPage' => 'es',
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
     * @param string $namespace Namespace ID or 'all' for all namespaces.
     * @return Response
     * @codeCoverageIgnore
     */
    public function editSummariesApiAction($namespace = 0)
    {
        $this->recordApiUsage('user/edit_summaries');

        $ret = $this->validateProjectAndUser();
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
