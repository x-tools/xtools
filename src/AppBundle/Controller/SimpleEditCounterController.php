<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;

class SimpleEditCounterController extends Controller
{
    /**
     * @Route("/sc", name="sc")
     * @Route("/sc", name="SimpleEditCounter")
     * @Route("/sc/", name="SimpleEditCounterSlash")
     * @Route("/sc/index.php", name="SimpleEditCounterIndexPhp")
     * @Route("/sc/{project}", name="SimpleEditCounterProject")
     */
    public function indexAction($project = null)
    {

        $lh = $this->get('app.labs_helper');

        $lh->checkEnabled('sc');

        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $projectQuery = $request->query->get('project');
        $username = $request->query->get('username', $request->query->get('user'));

        if ($projectQuery != '' && $username != '') {
            $routeParams = [ 'project' => $projectQuery, 'username' => $username ];
            return $this->redirectToRoute('SimpleEditCounterResult', $routeParams);
        } elseif ($projectQuery != '') {
            return $this->redirectToRoute('SimpleEditCounterProject', [ 'project' => $projectQuery ]);
        }

        // Otherwise fall through.
        return $this->render('simpleEditCounter/index.html.twig', [
            'xtPageTitle' => 'tool-sc',
            'xtSubtitle' => 'tool-sc-desc',
            'xtPage' => 'sc',
            'project' => $project,
        ]);
    }

    /**
     * @Route("/sc/{project}/{username}", name="SimpleEditCounterResult")
     */
    public function resultAction($project, $username)
    {
        $lh = $this->get('app.labs_helper');
        $lh->checkEnabled('sc');

        $user = new User($username);
        $username = $user->getUsername();

        $project = ProjectRepository::getProject($project, $this->container);

        // if (!$project->exists()) {
        //     $this->addFlash('notice', ['invalid-project', $projectQuery]);
        //     return $this->redirectToRoute('SimpleEditCounter');
        // }

        $dbName = $project->getDatabaseName();
        $url = $project->getUrl();

        $userTable = $lh->getTable('user', $dbName);
        $archiveTable = $lh->getTable('archive', $dbName);
        $revisionTable = $lh->getTable('revision', $dbName);
        $userGroupsTable = $lh->getTable('user_groups', $dbName);

        // Grab the connection to the replica database (which is separate from the above)
        $conn = $this->get('doctrine')->getManager('replicas')->getConnection();

        // Prepare the query and execute
        $resultQuery = $conn->prepare("
			SELECT 'id' as source, user_id as value FROM $userTable WHERE user_name = :username
			UNION
			SELECT 'arch' as source, COUNT(*) AS value FROM $archiveTable WHERE ar_user_text = :username
			UNION
			SELECT 'rev' as source, COUNT(*) AS value FROM $revisionTable WHERE rev_user_text = :username
			UNION
			SELECT 'groups' as source, ug_group as value
				FROM $userGroupsTable JOIN $userTable on user_id = ug_user WHERE user_name = :username
			");

        $resultQuery->bindParam('username', $username);
        $resultQuery->execute();

        if ($resultQuery->errorCode() > 0) {
            $this->addFlash('notice', [ 'no-result', $username ]);
            return $this->redirectToRoute('SimpleEditCounterProject', [ 'project' => $projectQuery ]);
        }

        // Fetch the result data
        $results = $resultQuery->fetchAll();

        // Initialize the variables - just so we don't get variable undefined errors if there is a problem
        $id = '';
        $arch = '';
        $rev = '';
        $groups = '';

        // Iterate over the results, putting them in the right variables
        foreach ($results as $row) {
            if ($row['source'] == 'id') {
                $id = $row['value'];
            }
            if ($row['source'] == 'arch') {
                $arch = $row['value'];
            }
            if ($row['source'] == 'rev') {
                $rev = $row['value'];
            }
            if ($row['source'] == 'groups') {
                $groups .= $row['value']. ', ';
            }
        }

        // Unknown user - If the user is created the $results variable will have 3 entries.
        // This is a workaround to detect non-existent IPs.
        if (count($results) < 3 && $arch == 0 && $rev == 0) {
            $this->addFlash('notice', [ 'no-result', $username ]);

            return $this->redirectToRoute('SimpleEditCounterProject', [ 'project' => $projectQuery ]);
        }

        // Remove the last comma and space
        if (strlen($groups) > 2) {
            $groups = substr($groups, 0, -2);
        }

        // If the user isn't in any groups, show a message.
        if (strlen($groups) == 0) {
            $groups = '---';
        }

        $globalGroups = '';

        if (boolval($this->getParameter('app.single_wiki'))) {
            // Retrieving the global groups, using the ApiHelper class
            $api = $this->get('app.api_helper');
            $globalGroups = $api->globalGroups($url, $username);
        }

        // Assign the values and display the template
        return $this->render('simpleEditCounter/result.html.twig', [
            'xtPage' => 'sc',
            'xtTitle' => $username,
            'user' => $user,
            'project' => $project,
            'project_url' => $url,
            'id' => $id,
            'arch' => $arch,
            'rev' => $rev + $arch,
            'live' => $rev,
            'groups' => $groups,
            'globalGroups' => $globalGroups,
        ]);
    }
}
