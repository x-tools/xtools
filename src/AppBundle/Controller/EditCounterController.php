<?php
/**
 * This file contains only the EditCounterController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xtools\EditCounter;
use Xtools\EditCounterRepository;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;

/**
 * Class EditCounterController
 */
class EditCounterController extends Controller
{

    /** @var User */
    protected $user;
    
    /** @var Project */
    protected $project;
    
    /** @var EditCounter */
    protected $editCounter;

    /**
     * Every action in this controller (other than 'index') calls this first.
     * @param string|bool $project The project name.
     * @param string|bool $username The username.
     */
    protected function setUpEditCounter($project = false, $username = false)
    {
        // Make sure EditCounter is enabled.
        $this->get('app.labs_helper')->checkEnabled("ec");

        $this->project = ProjectRepository::getProject($project, $this->container);
        $this->user = UserRepository::getUser($username, $this->container);

        // Get an edit-counter.
        $editCounterRepo = new EditCounterRepository();
        $editCounterRepo->setContainer($this->container);
        $this->editCounter = new EditCounter($this->project, $this->user);
        $this->editCounter->setRepository($editCounterRepo);

        // Don't continue if the user doesn't exist.
        if (!$this->user->existsOnProject($this->project)) {
            $this->container->getSession()->getFlashBag()->set('notice', 'user-not-found');
        }
    }

    /**
     * The initial GET request that displays the search form.
     *
     * @Route("/ec", name="ec")
     * @Route("/ec", name="EditCounter")
     * @Route("/ec/", name="EditCounterSlash")
     * @Route("/ec/index.php", name="EditCounterIndexPhp")
     * @Route("/ec/{project}", name="EditCounterProject")
     *
     * @param Request $request
     * @param string|null $project
     * @return RedirectResponse|Response
     */
    public function indexAction(Request $request, $project = null)
    {
        $queryProject = $request->query->get('project');
        $username = $request->query->get('username');

        if (($project || $queryProject) && $username) {
            $routeParams = [ 'project'=>($project ?: $queryProject), 'username' => $username ];
            return $this->redirectToRoute("EditCounterResult", $routeParams);
        } elseif (!$project && $queryProject) {
            return $this->redirectToRoute("EditCounterProject", [ 'project'=>$queryProject ]);
        }

        $project = ProjectRepository::getProject($queryProject, $this->container);
        if (!$project->exists()) {
            $project = ProjectRepository::getDefaultProject($this->container);
        }

        // Otherwise fall through.
        return $this->render('editCounter/index.html.twig', [
            "xtPageTitle" => "tool-ec",
            "xtSubtitle" => "tool-ec-desc",
            'xtPage' => "ec",
            'project' => $project,
        ]);
    }

    /**
     * @Route("/ec/{project}/{username}", name="EditCounterResult")
     * @param Request $request
     * @param string $project
     * @param string $username
     * @return Response
     */
    public function resultAction(Request $request, $project, $username)
    {
        $this->setUpEditCounter($project, $username);
        $isSubRequest = $this->container->get('request_stack')->getParentRequest() !== null;
        return $this->render('editCounter/result.html.twig', [
            'xtTitle' => $username,
            'xtPage' => 'ec',
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            'is_labs' => $this->project->getRepository()->isLabs(),
            'is_sub_request' => $isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ]);
    }

    /**
     * @Route("/ec-generalstats/{project}/{username}", name="EditCounterGeneralStats")
     * @param string $project
     * @param string $username
     * @return Response
     */
    public function generalStatsAction($project, $username)
    {
        $this->setUpEditCounter($project, $username);
        $isSubRequest = $this->get('request_stack')->getParentRequest() !== null;
        return $this->render('editCounter/general_stats.html.twig', [
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'is_labs' => $this->project->getRepository()->isLabs(),
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ]);
    }

    /**
     * @Route("/ec-namespacetotals/{project}/{username}", name="EditCounterNamespaceTotals")
     * @param string $project
     * @param string $username
     * @return Response
     */
    public function namespaceTotalsAction($project, $username)
    {
        $this->setUpEditCounter($project, $username);
        $isSubRequest = $this->get('request_stack')->getParentRequest() !== null;
        return $this->render('editCounter/namespace_totals.html.twig', [
            'xtTitle' => 'namespacetotals',
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'is_labs' => $this->project->getRepository()->isLabs(),
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ]);
    }

    /**
     * @Route("/ec-timecard/{project}/{username}", name="EditCounterTimeCard")
     * @param string $project
     * @param string $username
     * @return Response
     */
    public function timecardAction($project, $username)
    {
        $this->setUpEditCounter($project, $username);
        $isSubRequest = $this->get('request_stack')->getParentRequest() !== null;
        //$datasets = $this->editCounterHelper->getTimeCard($username);
        return $this->render('editCounter/timecard.html.twig', [
            'xtTitle' => 'tool-ec',
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            //'datasets' => $datasets,
            'is_labs' => $this->project->getRepository()->isLabs(),
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ]);
    }

    /**
     * @Route("/ec-yearcounts/{project}/{username}", name="EditCounterYearCounts")
     * @param string $project
     * @param string $username
     * @return Response
     */
    public function yearcountsAction($project, $username)
    {
        $this->setUpEditCounter($project, $username);
        $isSubRequest = $this->container->get('request_stack')->getParentRequest() !== null;
        //$yearcounts = $this->editCounterHelper->getYearCounts($username);
        return $this->render('editCounter/yearcounts.html.twig', [
            'xtTitle' => 'tool-ec',
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            //'namespaces' => $this->apiHelper->namespaces($project),
            //'yearcounts' => $yearcounts,
            'is_labs' => $this->project->getRepository()->isLabs(),
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ]);
    }

    /**
     * @Route("/ec-monthcounts/{project}/{username}", name="EditCounterMonthCounts")
     * @param string $project
     * @param string $username
     * @return Response
     */
    public function monthcountsAction($project, $username)
    {
        $this->setUpEditCounter($project, $username);
        $isSubRequest = $this->container->get('request_stack')->getParentRequest() !== null;
        return $this->render('editCounter/monthcounts.html.twig', [
            'xtTitle' => 'tool-ec',
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'is_labs' => $this->project->getRepository()->isLabs(),
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ]);
    }

    /**
     * @Route("/ec-latestglobal/{project}/{username}", name="EditCounterLatestGlobal")
     * @param string $project
     * @param string $username
     * @return Response
     */
    public function latestglobalAction($project, $username)
    {
        $this->setUpEditCounter($project, $username);
        $isSubRequest = $this->container->get('request_stack')->getParentRequest() !== null;
        return $this->render('editCounter/latest_global.html.twig', [
            'xtTitle' => 'tool_ec',
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ]);
    }
}
