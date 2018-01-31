<?php
/**
 * This file contains only the AdminScoreController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use DateTime;
use Xtools\ProjectRepository;
use Xtools\UserRepository;

/**
 * The AdminScoreController serves the search form and results page of the AdminScore tool
 */
class AdminScoreController extends XtoolsController
{

    /**
     * Get the tool's shortname.
     * @return string
     * @codeCoverageIgnore
     */
    public function getToolShortname()
    {
        return 'adminscore';
    }

    /**
     * Display the AdminScore search form.
     * @Route("/adminscore", name="adminscore")
     * @Route("/adminscore", name="AdminScore")
     * @Route("/adminscore/", name="AdminScoreSlash")
     * @Route("/adminscore/index.php", name="AdminScoreIndexPhp")
     * @Route("/scottywong tools/adminscore.php", name="AdminScoreLegacy")
     * @Route("/adminscore/{project}", name="AdminScoreProject")
     * @param Request $request The HTTP request.
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $params = $this->parseQueryParams($request);

        // Redirect if we have a project and user.
        if (isset($params['project']) && isset($params['username'])) {
            return $this->redirectToRoute('AdminScoreResult', $params);
        }

        // Convert the given project (or default project) into a Project instance.
        $params['project'] = $this->getProjectFromQuery($params);

        return $this->render('adminscore/index.html.twig', [
            'xtPage' => 'adminscore',
            'xtPageTitle' => 'tool-adminscore',
            'xtSubtitle' => 'tool-adminscore-desc',
            'project' => $params['project'],
        ]);
    }

    /**
     * Display the AdminScore results.
     * @Route("/adminscore/{project}/{username}", name="AdminScoreResult")
     * @param Request $request The HTTP request.
     * @return Response
     * @todo Move SQL to a model.
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request)
    {
        // Second parameter causes it return a Redirect to the index if the user has too many edits.
        $ret = $this->validateProjectAndUser($request, 'adminscore');
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($projectData, $user) = $ret;
        }

        $dbName = $projectData->getDatabaseName();
        $projectRepo = $projectData->getRepository();

        $userTable = $projectRepo->getTableName($dbName, 'user');
        $pageTable = $projectRepo->getTableName($dbName, 'page');
        $loggingTable = $projectRepo->getTableName($dbName, 'logging', 'userindex');
        $revisionTable = $projectRepo->getTableName($dbName, 'revision');
        $archiveTable = $projectRepo->getTableName($dbName, 'archive');

        // MULTIPLIERS (to review)
        $multipliers = [
            'account-age-mult' => 1.25,             # 0 if = 365 jours
            'edit-count-mult' => 1.25,              # 0 if = 10 000
            'user-page-mult' => 0.1,                # 0 if =
            'patrols-mult' => 1,                    # 0 if =
            'blocks-mult' => 1.4,                   # 0 if = 10
            'afd-mult' => 1.15,
            'recent-activity-mult' => 0.9,          # 0 if =
            'aiv-mult' => 1.15,
            'edit-summaries-mult' => 0.8,           # 0 if =
            'namespaces-mult' => 1.0,               # 0 if =
            'pages-created-live-mult' => 1.4,       # 0 if =
            'pages-created-deleted-mult' => 1.4,    # 0 if =
            'rpp-mult' => 1.15,                     # 0 if =
            'user-rights-mult' => 0.75,             # 0 if =
        ];

        // Grab the connection to the replica database (which is separate from the above)
        $conn = $this->get('doctrine')->getManager("replicas")->getConnection();

        // Prepare the query and execute
        $resultQuery = $conn->prepare("
        SELECT 'account-age' AS source, user_registration AS value FROM $userTable
            WHERE user_name = :username
        UNION
        SELECT 'edit-count' AS source, user_editcount AS value FROM $userTable
            WHERE user_name = :username
        UNION
        SELECT 'user-page' AS source, page_len AS value FROM $pageTable
            WHERE page_namespace = 2 AND page_title = :username
        UNION
        SELECT 'patrols' AS source, COUNT(*) AS value FROM $loggingTable
            WHERE log_type = 'patrol'
                AND log_action = 'patrol'
                AND log_namespace = 0
                AND log_deleted = 0 AND log_user_text = :username
        UNION
        SELECT 'blocks' AS source, COUNT(*) AS value FROM $loggingTable l
            INNER JOIN $userTable u ON l.log_user = u.user_id
            WHERE l.log_type = 'block' AND l.log_action = 'block'
            AND l.log_namespace = 2 AND l.log_deleted = 0 AND u.user_name = :username
        UNION
        SELECT 'afd' AS source, COUNT(*) AS value FROM $revisionTable r
          INNER JOIN $pageTable p on p.page_id = r.rev_page
            WHERE p.page_title LIKE 'Articles_for_deletion/%'
                AND p.page_title NOT LIKE 'Articles_for_deletion/Log/%'
                AND r.rev_user_text = :username
        UNION
        SELECT 'recent-activity' AS source, COUNT(*) AS value FROM $revisionTable
            WHERE rev_user_text = :username AND rev_timestamp > (now()-INTERVAL 730 day) AND rev_timestamp < now()
        UNION
        SELECT 'aiv' AS source, COUNT(*) AS value FROM $revisionTable r
          INNER JOIN $pageTable p on p.page_id = r.rev_page
            WHERE p.page_title LIKE 'Administrator_intervention_against_vandalism%'
                AND r.rev_user_text = :username
        UNION
        SELECT 'edit-summaries' AS source, COUNT(*) AS value FROM $revisionTable JOIN $pageTable ON rev_page = page_id
            WHERE page_namespace = 0 AND rev_user_text = :username
        UNION
        SELECT 'namespaces' AS source, count(*) AS value FROM $revisionTable JOIN $pageTable ON rev_page = page_id
            WHERE rev_user_text = :username AND page_namespace = 0
        UNION
        SELECT 'pages-created-live' AS source, COUNT(*) AS value FROM $revisionTable
            WHERE rev_user_text = :username AND rev_parent_id = 0
        UNION
        SELECT 'pages-created-deleted' AS source, COUNT(*) AS value FROM $archiveTable
            WHERE ar_user_text = :username AND ar_parent_id = 0
        UNION
        SELECT 'rpp' AS source, COUNT(*) AS value FROM $revisionTable r
          INNER JOIN $pageTable p on p.page_id = r.rev_page
            WHERE p.page_title LIKE 'Requests_for_page_protection%'
                AND r.rev_user_text = :username;
        ");

        $username = $user->getUsername();
        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();

        // Fetch the result data
        $results = $resultQuery->fetchAll();

        $master = [];
        $total = 0;

        foreach ($results as $row) {
            $key = $row['source'];
            $value = $row['value'];

            if ($key === 'account-age') {
                if ($value == null) {
                    $value = 0;
                } else {
                    $now = new DateTime();
                    $date = new DateTime($value);
                    $diff = $date->diff($now);
                    $formula = 365 * $diff->format('%y') + 30 * $diff->format('%m') + $diff->format('%d');
                    $value = $formula - 365;
                }
            }

            $multiplierKey = $row['source'] . '-mult';
            $multiplier = isset($multipliers[$multiplierKey]) ? $multipliers[$multiplierKey] : 1;
            $score = max(min($value * $multiplier, 100), -100);
            $master[$key]['mult'] = $multiplier;
            $master[$key]['value'] = $value;
            $master[$key]['score'] = $score;
            $total += $score;
        }

        return $this->render('adminscore/result.html.twig', [
            'xtPage' => 'adminscore',
            'xtTitle' => $username,
            'project' => $projectData,
            'user' => $user,
            'master' => $master,
            'total' => $total,
        ]);
    }
}
