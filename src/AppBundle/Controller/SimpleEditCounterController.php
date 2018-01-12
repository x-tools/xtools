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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Xtools\SimpleEditCounter;

/**
 * This controller handles the Simple Edit Counter tool.
 */
class SimpleEditCounterController extends XtoolsController
{

    /**
     * Get the tool's shortname.
     * @return string
     * @codeCoverageIgnore
     */
    public function getToolShortname()
    {
        return 'sc';
    }

    /**
     * The Simple Edit Counter search form.
     * @Route("/sc", name="sc")
     * @Route("/sc", name="SimpleEditCounter")
     * @Route("/sc/", name="SimpleEditCounterSlash")
     * @Route("/sc/index.php", name="SimpleEditCounterIndexPhp")
     * @Route("/sc/{project}", name="SimpleEditCounterProject")
     * @param Request $request The HTTP request.
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $params = $this->parseQueryParams($request);

        // Redirect if project and username are given.
        if (isset($params['project']) && isset($params['username'])) {
            return $this->redirectToRoute('SimpleEditCounterResult', $params);
        }

        // Convert the given project (or default project) into a Project instance.
        $params['project'] = $this->getProjectFromQuery($params);

        // Show the form.
        return $this->render('simpleEditCounter/index.html.twig', [
            'xtPageTitle' => 'tool-sc',
            'xtSubtitle' => 'tool-sc-desc',
            'xtPage' => 'sc',
            'project' => $params['project'],
        ]);
    }

    /**
     * Display the
     * @Route("/sc/{project}/{username}", name="SimpleEditCounterResult")
     * @param Request $request The HTTP request.
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request)
    {
        $ret = $this->validateProjectAndUser($request);
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($project, $user) = $ret;
        }

        $sec = new SimpleEditCounter($this->container, $project, $user);
        $sec->prepareData();

        // Assign the values and display the template
        return $this->render('simpleEditCounter/result.html.twig', [
            'xtPage' => 'sc',
            'xtTitle' => $user->getUsername(),
            'user' => $user,
            'project' => $project,
            'sec' => $sec,
        ]);
    }

    /************************ API endpoints ************************/

    /**
     * API endpoint for the Simple Edit Counter.
     * @Route("/api/user/simple_editcount/{project}/{username}", name="SimpleEditCounterApi")
     * @param Request $request
     * @return Response
     * @codeCoverageIgnore
     */
    public function simpleEditCounterApiAction(Request $request)
    {
        $this->recordApiUsage('user/simple_editcount');

        // Here we do want to impose the max edit count restriction. Even though the
        // query is very 'simple', it can still run too slow for an API.
        $ret = $this->validateProjectAndUser($request, 'sc');
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($project, $user) = $ret;
        }

        $sec = new SimpleEditCounter($this->container, $project, $user);
        $sec->prepareData();

        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);
        $response->setData($sec->getData());
        return $response;
    }
}
