<?php
/**
 * This file contains only the DeleteCounterController class.
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
 * This controller handles the Delete Counter tool.
 */
class DeleteCounterController extends Controller
{

    /**
     * Get the tool's shortname.
     *
     * @return string
     */
    public function getToolShortname()
    {
        return 'dc';
    }

    /**
     * The Delete Counter Counter search form.
     *
     * @param Request $request The HTTP request.
     * @param string  $project The project database name or domain.
     *
     * @return Response
     *
     * @Route("/dc",           name="dc")
     * @Route("/dc",           name="DeletionCounter")
     * @Route("/dc/",          name="DeletionCounterSlash")
     * @Route("/dc/index.php", name="DeletionCounterIndexPhp")
     * @Route("/dc/{project}", name="DeletionCounterProject")
     */
    public function indexAction(Request $request, $project = null)
    {
        // Get the query parameters.
        $projectName = $project ?: $request->query->get('project');
        $username = $request->query->get(
            'username',
            $request->query->get('user')
        );

        // If we've got a project and user, redirect to results.
        if ($projectName != '' && $username != '') {
            $routeParams = [ 'project' => $projectName, 'username' => $username ];
            return $this->redirectToRoute(
                'DeletionCounterResult',
                $routeParams
            );
        }

        // Instantiate the project if we can, or use the default.
        $theProject = (!empty($projectName))
            ? ProjectRepository::getProject($projectName, $this->container)
            : ProjectRepository::getDefaultProject($this->container);

        // Show the form.
        return $this->render(
            'deletionCounter/index.html.twig',
            [
            'xtPageTitle' => 'tool-dc',
            'xtSubtitle' => 'tool-dc-desc',
            'xtPage' => 'dc',
            'project' => $theProject,
            ]
        );
    }

    /**
     * Display the results
     *
     * @param string $project  The project domain name.
     * @param string $username The username.
     *
     * @return Response
     *
     * @Route("/dc/{project}/{username}", name="DeletionCounterResult")
     */
    public function resultAction($project, $username)
    {
        /**
         * Project information
         *
         * @var Project $project
         */
        $project = ProjectRepository::getProject($project, $this->container);
        $projectRepo = $project->getRepository();
        $dbName = $project->getDatabaseName();
        $domain = $project->getDomain();

        $user = UserRepository::getUser($username, $this->container);

        $userID = $user->getId($project);

        if (!$project->exists()) {
            $this->addFlash('notice', ['invalid-project', $domain]);
            return $this->redirectToRoute('SimpleEditCounter');
        }

        $loggingTable = $projectRepo->getTableName(
            $dbName,
            "logging",
            "userindex"
        );

        /**
         * Connection to the database
         *
         * @var Connection $conn
         */
        $conn = $this->get('doctrine')->getManager('replicas')->getConnection();

        $types = $this->container->getParameter("deletion_counter");

        if (!isset($types[$domain])) {
            $this->addFlash('notice', [ 'no-result', $username ]);
            return $this->redirectToRoute(
                'DeletionCounter',
                [
                    'project' => $project->getDomain()
                ]
            );
        }

        $data = $types[$domain];

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
            return $this->redirectToRoute(
                'DeletionCounterProject',
                [
                    'project' => $project->getDomain()
                ]
            );
        }

        $total = 0;
        $resultData = $resultQuery->fetchAll();

        if (count($resultData) == 0) {
            $this->addFlash('notice', [ 'no-result', $username ]);
            return $this->redirectToRoute(
                'DeletionCounterProject',
                [
                    'project' => $project->getDomain()
                ]
            );
        }

        $results = [];

        foreach ($resultData as $row) {
            $results[$row["type"]] = $row["count"];
            $total += $row["count"];
        }

        // Unknown user or generated no results
        // This is a workaround to detect non-existent IPs.
        if (count($results) == 0) {
            $this->addFlash('notice', [ 'no-result', $username ]);

            return $this->redirectToRoute(
                'DeletionCounterProject',
                [
                    'project' => $project->getDomain()
                ]
            );
        }

        // Assign the values and display the template
        return $this->render(
            'deletionCounter/result.html.twig',
            [
                'xtPage' => 'dc',
                'xtTitle' => $username,
                'user' => $user,
                'project' => $project,
                'results' => $results,
                'total' => $total,
            ]
        );
    }
}
