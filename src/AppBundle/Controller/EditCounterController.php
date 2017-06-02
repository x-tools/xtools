<?php

namespace AppBundle\Controller;

use AppBundle\Helper\ApiHelper;
use AppBundle\Helper\AutomatedEditsHelper;
use AppBundle\Helper\LabsHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarDumper\VarDumper;
use Xtools\EditCounter;
use Xtools\EditCounterRepository;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;

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
     * @param string|boolean $project
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
     * @Route("/ec", name="ec")
     * @Route("/ec", name="EditCounter")
     * @Route("/ec/", name="EditCounterSlash")
     * @Route("/ec/index.php", name="EditCounterIndexPhp")
     * @Route("/ec/{project}", name="EditCounterProject")
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
     */
    public function resultAction(Request $request, $project, $username)
    {
        $this->setUpEditCounter($project, $username);

        //$automatedEditsSummary = $automatedEditsHelper->getEditsSummary($user->getId());

        // Give it all to the template.
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

            // Automated edits.
            //'auto_edits' => $automatedEditsSummary,
        ]);
    }

    /**
     * @Route("/ec-generalstats/{project}/{username}", name="EditCounterGeneralStats")
     */
    public function generalStatsAction($project, $username)
    {
        $this->setUpEditCounter($project, $username);

//        $revisionCounts = $this->editCounterHelper->getRevisionCounts($user->getId($project));
//        $pageCounts = $this->editCounterHelper->getPageCounts($username, $revisionCounts['total']);
//        $logCounts = $this->editCounterHelper->getLogCounts($user->getId($project));
//        $automatedEditsSummary = $automatedEditsHelper->getEditsSummary($user->getId($project));
//        $topProjectsEditCounts = $this->editCounterHelper->getTopProjectsEditCounts($project->getUrl(), 
//            $user->getUsername());

        // Render view.
        $isSubRequest = $this->get('request_stack')->getParentRequest() !== null;
        return $this->render('editCounter/general_stats.html.twig', [
            'xtPage' => 'ec',
            'is_sub_request' => $isSubRequest,
            'is_labs' => $this->project->getRepository()->isLabs(),
            'user' => $this->user,
            'project' => $this->project,
            'ec' => $this->editCounter,

            // Revision counts.
//            'deleted_edits' => $revisionCounts['deleted'],
//            'total_edits' => $revisionCounts['total'],
//            'live_edits' => $revisionCounts['live'],
//            'first_rev' => $revisionCounts['first'],
//            'latest_rev' => $revisionCounts['last'],
//            'days' => $revisionCounts['days'],
//            'avg_per_day' => $revisionCounts['avg_per_day'],
//            'rev_24h' => $revisionCounts['24h'],
//            'rev_7d' => $revisionCounts['7d'],
//            'rev_30d' => $revisionCounts['30d'],
//            'rev_365d' => $revisionCounts['365d'],
//            'rev_small' => $revisionCounts['small'],
//            'rev_large' => $revisionCounts['large'],
//            'with_comments' => $revisionCounts['with_comments'],
//            'without_comments' => $revisionCounts['live'] - $revisionCounts['with_comments'],
//            'minor_edits' => $revisionCounts['minor_edits'],
//            'nonminor_edits' => $revisionCounts['live'] - $revisionCounts['minor_edits'],
//            'auto_edits_total' => array_sum($automatedEditsSummary),
//
//            // Page counts.
//            'uniquePages' => $pageCounts['unique'],
//            'pagesCreated' => $pageCounts['created'],
//            'pagesMoved' => $pageCounts['moved'],
//            'editsPerPage' => $pageCounts['edits_per_page'],
//
//            // Log counts (keys are 'log name'-'action').
//            'pagesThanked' => $logCounts['thanks-thank'],
//            'pagesApproved' => $logCounts['review-approve'], // Merged -a, -i, and -ia approvals.
//            'pagesPatrolled' => $logCounts['patrol-patrol'],
//            'usersBlocked' => $logCounts['block-block'],
//            'usersUnblocked' => $logCounts['block-unblock'],
//            'pagesProtected' => $logCounts['protect-protect'],
//            'pagesUnprotected' => $logCounts['protect-unprotect'],
//            'pagesDeleted' => $logCounts['delete-delete'],
//            'pagesDeletedRevision' => $logCounts['delete-revision'],
//            'pagesRestored' => $logCounts['delete-restore'],
//            'pagesImported' => $logCounts['import-import'],
//            'files_uploaded' => $logCounts['upload-upload'],
//            'files_modified' => $logCounts['upload-overwrite'],
//            'files_uploaded_commons' => $logCounts['files_uploaded_commons'],
//
//            // Other projects.
//            'top_projects_edit_counts' => $topProjectsEditCounts,
        ]);
    }

    /**
     * @Route("/ec-namespacetotals/{project}/{username}", name="EditCounterNamespaceTotals")
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
