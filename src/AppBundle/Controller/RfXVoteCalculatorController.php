<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Xtools\ProjectRepository;

// Note: In the legacy xTools, this tool was referred to as "rfap."
// Thus we have several references to it below, including in routes

class RfXVoteCalculatorController extends Controller
{

    /**
     * Get the tool's shortname.
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
            return $this->redirectToRoute("rfapResult", $routeParams);
        } elseif ($projectQuery != "") {
            return $this->redirectToRoute("rfapResult", [ 'project'=>$projectQuery ]);
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

        $projectData = ProjectRepository::getProject($project, $this->container);

        if (!$projectData->exists()) {
            $this->addFlash("notice", ["invalid-project", $project]);
            return $this->redirectToRoute("rfap");
        }
        return $this->render(
            'rfxVoteCalculator/result.html.twig',
            [
                "xtPage" => "rfap",
                "xtTitle" => $username,
            ]
        );
    }
}
