<?php
/**
 * This file contains only the SimpleEditCounterController class.
 */

namespace AppBundle\Controller;

use Doctrine\DBAL\Connection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Xtools\SimpleEditCounter;
use Xtools\SimpleEditCounterRepository;

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
     * @return Response
     */
    public function indexAction()
    {
        // Redirect if project and username are given.
        if (isset($this->params['project']) && isset($this->params['username'])) {
            return $this->redirectToRoute('SimpleEditCounterResult', $this->params);
        }

        // Convert the given project (or default project) into a Project instance.
        $this->params['project'] = $this->getProjectFromQuery($this->params);

        // Show the form.
        return $this->render('simpleEditCounter/index.html.twig', [
            'xtPageTitle' => 'tool-sc',
            'xtSubtitle' => 'tool-sc-desc',
            'xtPage' => 'sc',
            'project' => $this->params['project'],

            // Defaults that will get overriden if in $params.
            'namespace' => 'all',
            'start' => '',
            'end' => '',
        ]);
    }

    /**
     * Display the
     * @Route(
     *     "/sc/{project}/{username}/{namespace}/{start}/{end}",
     *     name="SimpleEditCounterResult",
     *     requirements={
     *         "start" = "|\d{4}-\d{2}-\d{2}",
     *         "end" = "|\d{4}-\d{2}-\d{2}",
     *         "namespace" = "|all|\d+"
     *     }
     * )
     * @param int|string $namespace Namespace ID or 'all' for all namespaces.
     * @param null|string $start
     * @param null|string $end
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction($namespace = 'all', $start = false, $end = false)
    {
        $ret = $this->validateProjectAndUser();
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($project, $user) = $ret;
        }

        // 'false' means the dates are optional and returned as 'false' if empty.
        list($start, $end) = $this->getUTCFromDateParams($start, $end, false);

        $sec = new SimpleEditCounter($this->container, $project, $user, $namespace, $start, $end);
        $secRepo = new SimpleEditCounterRepository();
        $secRepo->setContainer($this->container);
        $sec->setRepository($secRepo);
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
     * @Route(
     *     "/api/user/simple_editcount/{project}/{username}/{namespace}/{start}/{end}",
     *     name="SimpleEditCounterApi",
     *     requirements={
     *         "start" = "|\d{4}-\d{2}-\d{2}",
     *         "end" = "|\d{4}-\d{2}-\d{2}",
     *         "namespace" = "|all|\d+"
     *     }
     * )
     * @param int|string $namespace Namespace ID or 'all' for all namespaces.
     * @param null|string $start
     * @param null|string $end
     * @return Response
     * @codeCoverageIgnore
     */
    public function simpleEditCounterApiAction(
        $namespace = 'all',
        $start = false,
        $end = false
    ) {
        $this->recordApiUsage('user/simple_editcount');

        // Here we do want to impose the max edit count restriction. Even though the
        // query is very 'simple', it can still run too slow for an API.
        $ret = $this->validateProjectAndUser('sc');
        if ($ret instanceof RedirectResponse) {
            return $ret;
        } else {
            list($project, $user) = $ret;
        }

        // 'false' means the dates are optional and returned as 'false' if empty.
        list($start, $end) = $this->getUTCFromDateParams($start, $end, false);

        $sec = new SimpleEditCounter($this->container, $project, $user, $namespace, $start, $end);
        $secRepo = new SimpleEditCounterRepository();
        $secRepo->setContainer($this->container);
        $sec->setRepository($secRepo);
        $sec->prepareData();

        $ret = [
            'username' => $user->getUsername(),
        ];

        if ($namespace !== 'all') {
            $ret['namespace'] = $namespace;
        }
        if ($start !== false) {
            $ret['start'] = date('Y-m-d', $start);
        }
        if ($end !== false) {
            $ret['end'] = date('Y-m-d', $end);
        }

        $ret = array_merge($ret, $sec->getData());

        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);
        $response->setData($ret);
        return $response;
    }
}
