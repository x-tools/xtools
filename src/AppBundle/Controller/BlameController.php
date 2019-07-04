<?php
declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Helper\I18nHelper;
use AppBundle\Model\Authorship;
use AppBundle\Model\Blame;
use AppBundle\Repository\BlameRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller provides the search form and results page for the Blame tool.
 * @codeCoverageIgnore
 */
class BlameController extends XtoolsController
{
    /**
     * Get the name of the tool's index route. This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'Blame';
    }

    /**
     * BlameController constructor.
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
     * @Route("/blame", name="Blame")
     * @Route("/blame/{project}", name="BlameProject")
     * @return Response
     */
    public function indexAction(): Response
    {
        $this->params['target'] = $this->request->query->get('target', '');

        if (isset($this->params['project']) && isset($this->params['page']) && isset($this->params['q'])) {
            return $this->redirectToRoute('BlameResult', $this->params);
        }

        if (preg_match('/\d{4}-\d{2}-\d{2}/', $this->params['target'])) {
            $show = 'date';
        } elseif (is_numeric($this->params['target'])) {
            $show = 'id';
        } else {
            $show = 'latest';
        }

        return $this->render('blame/index.html.twig', array_merge([
            'xtPage' => 'Blame',
            'xtPageTitle' => 'tool-blame',
            'xtSubtitle' => 'tool-blame-desc',
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
     *     "/blame/{project}/{page}/{target}",
     *     name="BlameResult",
     *     requirements={"target"="|latest|\d+|\d{4}-\d{2}-\d{2}"},
     *     defaults={"target"="latest"}
     * )
     * @param string $target
     * @return Response
     */
    public function resultAction(string $target): Response
    {
        if (!isset($this->params['q'])) {
            return $this->redirectToRoute('BlameProject', [
                'project' => $this->project->getDomain(),
            ]);
        }
        if (0 !== $this->page->getNamespace()) {
            $this->addFlashMessage('danger', 'error-authorship-non-mainspace');
            return $this->redirectToRoute('BlameProject', [
                'project' => $this->project->getDomain(),
            ]);
        }

        // This action sometimes requires more memory. 256M should be safe.
        ini_set('memory_limit', '256M');

        $blameRepo = new BlameRepository();
        $blameRepo->setContainer($this->container);
        $blame = new Blame($this->page, $this->params['q'], $target);
        $blame->setRepository($blameRepo);
        $blame->prepareData();

        return $this->getFormattedResponse('blame/blame', [
            'xtPage' => 'Blame',
            'xtTitle' => $this->page->getTitle(),
            'blame' => $blame,
        ]);
    }
}
