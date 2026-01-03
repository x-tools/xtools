<?php

declare( strict_types = 1 );

namespace App\Repository;

use App\Model\Edit;
use App\Model\Page;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * BlameRepository is responsible for retrieving authorship data about a single page.
 * @codeCoverageIgnore
 */
class BlameRepository extends AuthorshipRepository {
	/**
	 * @param ManagerRegistry $managerRegistry
	 * @param CacheItemPoolInterface $cache
	 * @param Client $guzzle
	 * @param LoggerInterface $logger
	 * @param ParameterBagInterface $parameterBag
	 * @param bool $isWMF
	 * @param int $queryTimeout
	 * @param EditRepository $editRepo
	 * @param UserRepository $userRepo
	 */
	public function __construct(
		protected ManagerRegistry $managerRegistry,
		protected CacheItemPoolInterface $cache,
		protected Client $guzzle,
		protected LoggerInterface $logger,
		protected ParameterBagInterface $parameterBag,
		protected bool $isWMF,
		protected int $queryTimeout,
		protected EditRepository $editRepo,
		protected UserRepository $userRepo
	) {
		parent::__construct( $managerRegistry, $cache, $guzzle, $logger, $parameterBag, $isWMF, $queryTimeout );
	}

	/**
	 * Get an Edit given the revision ID.
	 * @param Page $page Given so that the Edit will point to the same instance, rather than create a new Page.
	 * @param int $revId
	 * @return Edit|null null if not found.
	 */
	public function getEditFromRevId( Page $page, int $revId ): ?Edit {
		return $this->editRepo->getEditFromRevIdForPage( $this->userRepo, $page->getProject(), $revId, $page );
	}
}
