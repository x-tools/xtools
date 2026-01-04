<?php

declare( strict_types=1 );

namespace App\Controller;

use App\Model\Authorship;
use App\Repository\AuthorshipRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller serves the search form and results for the Authorship tool
 * @codeCoverageIgnore
 */
class AuthorshipController extends XtoolsController {
	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getIndexRoute(): string {
		return 'Authorship';
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function supportedProjects(): array {
		return Authorship::SUPPORTED_PROJECTS;
	}

	/**
	 * The search form.
	 * @Route("/authorship", name="Authorship")
	 * @Route("/authorship/{project}", name="AuthorshipProject")
	 * @return Response
	 */
	public function indexAction(): Response {
		$this->params['target'] = $this->request->query->get( 'target', '' );

		if ( isset( $this->params['project'] ) && isset( $this->params['page'] ) ) {
			return $this->redirectToRoute( 'AuthorshipResult', $this->params );
		}

		if ( preg_match( '/\d{4}-\d{2}-\d{2}/', $this->params['target'] ) ) {
			$show = 'date';
		} elseif ( is_numeric( $this->params['target'] ) ) {
			$show = 'id';
		} else {
			$show = 'latest';
		}

		return $this->render( 'authorship/index.html.twig', array_merge( [
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
		] ) );
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
	 * @param AuthorshipRepository $authorshipRepo
	 * @param RequestStack $requestStack
	 * @return Response
	 */
	public function resultAction(
		string $target,
		AuthorshipRepository $authorshipRepo,
		RequestStack $requestStack
	): Response {
		if ( 0 !== $this->page->getNamespace() ) {
			$this->addFlashMessage( 'danger', 'error-authorship-non-mainspace' );
			return $this->redirectToRoute( 'AuthorshipProject', [
				'project' => $this->project->getDomain(),
			] );
		}

		// This action sometimes requires more memory. 256M should be safe.
		ini_set( 'memory_limit', '256M' );

		$isSubRequest = $this->request->get( 'htmlonly' ) || null !== $requestStack->getParentRequest();
		$limit = $isSubRequest ? 10 : ( $this->limit ?? 500 );

		$authorship = new Authorship( $authorshipRepo, $this->page, $target, $limit );
		$authorship->prepareData();

		return $this->getFormattedResponse( 'authorship/authorship', [
			'xtPage' => 'Authorship',
			'xtTitle' => $this->page->getTitle(),
			'authorship' => $authorship,
			'is_sub_request' => $isSubRequest,
		] );
	}
}
