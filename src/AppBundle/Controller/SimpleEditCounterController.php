<?php
/**
 * This file contains only the SimpleEditCounterController class.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Helper\I18nHelper;
use AppBundle\Model\SimpleEditCounter;
use AppBundle\Repository\SimpleEditCounterRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

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
    public function getIndexRoute(): string
    {
        return 'SimpleEditCounter';
    }

    /**
     * SimpleEditCounterController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     * @param I18nHelper $i18n
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container, I18nHelper $i18n)
    {
        $this->tooHighEditCountAction = $this->getIndexRoute();
        $this->tooHighEditCountActionBlacklist = ['index', 'result'];

        parent::__construct($requestStack, $container, $i18n);
    }

    /**
     * The Simple Edit Counter search form.
     * @Route("/sc", name="SimpleEditCounter")
     * @Route("/sc/", name="SimpleEditCounterSlash")
     * @Route("/sc/index.php", name="SimpleEditCounterIndexPhp")
     * @Route("/sc/{project}", name="SimpleEditCounterProject")
     * @Route("/sc/{project}/", name="SimpleEditCounterProjectSlash")
     * @return Response
     */
    public function indexAction(): Response
    {
        // Redirect if project and username are given.
        if (isset($this->params['project']) && isset($this->params['username'])) {
            return $this->redirectToRoute('SimpleEditCounterResult', $this->params);
        }

        // Show the form.
        return $this->render('simpleEditCounter/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-simpleeditcounter',
            'xtSubtitle' => 'tool-simpleeditcounter-desc',
            'xtPage' => 'SimpleEditCounter',
            'project' => $this->project,

            // Defaults that will get overridden if in $params.
            'namespace' => 'all',
            'start' => '',
            'end' => '',
        ], $this->params, ['project' => $this->project]));
    }

    /**
     * Display the results.
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
    public function resultAction($namespace = 'all'): Response
    {
        $sec = new SimpleEditCounter(
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

        return $this->getFormattedResponse('simpleEditCounter/result', [
            'xtPage' => 'SimpleEditCounter',
            'xtTitle' => $this->user->getUsername(),
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
    public function simpleEditCounterApiAction($namespace = 'all'): Response
    {
        $this->recordApiUsage('user/simple_editcount');

        $sec = new SimpleEditCounter(
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

        return $this->getFormattedApiResponse($sec->getData());
    }
}
