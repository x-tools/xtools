<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EditCounterController extends Controller
{
    /**
     * @Route("/ec", name="EditCounter")
     * @Route("/ec/", name="EditCounterSlash")
     * @Route("/ec/get", name="EditCounterGet")
     * @Route("/ec/index.php", name="EditCounterIndexPhp")
     */
    public function indexAction()
    {
        if (!$this->getParameter("enable.ec")) {
            throw new NotFoundHttpException("This tool is disabled");
        }

        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $project = $request->query->get('project');
        $username = $request->query->get('user');

        if ($project != "" && $username != "") {
            return $this->redirectToRoute("EditCounterResult", array('project'=>$project, 'username' => $username));
        }
        else if ($project != "") {
            return $this->redirectToRoute("EditCounterProject", array('project'=>$project));
        }

        // Otherwise fall through.
        return $this->render('editCounter/index.html.twig', [
            "pageTitle" => "tool_ec",
            "subtitle" => "tool_ec_desc",
            'page' => "ec",
            'title' => "tool_ec",
        ]);
    }

    /**
     * @Route("/ec/{project}", name="EditCounterProject")
     */
    public function projectAction($project) {
        if (!$this->getParameter("enable.ec")) {
            throw new NotFoundHttpException("This tool is disabled");
        }
        return $this->render('editCounter/index.html.twig', [
            'title' => "$project edit counter",
            'page' => "ec",
            'project' => $project,
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
        ]);
    }
    
    /**
     * @Route("/ec/{project}/{username}", name="EditCounterResult")
     */
    public function resultAction($project, $username) {
        if (!$this->getParameter("enable.ec")) {
            throw new NotFoundHttpException("This tool is disabled");
        }

        $username = ucfirst($username);
        $username = str_replace("_", " ", $username);

        // Grab the connection to the meta database
        $conn = $this->get('doctrine')->getManager("meta")->getConnection();

        // Create the query we're going to run against the meta database
        $wikiQuery = $conn->createQueryBuilder();
        $wikiQuery
            ->select(['dbName','name','url'])
            ->from("wiki")
            ->where($wikiQuery->expr()->eq('dbname', ':project'))
            ->orwhere($wikiQuery->expr()->like('name', ':project'))
            ->orwhere($wikiQuery->expr()->like('url', ":project"))
            ->setParameter("project", $project);
        $wikiStatement = $wikiQuery->execute();

        // Fetch the wiki data
        $wikis = $wikiStatement->fetchAll();

        // Throw an exception if we can't find the wiki
        if (sizeof($wikis) <1) {
            throw new Exception("Unknown wiki \"$project\"");
        }

        // Grab the data we need out of it.
        $dbName = $wikis[0]['dbName'];
        $wikiName = $wikis[0]['name'];
        $url = $wikis[0]['url'];

        if (substr($dbName, -2) != "_p") {
            $dbName .= "_p";
        }

        // Grab the connection to the replica database (which is separate from the above)
        $conn = $this->get('doctrine')->getManager("replicas")->getConnection();

        // Prepare the query and execute
        $resultQuery = $conn->prepare( "
			SELECT 'id' as source, user_id as value FROM $dbName.user WHERE user_name = :username
			UNION
			SELECT 'arch' as source, COUNT(*) AS value FROM $dbName.archive_userindex WHERE ar_user_text = :username
			UNION
			SELECT 'rev' as source, COUNT(*) AS value FROM $dbName.revision_userindex WHERE rev_user_text = :username
			UNION
			(SELECT 'first_rev' as source, rev_timestamp FROM $dbName.`revision_userindex` WHERE rev_user_text = :username ORDER BY rev_timestamp ASC LIMIT 1)
			UNION
			(SELECT 'latest_rev' as source, rev_timestamp FROM $dbName.`revision_userindex` WHERE rev_user_text = :username ORDER BY rev_timestamp DESC LIMIT 1)
			UNION 
			SELECT 'rev_24h' as source, COUNT(*) as value FROM $dbName.revision_userindex WHERE rev_user_text = :username AND rev_timestamp >= DATE_SUB(NOW(),INTERVAL 24 HOUR)
			UNION
			SELECT 'rev_7d' as source, COUNT(*) as value FROM $dbName.revision_userindex WHERE rev_user_text = :username AND rev_timestamp >= DATE_SUB(NOW(),INTERVAL 7 DAY)
			UNION
			SELECT 'rev_30d' as source, COUNT(*) as value FROM $dbName.revision_userindex WHERE rev_user_text = :username AND rev_timestamp >= DATE_SUB(NOW(),INTERVAL 30 DAY)
			UNION
			SELECT 'rev_365d' as source, COUNT(*) as value FROM $dbName.revision_userindex WHERE rev_user_text = :username AND rev_timestamp >= DATE_SUB(NOW(),INTERVAL 365 DAY)
			UNION
			SELECT 'groups' as source, ug_group as value FROM $dbName.user_groups JOIN $dbName.user on user_id = ug_user WHERE user_name = :username
			");

        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();

        // Fetch the result data
        $results = $resultQuery->fetchAll();

        // Unknown user - This is a dirty hack that should be fixed.
        if (sizeof($results) < 7) {
            throw new Exception("Unknown user \"$username\"");
        }

        // Initialize the variables - just so we don't get variable undefined errors if there is a problem
        $id = 0;
        $arch = "";
        $rev = "";
        $first_rev = 0;
        $latest_rev = 0;
        $rev_24h = "";
        $rev_7d = "";
        $rev_30d = "";
        $rev_365d = "";
        $groups = "";

        // Iterate over the results, putting them in the right variables
        foreach($results as $row) {
            if($row["source"] == "id") {
                $id = $row["value"];
            }
            if($row["source"] == "arch") {
                $arch = $row["value"];
            }
            if($row["source"] == "rev") {
                $rev = $row["value"];
            }
            if($row["source"] == "first_rev") {
                $first_rev = strtotime($row["value"]);
            }
            if($row["source"] == "latest_rev") {
                $latest_rev = strtotime($row["value"]);
            }
            if($row["source"] == "rev_24h") {
                $rev_24h = $row["value"];
            }
            if($row["source"] == "rev_7d") {
                $rev_7d = $row["value"];
            }
            if($row["source"] == "rev_30d") {
                $rev_30d = $row["value"];
            }
            if($row["source"] == "rev_365d") {
                $rev_365d = $row["value"];
            }
            if($row["source"] == "groups") {
                $groups .= $row["value"]. ", ";
            }
        }

        $days = ceil(($latest_rev - $first_rev)/(60*60*24));

        // Workaround if there is only one edit.

        if ($first_rev == $latest_rev) {
            $days = 1;
        }

        $delta = round(($rev/$days), 3);

        // Remove the last comma and space
        if (strlen($groups) > 2) {
            $groups = substr($groups, 0, -2);
        }

        // If the user isn't in any groups, show a message.
        if (strlen($groups) == 0) {
            $groups = "----";
        }

        if ($first_rev > 0) {
            $first_rev = date('Y-m-d h:i:s', $first_rev);
        }
        else {
            $first_rev = "----";
        }

        if ($latest_rev > 0) {
            $latest_rev = date('Y-m-d h:i:s', $latest_rev);
        }
        else {
            $latest_rev = "----";
        }

        // -------------------------
        // General statistics part 2
        // -------------------------

        $resultQuery = $conn->prepare("
			SELECT 'unique-pages' as source, COUNT(distinct rev_page) as value FROM $dbName.`revision_userindex` where rev_user_text=:username
			UNION
			SELECT 'pages-created-live' as source, COUNT(*) as value from $dbName.`revision_userindex` where rev_user_text=:username and rev_parent_id=0
			UNION
			SELECT 'pages-created-archive' as source, COUNT(*) as value from $dbName.`archive_userindex` where ar_user_text=:username and ar_parent_id=0
			UNION
			SELECT 'pages-moved' as source, count(*) as value from $dbName.`logging` where log_type='move' and log_action='move' and log_user_text=:username 
            ");

        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();

        // Fetch the result data
        $results = $resultQuery->fetchAll();

        $uniquePages = 0;
        $pagesCreated = 0;
        $pagesMoved = 0;
        $editsPerPage = 0;

        foreach($results as $row) {
            if($row["source"] == "unique-pages") {
                $uniquePages += $row["value"];
            }
            if($row["source"] == "pages-created-live") {
                $pagesCreated += $row["value"];
            }
            if($row["source"] == "pages-created-archive") {
                $pagesCreated += $row["value"];
            }
            if($row["source"] == "pages-moved") {
                $pagesMoved += $row["value"];
            }
        }

        if ($uniquePages > 0) {
            $editsPerPage = ($rev + $arch) / $uniquePages;
            $editsPerPage = number_format($editsPerPage, 2);
        }

        // -------------------------
        // General statistics part 3
        // -------------------------
        // TODO: Turn into single query - not using UNION
        $resultQuery = $conn->prepare("
        SELECT 'pages-thanked' as source, count(*) as value from $dbName.`logging` where log_type='thank' and log_action='thank' and log_user_text=:username 
        UNION
        SELECT 'pages-approved' as source, count(*) as value from $dbName.`logging` where log_type='review' and log_action='approve' and log_user_text=:username 
        UNION
        SELECT 'pages-patrolled' as source, count(*) as value from $dbName.`logging` where log_type='patrol' and log_action='patrol' and log_user_text=:username 
        UNION
        SELECT 'users-blocked' as source, count(*) as value from $dbName.`logging` where log_type='block' and log_action='block' and log_user_text=:username 
        UNION
        SELECT 'users-unblocked' as source, count(*) as value from $dbName.`logging` where log_type='block' and log_action='unblock' and log_user_text=:username 
        UNION
        SELECT 'pages-protected' as source, count(*) as value from $dbName.`logging` where log_type='protect' and log_action='protect' and log_user_text=:username 
        UNION
        SELECT 'pages-unprotected' as source, count(*) as value from $dbName.`logging` where log_type='protect' and log_action='unprotect' and log_user_text=:username 
        UNION
        SELECT 'pages-deleted' as source, count(*) as value from $dbName.`logging` where log_type='delete' and log_action='delete' and log_user_text=:username 
        UNION
        SELECT 'pages-deleted-revision' as source, count(*) as value from $dbName.`logging` where log_type='delete' and log_action='revision' and log_user_text=:username 
        UNION
        SELECT 'pages-restored' as source, count(*) as value from $dbName.`logging` where log_type='delete' and log_action='restore' and log_user_text=:username 
        UNION
        SELECT 'pages-imported' as source, count(*) as value from $dbName.`logging` where log_type='import' and log_action='import' and log_user_text=:username 
        ");

        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();

        // Fetch the result data
        $results = $resultQuery->fetchAll();

        $pagesThanked = 0;
        $pagesApproved = 0;
        $pagesPatrolled = 0;
        $usersBlocked = 0;
        $usersUnblocked = 0;
        $pagesProtected = 0;
        $pagesUnrotected = 0;
        $pagesDeleted = 0;
        $pagesDeletedRevision = 0;
        $pagesRestored = 0;
        $pagesImported = 0;

        foreach($results as $row) {
            if ($row["source"] == "pages-thanked") {
                $pagesThanked += $row["value"];
            }
            if ($row["source"] == "pages-approved") {
                $pagesApproved += $row["value"];
            }
            if ($row["source"] == "pages-patrolled") {
                $pagesPatrolled += $row["value"];
            }
            if ($row["source"] == "users-blocked") {
                $usersBlocked += $row["value"];
            }
            if ($row["source"] == "users-unblocked") {
                $usersUnblocked += $row["value"];
            }
            if ($row["source"] == "pages-protected") {
                $pagesProtected += $row["value"];
            }
            if ($row["source"] == "pages-unprotected") {
                $pagesUnrotected += $row["value"];
            }
            if ($row["source"] == "pages-deleted") {
                $pagesDeleted += $row["value"];
            }
            if ($row["source"] == "pages-deleted-revision") {
                $pagesDeletedRevision += $row["value"];
            }
            if ($row["source"] == "pages-restored") {
                $pagesRestored += $row["value"];
            }
            if ($row["source"] == "pages-imported") {
                $pagesImported += $row["value"];
            }
        }


        // -------------------------
        // General statistics part 4
        // -------------------------



        // -------------------------
        // General statistics graphs
        // -------------------------



        // -------------------------
        // Namespace Totals
        // -------------------------
        // TODO: Convert to named namespaces
        $namespaceArray = array();
        $namespaceTotal = 0;
        if (($rev + $arch) > 0) {
            $colors = array(
                0 => '#Cc0000',#'#FF005A', #red '#FF5555',
                1 => '#F7b7b7',

                2 => '#5c8d20',#'#008800', #green'#55FF55',
                3 => '#85eD82',

                4 => '#2E97E0', #blue
                5 => '#B9E3F9',

                6 => '#e1711d',  #orange
                7 => '#ffc04c',

                8 => '#FDFF98', #yellow

                9 => '#5555FF',
                10 => '#55FFFF',

                11 => '#0000C0',  #
                12 => '#008800',  # green
                13 => '#00C0C0',
                14 => '#FFAFAF',	# rosé
                15 => '#808080',	# gray
                16 => '#00C000',
                17 => '#404040',
                18 => '#C0C000',	# green
                19 => '#C000C0',

                100 => '#75A3D1',	# blue
                101 => '#A679D2',	# purple
                102 => '#660000',
                103 => '#000066',
                104 => '#FAFFAF',	# caramel
                105 => '#408345',
                106 => '#5c8d20',
                107 => '#e1711d',	# red
                108 => '#94ef2b',	# light green
                109 => '#756a4a',	# brown
                110 => '#6f1dab',
                111 => '#301e30',
                112 => '#5c9d96',
                113 => '#a8cd8c',	# earth green
                114 => '#f2b3f1',	# light purple
                115 => '#9b5828',
                118 => '#99FFFF',
                119 => '#99BBFF',
                120 => '#FF99FF',
                121 => '#CCFFFF',
                122 => '#CCFF00',
                123 => '#CCFFCC',
                200 => '#33FF00',
                201 => '#669900',
                202 => '#666666',
                203 => '#999999',
                204 => '#FFFFCC',
                205 => '#FF00CC',
                206 => '#FFFF00',
                207 => '#FFCC00',
                208 => '#FF0000',
                209 => '#FF6600',
                446 => '#06DCFB',
                447 => '#892EE4',
                460 => '#99FF66',
                461 => '#99CC66',	# green
                470 => '#CCCC33',	# ocker
                471 => '#CCFF33',
                480 => '#6699FF',
                481 => '#66FFFF',
                490 => '#995500',
                491 => '#998800',
                710 => '#FFCECE',
                711 => '#FFC8F2',
                828 => '#F7DE00',
                829 => '#BABA21',
                866 => '#FFFFFF',
                867 => '#FFCCFF',
                1198 => '#FF34B3',
                1199 => '#8B1C62',);

            $colors2 = array('#61a9f3',#blue
                '#f381b9',#pink
                '#61E3A9',

                '#D56DE2',
                '#85eD82',
                '#F7b7b7',
                '#CFDF49',
                '#88d8f2',
                '#07AF7B',#green
                '#B9E3F9',
                '#FFF3AD',
                '#EF606A',#red
                '#EC8833',
                '#FFF100',
                '#87C9A5',
                '#FFFB11',
                '#005EBC',
                '#9AEB67',
                '#FF4A26',
                '#FDFF98',
                '#6B7EFF',
                '#BCE02E',
                '#E0642E',
                '#E0D62E',

                '#02927F',
                '#FF005A',
                '#61a9f3', #blue' #FFFF55',
            );
            $colorCounter2 = 0;
            $resultQuery = $conn->prepare("SELECT page_namespace, count(*) as 'count' FROM $dbName.`revision_userindex` r
RIGHT JOIN $dbName.page p on r.rev_page = p.page_id
WHERE r.rev_user = :id GROUP BY page_namespace");
            $resultQuery->bindParam(":id", $id);
            $resultQuery->execute();

            $namespaceTotal = 0;
            $namespaceArray = [];

            foreach ($resultQuery->fetchAll() as $row) {
                if ($colors[$row["page_namespace"]]) {
                    $color = $colors[$row["page_namespace"]];
                }
                else {
                    if ($colors2[$colorCounter2]) {
                        $color = $colors2[$colorCounter2];
                        $colorCounter2++;
                    }
                    else {
                        $color = $colors2[0];
                        $colorCounter2 = 1;
                    }
                }
                $namespaceArray[$row["page_namespace"]] = ['num'=>$row["count"], "color" => $color];
                $namespaceTotal += $row["count"];
            }

            /*for($i = 0; $i < (sizeof($namespaceArray) + 1); $i++) {
                $namespaceArray[$i]['pct'] = ($namespaceArray[$i]['num'] / 100);
            }*/
            
        }


        // -------------------------
        // Month and Year Counts
        // -------------------------


        // -------------------------
        // Timecard
        // -------------------------



        // -------------------------
        // Latest Global Edits
        // -------------------------



        // -------------------------
        // Top Edited Pages
        // -------------------------


        // -------------------------
        // Semi-automated edits
        // -------------------------


        return $this->render('editCounter/result.html.twig', [
            'title' => "tool_ec",
            'page' => "ec",
            'project' => $project,
            'username' => $username,
            'wiki' => $dbName,
            'name' => $wikiName,
            'url' => $url,
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
            'user_id' => $id,
            'user_groups' => $groups,
            'deleted_edits' => $arch,
            'total_edits' => $rev + $arch,
            'live_edits' => $rev,
            'first_rev' => $first_rev,
            'latest_rev' => $latest_rev,
            'days' => $days,
            'delta' => $delta,
            'rev_24h' => $rev_24h,
            'rev_7d' => $rev_7d,
            'rev_30d' => $rev_30d,
            'rev_365d' => $rev_365d,

            // General part 2
            'uniquePages' => $uniquePages,
            'pagesCreated' => $pagesCreated,
            'pagesMoved' => $pagesMoved,
            'editsPerPage' => $editsPerPage,

            // General part 3
            'pagesThanked' => $pagesThanked,
            'pagesApproved' => $pagesApproved,
            'pagesPatrolled' => $pagesPatrolled,
            'usersBlocked' => $usersBlocked,
            'usersUnblocked' => $usersUnblocked,
            'pagesProtected' => $pagesProtected,
            'pagesUnprotected' => $pagesUnrotected,
            'pagesDeleted' => $pagesDeleted,
            'pagesDeletedRevision' => $pagesDeletedRevision,
            'pagesRestored' => $pagesRestored,
            'pagesImported' => $pagesImported,

            // Namespace Totals
            'namespaceArray' => $namespaceArray,
            'namespaceTotal' => $namespaceTotal,
        ]);
    }
}
