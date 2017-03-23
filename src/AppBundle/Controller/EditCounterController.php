<?php

namespace AppBundle\Controller;

use AppBundle\Helper\ApiHelper;
use AppBundle\Helper\EditCounterHelper;
use AppBundle\Helper\LabsHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class EditCounterController extends Controller
{
    /**
     * @Route("/ec", name="ec")
     * @Route("/ec", name="EditCounter")
     * @Route("/ec/", name="EditCounterSlash")
     * @Route("/ec/index.php", name="EditCounterIndexPhp")
     * @Route("/ec/{project}", name="EditCounterProject")
     */
    public function indexAction(Request $request, $project = null)
    {
        $lh = $this->get("app.labs_helper");
        $lh->checkEnabled("ec");

        $queryProject = $request->query->get('project');
        $username = $request->query->get('user');

        if (($project || $queryProject) && $username) {
            $routeParams = [ 'project'=>($project ?: $queryProject), 'username' => $username ];
            return $this->redirectToRoute("EditCounterResult", $routeParams);
        } elseif (!$project && $queryProject) {
            return $this->redirectToRoute("EditCounterProject", [ 'project'=>$queryProject ]);
        }

        // Otherwise fall through.
        return $this->render('editCounter/index.html.twig', [
            "xtPageTitle" => "tool_ec",
            "xtSubtitle" => "tool_ec_desc",
            'xtPage' => "ec",
            'xtTitle' => "tool_ec",
            'project' => $project,
        ]);
    }

    /**
     * @Route("/ec/{project}/{username}", name="EditCounterResult")
     */
    public function resultAction($project, $username)
    {
        /** @var LabsHelper $lh */
        $lh = $this->get("app.labs_helper");
        /** @var EditCounterHelper $ec */
        $ec = $this->get("app.editcounter_helper");
        /** @var ApiHelper $api */
        $api = $this->get("app.api_helper");

        // Check, clean, and get inputs.
        $lh->checkEnabled("ec");
        $username = ucfirst($username);
        $dbValues = $lh->databasePrepare($project);
        $dbName = $dbValues["dbName"];
        $wikiName = $dbValues["wikiName"];
        $url = $dbValues["url"];

        // Get statistics.
        $userId = $ec->getUserId($username);
        $revisionCounts = $ec->getRevisionCounts($userId);
        $pageCounts = $ec->getPageCounts($username, $revisionCounts['total']);
        $logCounts = $ec->getLogCounts($userId);
        $namespaceTotals = $ec->getNamespaceTotals($userId);

        // Give it all to the template.
        return $this->render('editCounter/result.html.twig', [
            'xtTitle' => 'tool_ec',
            'xtPage' => 'ec',
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            'is_labs' => $lh->isLabs(),

            // Project.
            'project' => $project,
            'wiki' => $dbName,
            'name' => $wikiName,
            'url' => $url,
            'namespaces' => $api->namespaces($project),

            // User and groups.
            'username' => $username,
            'user_id' => $userId,
            'user_groups' => $api->groups($project, $username),
            'global_groups' => $api->globalGroups($project, $username),

            // Revision counts.
            'deleted_edits' => $revisionCounts['deleted'],
            'total_edits' => $revisionCounts['total'],
            'live_edits' => $revisionCounts['live'],
            'first_rev' => $revisionCounts['first'],
            'latest_rev' => $revisionCounts['last'],
            'days' => $revisionCounts['days'],
            'avg_per_day' => $revisionCounts['avg_per_day'],
            'rev_24h' => $revisionCounts['24h'],
            'rev_7d' => $revisionCounts['7d'],
            'rev_30d' => $revisionCounts['30d'],
            'rev_365d' => $revisionCounts['365d'],
            'rev_small' => $revisionCounts['small'],
            'rev_large' => $revisionCounts['large'],
            'with_comments' => $revisionCounts['with_comments'],
            'without_comments' => $revisionCounts['live'] - $revisionCounts['with_comments'],
            'minor_edits' => $revisionCounts['minor_edits'],
            'nonminor_edits' => $revisionCounts['live'] - $revisionCounts['minor_edits'],

            // Page counts.
            'uniquePages' => $pageCounts['unique'],
            'pagesCreated' => $pageCounts['created'],
            'pagesMoved' => $pageCounts['moved'],
            'editsPerPage' => $pageCounts['edits_per_page'],

            // Log counts (keys are 'log name'-'action').
            'pagesThanked' => $logCounts['thanks-thank'],
            'pagesApproved' => $logCounts['review-approve'], // Merged -a, -i, and -ia approvals.
            'pagesPatrolled' => $logCounts['patrol-patrol'],
            'usersBlocked' => $logCounts['block-block'],
            'usersUnblocked' => $logCounts['block-unblock'],
            'pagesProtected' => $logCounts['protect-protect'],
            'pagesUnprotected' => $logCounts['protect-unprotect'],
            'pagesDeleted' => $logCounts['delete-delete'],
            'pagesDeletedRevision' => $logCounts['delete-revision'],
            'pagesRestored' => $logCounts['delete-restore'],
            'pagesImported' => $logCounts['import-import'],
            'files_uploaded' => $logCounts['upload-upload'],
            'files_modified' => $logCounts['upload-overwrite'],
            'files_uploaded_commons' => $logCounts['files_uploaded_commons'],

            // Namespace Totals
            'namespaceArray' => $namespaceTotals,
            'namespaceTotal' => array_sum($namespaceTotals),

        ]);
    }
}
