<?php
declare(strict_types=1);

namespace App\Controller;

use App\Helper\I18nHelper;
use App\Model\Authorship;
use App\Repository\AuthorshipRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller serves the search form and results for the Authorship tool
 * @codeCoverageIgnore
 */
class AuthorshipController extends XtoolsController
{
    /**
     * Get the name of the tool's index route. This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'Authorship';
    }

    /**
     * Authorship constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     * @param I18nHelper $i18n
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container, I18nHelper $i18n)
    {
        // Ensures the requested project is validated against Authorship::SUPPORTED_PROJECTS, and not any valid project.
        $this->supportedProjects = Authorship::SUPPORTED_PROJECTS;

        parent::__construct($requestStack, $container, $i18n);
    }

    /**
     * The search form.
     * @Route("/authorship", name="Authorship")
     * @Route("/authorship/{project}", name="AuthorshipProject")
     * @return Response
     */
    public function indexAction(): Response
    {
        $this->params['target'] = $this->request->query->get('target', '');

        if (isset($this->params['project']) && isset($this->params['page'])) {
            return $this->redirectToRoute('AuthorshipResult', $this->params);
        }

        if (preg_match('/\d{4}-\d{2}-\d{2}/', $this->params['target'])) {
            $show = 'date';
        } elseif (is_numeric($this->params['target'])) {
            $show = 'id';
        } else {
            $show = 'latest';
        }

        return $this->render('authorship/index.html.twig', array_merge([
            'xtPage' => 'Authorship',
            'xtPageTitle' => 'tool-authorship',
            'xtSubtitle' => 'tool-authorship-desc',
            'project' => $this->project,

            // Defaults that will get overridden if in $params.
            'page' => '',
            'supportedProjects' => Authorship::SUPPORTED_PROJECTS,
        ], $this->params, [
            'project' => $this->project,
            'show' => $show,
            'target' => '',
        ]));
    }

    /**
     * @Route(
     *     "/articleinfo-authorship/{project}/{page}",
     *     name="AuthorshipResultLegacy",
     *     requirements={
     *         "page"="(.+?)",
     *         "target"="|latest|\d+|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={"target"="latest"}
     * )
     * @Route(
     *     "/authorship/{project}/{page}/{target}",
     *     name="AuthorshipResult",
     *     requirements={
     *         "page"="(.+?)",
     *         "target"="|latest|\d+|\d{4}-\d{2}-\d{2}",
     *     },
     *     defaults={"target"="latest"}
     * )
     * @param string $target
     * @return Response
     */
    public function resultAction(string $target): Response
    {
        if (0 !== $this->page->getNamespace()) {
            $this->addFlashMessage('danger', 'error-authorship-non-mainspace');
            return $this->redirectToRoute('AuthorshipProject', [
                'project' => $this->project->getDomain(),
            ]);
        }

        // This action sometimes requires more memory. 256M should be safe.
        ini_set('memory_limit', '256M');

        $isSubRequest = $this->request->get('htmlonly')
            || null !== $this->get('request_stack')->getParentRequest();
        $limit = $isSubRequest ? 10 : ($this->limit ?? 500);

        $authorshipRepo = new AuthorshipRepository();
        $authorshipRepo->setContainer($this->container);
        $authorship = new Authorship($this->page, $target, $limit);
        $authorship->setRepository($authorshipRepo);
        $authorship->prepareData();

        return $this->getFormattedResponse('authorship/authorship', [
            'xtPage' => 'Authorship',
            'xtTitle' => $this->page->getTitle(),
            'authorship' => $authorship,
            'is_sub_request' => $isSubRequest,
        ]);
    }
}
