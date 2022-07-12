<?php
/**
 * This file contains only the AdminScoreController class.
 */

declare(strict_types=1);

namespace App\Controller;

use App\Model\AdminScore;
use App\Repository\AdminScoreRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
    public function getIndexRoute(): string
    {
        return 'AdminScore';
    }

    /**
     * Display the AdminScore search form.
     * @Route("/adminscore", name="AdminScore")
     * @Route("/adminscore/index.php", name="AdminScoreIndexPhp")
     * @Route("/scottywong tools/adminscore.php", name="AdminScoreLegacy")
     * @Route("/adminscore/{project}", name="AdminScoreProject")
     * @return Response
     */
    public function indexAction(): Response
    {
        // Redirect if we have a project and user.
        if (isset($this->params['project']) && isset($this->params['username'])) {
            return $this->redirectToRoute('AdminScoreResult', $this->params);
        }

        return $this->render('adminscore/index.html.twig', [
            'xtPage' => 'AdminScore',
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
    public function resultAction(): Response
    {
        $adminScoreRepo = new AdminScoreRepository();
        $adminScoreRepo->setContainer($this->container);
        $adminScore = new AdminScore($this->project, $this->user);
        $adminScore->setRepository($adminScoreRepo);

        return $this->getFormattedResponse('adminscore/result', [
            'xtPage' => 'AdminScore',
            'xtTitle' => $this->user->getUsername(),
            'as' => $adminScore,
        ]);
    }
}
