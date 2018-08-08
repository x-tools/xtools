<?php
/**
 * This file contains only the AdminScoreController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Xtools\AdminScore;
use Xtools\AdminScoreRepository;

/**
 * The AdminScoreController serves the search form and results page of the AdminScore tool.
 */
class AdminScoreController extends XtoolsController
{
    /**
     * Get the name of the tool's index route.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute()
    {
        return 'AdminScore';
    }

    /**
     * Display the AdminScore search form.
     * @Route("/adminscore", name="AdminScore")
     * @Route("/adminscore/", name="AdminScoreSlash")
     * @Route("/adminscore/index.php", name="AdminScoreIndexPhp")
     * @Route("/scottywong tools/adminscore.php", name="AdminScoreLegacy")
     * @Route("/adminscore/{project}", name="AdminScoreProject")
     * @return Response
     */
    public function indexAction()
    {
        // Redirect if we have a project and user.
        if (isset($this->params['project']) && isset($this->params['username'])) {
            return $this->redirectToRoute('AdminScoreResult', $this->params);
        }

        return $this->render('adminscore/index.html.twig', [
            'xtPage' => 'adminscore',
            'xtPageTitle' => 'tool-adminscore',
            'xtSubtitle' => 'tool-adminscore-desc',
            'project' => $this->project,
        ]);
    }

    /**
     * Display the AdminScore results.
     * @Route("/adminscore/{project}/{username}", name="AdminScoreResult")
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction()
    {
        $adminScoreRepo = new AdminScoreRepository();
        $adminScoreRepo->setContainer($this->container);
        $adminScore = new AdminScore($this->project, $this->user);
        $adminScore->setRepository($adminScoreRepo);

        return $this->getFormattedResponse('adminscore/result', [
            'xtPage' => 'adminscore',
            'xtTitle' => $this->user->getUsername(),
            'as' => $adminScore,
        ]);
    }
}
