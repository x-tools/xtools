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
use Xtools\Page;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;

/**
 * Class EditCounterController
 */
class EditCounterController extends Controller
{

    /** @var User The user being queried. */
    protected $user;

    /** @var Project The project being queried. */
    protected $project;

    /** @var EditCounter The edit-counter, that does all the work. */
    protected $editCounter;

    /**
     * Get the tool's shortname.
     * @return string
     */
    public function getToolShortname()
    {
        return 'ec';
    }

    /**
     * Every action in this controller (other than 'index') calls this first.
     * @param string|bool $project The project name.
     * @param string|bool $username The username.
     */
    protected function setUpEditCounter($project = false, $username = false)
    {
        $this->project = ProjectRepository::getProject($project, $this->container);
        $this->user = UserRepository::getUser($username, $this->container);

        // Get an edit-counter.
        $editCounterRepo = new EditCounterRepository();
        $editCounterRepo->setContainer($this->container);
        $this->editCounter = new EditCounter($this->project, $this->user);
        $this->editCounter->setRepository($editCounterRepo);

        // Don't continue if the user doesn't exist.
        if (!$this->user->existsOnProject($this->project)) {
            $this->addFlash('notice', 'user-not-found');
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
        $username = $request->query->get('username', $request->query->get('user'));

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
     * Display all results.
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
            'xtTitle' => $this->user->getUsername() . ' - ' . $this->project->getTitle(),
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
     * Display the general statistics section.
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
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'is_labs' => $this->project->getRepository()->isLabs(),
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ]);
    }

    /**
     * Display the namespace totals section.
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
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'is_labs' => $this->project->getRepository()->isLabs(),
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ]);
    }

    /**
     * Display the timecard section.
     * @Route("/ec-timecard/{project}/{username}", name="EditCounterTimecard")
     * @param string $project
     * @param string $username
     * @return Response
     */
    public function timecardAction($project, $username)
    {
        $this->setUpEditCounter($project, $username);
        $isSubRequest = $this->get('request_stack')->getParentRequest() !== null;
        $optedInPage = $this->project
            ->getRepository()
            ->getPage($this->project, $this->project->userOptInPage($this->user));
        return $this->render('editCounter/timecard.html.twig', [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'is_labs' => $this->project->getRepository()->isLabs(),
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
            'opted_in_page' => $optedInPage,
        ]);
    }

    /**
     * Display the year counts section.
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
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            //'yearcounts' => $yearcounts,
            'is_labs' => $this->project->getRepository()->isLabs(),
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ]);
    }

    /**
     * Display the month counts section.
     * @Route("/ec-monthcounts/{project}/{username}", name="EditCounterMonthCounts")
     * @param string $project
     * @param string $username
     * @return Response
     */
    public function monthcountsAction($project, $username)
    {
        $this->setUpEditCounter($project, $username);
        $isSubRequest = $this->container->get('request_stack')->getParentRequest() !== null;
        $optedInPage = $this->project
            ->getRepository()
            ->getPage($this->project, $this->project->userOptInPage($this->user));
        return $this->render('editCounter/monthcounts.html.twig', [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'is_labs' => $this->project->getRepository()->isLabs(),
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
            'opted_in_page' => $optedInPage,
        ]);
    }

    /**
     * Display the latest global edits section.
     * @Route("/ec-latestglobal/{project}/{username}", name="EditCounterLatestGlobal")
     * @param Request $request The HTTP request.
     * @param string $project The project name.
     * @param string $username The username.
     * @return Response
     */
    public function latestglobalAction(Request $request, $project, $username)
    {
        $this->setUpEditCounter($project, $username);
        $isSubRequest = $request->get('htmlonly')
                        || $this->container->get('request_stack')->getParentRequest() !== null;
        return $this->render('editCounter/latest_global.html.twig', [
            'xtTitle' => $this->user->getUsername(),
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,
        ]);
    }
}
