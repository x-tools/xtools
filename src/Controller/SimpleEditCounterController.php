<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\SimpleEditCounter;
use App\Repository\SimpleEditCounterRepository;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller handles the Simple Edit Counter tool.
 */
class SimpleEditCounterController extends XtoolsController
{

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'SimpleEditCounter';
    }

    /**
     * The Simple Edit Counter search form.
     */
    #[Route(path: '/sc', name: 'SimpleEditCounter')]
    #[Route(path: '/sc/index.php', name: 'SimpleEditCounterIndexPhp')]
    #[Route(path: '/sc/{project}', name: 'SimpleEditCounterProject')]
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

            // Defaults that will get overridden if in $params.
            'namespace' => 'all',
            'start' => '',
            'end' => '',
        ], $this->params, ['project' => $this->project]));
    }

    private function prepareSimpleEditCounter(SimpleEditCounterRepository $simpleEditCounterRepo): SimpleEditCounter
    {
        $sec = new SimpleEditCounter(
            $simpleEditCounterRepo,
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end
        );
        $sec->prepareData();

        if ($sec->isLimited()) {
            $this->addFlash('warning', $this->i18n->msg('simple-counter-limited-results'));
        }

        return $sec;
    }

    /**
     * Display the results.
     * @codeCoverageIgnore
     */
    #[Route(
        '/sc/result/{project}/{username}/{namespace}/{start}/{end}',
        name: 'SimpleEditCounterResult',
        requirements: [
            'username' => '(ipr-.+\/\d+[^\/])|([^\/]+)',
            'namespace' => '|all|\d+',
            'start' => '|\d{4}-\d{2}-\d{2}',
            'end' => '|\d{4}-\d{2}-\d{2}',
        ],
        defaults: [
            'start' => false,
            'end' => false,
            'namespace' => 'all',
        ]
    )]
    public function resultAction(SimpleEditCounterRepository $simpleEditCounterRepo): Response
    {
        $sec = $this->prepareSimpleEditCounter($simpleEditCounterRepo);

        return $this->getFormattedResponse('simpleEditCounter/result', [
            'xtPage' => 'SimpleEditCounter',
            'xtTitle' => $this->user->getUsername(),
            'sec' => $sec,
        ]);
    }

    /************************ API endpoints ************************/

    /**
     * API endpoint for the Simple Edit Counter.
     * @OA\Tag(name="User API")
     * @OA\Parameter(ref="#/components/parameters/Project")
     * @OA\Parameter(ref="#/components/parameters/UsernameOrIp")
     * @OA\Parameter(ref="#/components/parameters/Namespace")
     * @OA\Parameter(ref="#/components/parameters/Start")
     * @OA\Parameter(ref="#/components/parameters/End")
     * @OA\Response(
     *     response=200,
     *     description="Simple edit count, along with user groups and global user groups.",
     *     @OA\JsonContent(
     *         @OA\Property(property="project", ref="#/components/parameters/Project/schema"),
     *         @OA\Property(property="username", ref="#/components/parameters/UsernameOrIp/schema"),
     *         @OA\Property(property="namespace", ref="#/components/parameters/Namespace/schema"),
     *         @OA\Property(property="start", ref="#components/parameters/Start/schema"),
     *         @OA\Property(property="end", ref="#components/parameters/End/schema"),
     *         @OA\Property(property="user_id", type="integer"),
     *         @OA\Property(property="live_edit_count", type="integer"),
     *         @OA\Property(property="deleted_edit_count", type="integer"),
     *         @OA\Property(property="user_groups", type="array", @OA\Items(type="string")),
     *         @OA\Property(property="global_user_groups", type="array", @OA\Items(type="string")),
     *         @OA\Property(property="elapsed_time", ref="#/components/schemas/elapsed_time")
     *     )
     * )
     * @OA\Response(response=404, ref="#/components/responses/404")
     * @OA\Response(response=503, ref="#/components/responses/503")
     * @OA\Response(response=504, ref="#/components/responses/504")
     * @codeCoverageIgnore
     */
    #[Route(
        '/api/user/simple_editcount/{project}/{username}/{namespace}/{start}/{end}',
        name: 'SimpleEditCounterApi',
        requirements: [
            'username' => '(ipr-.+\/\d+[^\/])|([^\/]+)',
            'namespace' => '|all|\d+',
            'start' => '|\d{4}-\d{2}-\d{2}',
            'end' => '|\d{4}-\d{2}-\d{2}',
        ],
        defaults: [
            'start' => false,
            'end' => false,
            'namespace' => 'all',
        ],
        methods: ['GET']
    )]
    public function simpleEditCounterApiAction(SimpleEditCounterRepository $simpleEditCounterRepository): JsonResponse
    {
        $this->recordApiUsage('user/simple_editcount');
        $sec = $this->prepareSimpleEditCounter($simpleEditCounterRepository);
        $data = $sec->getData();
        if ($this->user->isIpRange()) {
            unset($data['deleted_edit_count']);
        }
        return $this->getFormattedApiResponse($data);
    }
}
