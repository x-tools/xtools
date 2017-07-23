<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\CssSelector\Exception\InternalErrorException;
use Symfony\Component\Debug\Exception\ContextErrorException;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Xtools\ProjectRepository;
use Xtools\RFA;
use Xtools\User;

// Note: In the legacy xTools, this tool was referred to as "rfap."
// Thus we have several references to it below, including in routes

class RfXVoteCalculatorController extends Controller
{

    /**
     * Get the tool's shortname.
     *
     * @return string
     */
    public function getToolShortname()
    {
        return 'rfap';
    }

    /**
     * @Route("/rfap", name="rfap")
     * @Route("/rfap", name="RfXVoteCalculator")
     * @return \Symfony\Component\HttpFoundation\Response]
     */
    public function indexAction()
    {
        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $projectQuery = $request->query->get('project');
        $username = $request->query->get('username');

        if ($projectQuery != "" && $username != "") {
            $routeParams = [ 'project'=>$projectQuery, 'username' => $username ];
            return $this->redirectToRoute(
                "rfapResult",
                $routeParams
            );
        } elseif ($projectQuery != "") {
            return $this->redirectToRoute(
                "rfapResult",
                [
                    'project'=>$projectQuery
                ]
            );
        }

        return $this->render(
            'rfxVoteCalculator/index.html.twig',
            [
                "xtPage" => "rfap",
            ]
        );
    }

    /**
     * @Route("/rfap/{project}/{username}", name="rfapResult")
     */
    public function resultAction($project, $username)
    {
        $api = $this->get("app.api_helper");

        $conn = $this->getDoctrine()->getManager("replicas")->getConnection();

        $projectData = ProjectRepository::getProject($project, $this->container);
        $userData = new User($username);

        $rfaParam = $this->getParameter("rfa");

        if (!$projectData->exists() || $rfaParam == null) {
            $this->addFlash("notice", ["invalid-project", $project]);
            return $this->redirectToRoute("rfap");
        }

        $namespaces = $projectData->getNamespaces();

        $pageTypes = $rfaParam[$projectData->getDatabaseName()]["pages"];
        $namespace
            = $rfaParam[$projectData->getDatabaseName()]["rfa_namespace"] !== null
            ? $rfaParam[$projectData->getDatabaseName()]["rfa_namespace"] : 4;

        $finalData = [];

        // We sould probably figure out a better way to do this...
        $ignoredPages = "";

        if (isset($rfaParam[$projectData->getDatabaseName()]["excluded_title"])) {
            foreach (
                $rfaParam[$projectData->getDatabaseName()]["excluded_title"]
                as $ignoredPage
            ) {
                $ignoredPages .= "AND p.page_title != \"$ignoredPage\"\r\n";
            }
        }

        if (isset($rfaParam[$projectData->getDatabaseName()]["excluded_regex"])) {
            foreach (
                $rfaParam[$projectData->getDatabaseName()]["excluded_regex"]
                as $ignoredPage
            ) {
                $ignoredPages .= "AND p.page_title NOT LIKE \"%$ignoredPage%\"\r\n";
            }
        }

        foreach ($pageTypes as $type) {
            $type = explode(":", $type, 2)[1];

            $type = str_replace(" ", "_", $type);

            $query = "SELECT DISTINCT p.page_namespace, p.page_title
FROM `page` p
RIGHT JOIN revision r on p.page_id=r.rev_page
WHERE p.page_namespace=:namespace
AND r.rev_user_text=:username
And p.page_title LIKE \"$type/%\"
AND p.page_title NOT LIKE \"%$type/$username%\"
$ignoredPages";

            $sth = $conn->prepare($query);
            $sth->bindParam("namespace", $namespace);
            $sth->bindParam("username", $username);

            $sth->execute();

            $titles = [];

            while ($row = $sth->fetch()) {
                $titles[] = $namespaces[$row["page_namespace"]] .
                    ":" .$row["page_title"];
            }

            // Chunking... it's possible to make a URI too long
            $titleArray = array_chunk($titles, 20);

            foreach ($titleArray as $titlesWorked) {
                $pageData = $api->getMassPageText($project, $titlesWorked);

                foreach ($pageData as $title => $text) {
                    $type = str_replace("_", " ", $type);
                    $rfa = new RFA(
                        $text,
                        $rfaParam[$projectData->getDatabaseName()]["sections"],
                        $namespaces[2],
                        $username
                    );
                    $section = $rfa->get_userSectionFound();
                    $finalData[$type][$section][$title]["Support"]
                        = sizeof($rfa->get_support());
                    $finalData[$type][$section][$title]["Oppose"]
                        = sizeof($rfa->get_oppose());
                    $finalData[$type][$section][$title]["Neutral"]
                        = sizeof($rfa->get_neutral());
                    $finalData[$type][$section][$title]["Date"]
                        = $rfa->get_enddate();
                    $finalData[$type][$section][$title]["name"]
                        = explode("/", $title)[1];

                    unset($rfa);
                }
            }

        }

        return $this->render(
            'rfxVoteCalculator/result.html.twig',
            [
                "xtPage" => "rfap",
                "xtTitle" => $username,
                "user" => $userData,
                "project" => $projectData,
                "data"=> $finalData
            ]
        );
    }
}
