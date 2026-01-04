<?php

declare( strict_types=1 );

namespace App\Controller;

use App\Model\Authorship;
use App\Model\Blame;
use App\Repository\BlameRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller provides the search form and results page for the Blame tool.
 * @codeCoverageIgnore
 */
class BlameController extends XtoolsController {
	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getIndexRoute(): string {
		return 'Blame';
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
	 * @Route("/blame", name="Blame")
	 * @Route("/blame/{project}", name="BlameProject")
	 * @return Response
	 */
	public function indexAction(): Response {
		$this->params['target'] = $this->request->query->get( 'target', '' );

		if ( isset( $this->params['project'] ) && isset( $this->params['page'] ) && isset( $this->params['q'] ) ) {
			return $this->redirectToRoute( 'BlameResult', $this->params );
		}

		if ( preg_match( '/\d{4}-\d{2}-\d{2}/', $this->params['target'] ) ) {
			$show = 'date';
		} elseif ( is_numeric( $this->params['target'] ) ) {
			$show = 'id';
		} else {
			$show = 'latest';
		}

		return $this->render( 'blame/index.html.twig', array_merge( [
			'xtPage' => 'Blame',
			'xtPageTitle' => 'tool-blame',
			'xtSubtitle' => 'tool-blame-desc',

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
	 *     "/blame/{project}/{page}/{target}",
	 *     name="BlameResult",
	 *     requirements={
	 *         "page"="(.+?)",
	 *         "target"="|latest|\d+|\d{4}-\d{2}-\d{2}",
	 *     },
	 *     defaults={"target"="latest"}
	 * )
	 * @param string $target
	 * @param BlameRepository $blameRepo
	 * @return Response
	 */
	public function resultAction( string $target, BlameRepository $blameRepo ): Response {
		if ( !isset( $this->params['q'] ) ) {
			return $this->redirectToRoute( 'BlameProject', [
				'project' => $this->project->getDomain(),
			] );
		}
		if ( 0 !== $this->page->getNamespace() ) {
			$this->addFlashMessage( 'danger', 'error-authorship-non-mainspace' );
			return $this->redirectToRoute( 'BlameProject', [
				'project' => $this->project->getDomain(),
			] );
		}

		// This action sometimes requires more memory. 256M should be safe.
		ini_set( 'memory_limit', '256M' );

		$blame = new Blame( $blameRepo, $this->page, $this->params['q'], $target );
		$blame->setRepository( $blameRepo );
		$blame->prepareData();

		return $this->getFormattedResponse( 'blame/blame', [
			'xtPage' => 'Blame',
			'xtTitle' => $this->page->getTitle(),
			'blame' => $blame,
		] );
	}
}
