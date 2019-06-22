<?php
declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Model\Authorship;
use AppBundle\Repository\AuthorshipRepository;
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
     *     defaults={"target"="latest"}
     * )
     * @Route(
     *     "/authorship/{project}/{page}/{target}",
     *     name="AuthorshipResult",
     *     requirements={"target"="|latest|\d+|\d{4}-\d{2}-\d{2}"},
     *     defaults={"target"="latest"}
     * )
     * @param string $target
     * @return Response
     */
    public function resultAction(string $target): Response
    {
        if (0 !== $this->page->getNamespace()) {
            $this->addFlashMessage('danger', 'error-authorship-non-mainspace');
            return $this->redirectToRoute('Authorship', [
                'project' => $this->project,
            ]);
        }
        if (!in_array($this->project->getDomain(), Authorship::SUPPORTED_PROJECTS)) {
            $this->addFlashMessage('danger', 'error-authorship-unsupported-project', [
                $this->project->getDomain(),
            ]);
            return $this->redirectToRoute('Authorship', [
                'project' => $this->project,
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
