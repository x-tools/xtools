<?php
/**
 * This file contains only the SimpleEditCounterController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Xtools\SimpleEditCounter;
use Xtools\SimpleEditCounterRepository;

/**
 * This controller handles the Simple Edit Counter tool.
 */
class SimpleEditCounterController extends XtoolsController
{

    /**
     * Get the name of the tool's index route.
     * This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute()
    {
        return 'SimpleEditCounter';
    }

    /**
     * SimpleEditCounterController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container)
    {
        $this->tooHighEditCountAction = $this->getIndexRoute();
        $this->tooHighEditCountActionBlacklist = ['index', 'result'];

        parent::__construct($requestStack, $container);
    }

    /**
     * The Simple Edit Counter search form.
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

        // Show the form.
        return $this->render('simpleEditCounter/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-simpleeditcounter',
            'xtSubtitle' => 'tool-simpleeditcounter-desc',
            'xtPage' => 'simpleeditcounter',
            'project' => $this->project,

            // Defaults that will get overriden if in $params.
            'namespace' => 'all',
            'start' => '',
            'end' => '',
        ], $this->params));
    }

    /**
     * Display the
     * @Route(
     *     "/sc/{project}/{username}/{namespace}/{start}/{end}",
     *     name="SimpleEditCounterResult",
     *     requirements={
     *         "start" = "|\d{4}-\d{2}-\d{2}",
     *         "end" = "|\d{4}-\d{2}-\d{2}",
     *         "namespace" = "|all|\d+",
     *     },
     *     defaults={
     *         "start"=false,
     *         "end"=false,
     *     }
     * )
     * @param int|string $namespace Namespace ID or 'all' for all namespaces.
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction($namespace = 'all')
    {
        $sec = new SimpleEditCounter(
            $this->container,
            $this->project,
            $this->user,
            $namespace,
            $this->start,
            $this->end
        );
        $secRepo = new SimpleEditCounterRepository();
        $secRepo->setContainer($this->container);
        $sec->setRepository($secRepo);
        $sec->prepareData();

        // Assign the values and display the template
        return $this->render('simpleEditCounter/result.html.twig', [
            'xtPage' => 'simpleeditcounter',
            'xtTitle' => $this->user->getUsername(),
            'user' => $this->user,
            'project' => $this->project,
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
     *         "username" = "(.+?)(?!(?:\/(\d+))?\/(?:\d{4}-\d{2}-\d{2})(?:\/(\d{4}-\d{2}-\d{2}))?)?$",
     *         "start" = "|\d{4}-\d{2}-\d{2}",
     *         "end" = "|\d{4}-\d{2}-\d{2}",
     *         "namespace" = "|all|\d+"
     *     },
     *     defaults={
     *         "start"=false,
     *         "end"=false,
     *     }
     * )
     * @param int|string $namespace Namespace ID or 'all' for all namespaces.
     * @return Response
     * @codeCoverageIgnore
     */
    public function simpleEditCounterApiAction($namespace = 'all')
    {
        $this->recordApiUsage('user/simple_editcount');

        $sec = new SimpleEditCounter(
            $this->container,
            $this->project,
            $this->user,
            $namespace,
            $this->start,
            $this->end
        );
        $secRepo = new SimpleEditCounterRepository();
        $secRepo->setContainer($this->container);
        $sec->setRepository($secRepo);
        $sec->prepareData();

        $ret = [
            'username' => $this->user->getUsername(),
        ];

        if ($namespace !== 'all') {
            $ret['namespace'] = $namespace;
        }
        if ($this->start !== false) {
            $ret['start'] = date('Y-m-d', $this->start);
        }
        if ($this->end !== false) {
            $ret['end'] = date('Y-m-d', $this->end);
        }

        $ret = array_merge($ret, $sec->getData());

        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);
        $response->setData($ret);
        return $response;
    }
}
