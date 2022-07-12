<?php
/**
 * This file contains only the SimpleEditCounterController class.
 */

declare(strict_types=1);

namespace App\Controller;

use App\Model\SimpleEditCounter;
use App\Repository\SimpleEditCounterRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
     * The Simple Edit Counter search form.
     * @Route("/sc", name="SimpleEditCounter")
     * @Route("/sc/index.php", name="SimpleEditCounterIndexPhp")
     * @Route("/sc/{project}", name="SimpleEditCounterProject")
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

    private function prepareSimpleEditCounter(): SimpleEditCounter
    {
        $sec = new SimpleEditCounter(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end
        );
        $secRepo = new SimpleEditCounterRepository();
        $secRepo->setContainer($this->container);
        $sec->setRepository($secRepo);
        $sec->prepareData();

        if ($sec->isLimited()) {
            $this->addFlash('warning', $this->i18n->msg('simple-counter-limited-results'));
        }

        return $sec;
    }

    /**
     * Display the results.
     * @Route(
     *     "/sc/{project}/{username}/{namespace}/{start}/{end}",
     *     name="SimpleEditCounterResult",
     *     requirements={
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "namespace" = "|all|\d+",
     *         "start" = "|\d{4}-\d{2}-\d{2}",
     *         "end" = "|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={
     *         "start"=false,
     *         "end"=false,
     *         "namespace"="all",
     *     }
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(): Response
    {
        $sec = $this->prepareSimpleEditCounter();

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
     *         "username" = "(ipr-.+\/\d+[^\/])|([^\/]+)",
     *         "start" = "|\d{4}-\d{2}-\d{2}",
     *         "end" = "|\d{4}-\d{2}-\d{2}",
     *         "namespace" = "|all|\d+"
     *     },
     *     defaults={
     *         "start"=false,
     *         "end"=false,
     *         "namespace"="all",
     *     }
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function simpleEditCounterApiAction(): Response
    {
        $sec = $this->prepareSimpleEditCounter();
        $data = $sec->getData();
        if ($this->user->isIpRange()) {
            unset($data['deleted_edit_count']);
        }
        return $this->getFormattedApiResponse($data);
    }
}
