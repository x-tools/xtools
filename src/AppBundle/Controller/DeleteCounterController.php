<?php
/**
 * This file contains only the SimpleEditCounterController class.
 */

namespace AppBundle\Controller;

use Doctrine\DBAL\Connection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;

/**
 * This controller handles the Simple Edit Counter tool.
 */
class DeleteCounterController extends Controller
{

    /**
     * Get the tool's shortname.
     * @return string
     */
    public function getToolShortname()
    {
        return 'dc';
    }

    /**
     * The Simple Edit Counter search form.
     * @Route("/dc", name="dc")
     * @Route("/dc", name="DeletionCounter")
     * @Route("/dc/", name="DeletionCounterSlash")
     * @Route("/dc/index.php", name="DeletionCounterIndexPhp")
     * @Route("/dc/{project}", name="DeletionCounterProject")
     * @param Request $request The HTTP request.
     * @param string $project The project database name or domain.
     * @return Response
     */
    public function indexAction(Request $request, $project = null)
    {
        // Get the query parameters.
        $projectName = $project ?: $request->query->get('project');
        $username = $request->query->get('username', $request->query->get('user'));

        // If we've got a project and user, redirect to results.
        if ($projectName != '' && $username != '') {
            $routeParams = [ 'project' => $projectName, 'username' => $username ];
            return $this->redirectToRoute('DeletionCounterResult', $routeParams);
        }

        // Instantiate the project if we can, or use the default.
        $theProject = (!empty($projectName))
            ? ProjectRepository::getProject($projectName, $this->container)
            : ProjectRepository::getDefaultProject($this->container);

        // Show the form.
        return $this->render('deletionCounter/index.html.twig', [
            'xtPageTitle' => 'tool-dc',
            'xtSubtitle' => 'tool-dc-desc',
            'xtPage' => 'dc',
            'project' => $theProject,
        ]);
    }

    /**
     * Display the
     * @Route("/dc/{project}/{username}", name="DeletionCounterResult")
     * @param string $project The project domain name.
     * @param string $username The username.
     * @return Response
     */
    public function resultAction($project, $username)
    {
        /** @var Project $project */
        $project = ProjectRepository::getProject($project, $this->container);
        $projectRepo = $project->getRepository();

        $user = UserRepository::getUser($username, $this->container);

        $userID = $user->getId($project);

        if (!$project->exists()) {
            $this->addFlash('notice', ['invalid-project', $project]);
            return $this->redirectToRoute('SimpleEditCounter');
        }

        $dbName = $project->getDatabaseName();

        $loggingTable = $projectRepo->getTableName($dbName, "logging", "userindex");

        /** @var Connection $conn */
        $conn = $this->get('doctrine')->getManager('replicas')->getConnection();

        $types = $this->container->getParameter("deletion_counter");

        if (!isset($types[$project->getDatabaseName()])) {
            $this->addFlash('notice', [ 'no-result', $username ]);
            return $this->redirectToRoute(
                'DeletionCounter',
                [
                    'project' => $project->getDomain()
                ]
            );
        }

        $data = $types[$project->getDatabaseName()];

        $queryArray = [];

        foreach ($data as $key => $value) {
            $queryArray[] = "(SELECT '$key' AS `type`, COUNT(log_id) AS `count`
            FROM $loggingTable
            WHERE log_type = 'delete'
            AND log_user = :userID
            AND log_comment RLIKE \"$value\")";
        }
        $resultQuery = $conn->prepare(join("\nUNION\n", $queryArray));

        $resultQuery->bindParam('userID', $userID);
        $resultQuery->execute();

        if ($resultQuery->errorCode() > 0) {
            $this->addFlash('notice', [ 'no-result', $username ]);
            return $this->redirectToRoute('DeletionCounterProject', [ 'project' => $project->getDomain() ]);
        }

        foreach ($resultQuery->fetchAll() as $row) {
            $results[$row["type"]] = $row["count"];
        }

        dump($results);

        // Unknown user - If the user is created the $results variable will have 3 entries.
        // This is a workaround to detect non-existent IPs.
        if (count($results) == 0) {
            $this->addFlash('notice', [ 'no-result', $username ]);

            return $this->redirectToRoute('DeletionCounterProject', [ 'project' => $project->getDomain() ]);
        }



        // Assign the values and display the template
        return $this->render('deletionCounter/result.html.twig', [
            'xtPage' => 'dc',
            'xtTitle' => $username,
            'user' => $user,
            'project' => $project,
            'results' => $results,
        ]);
    }
}
