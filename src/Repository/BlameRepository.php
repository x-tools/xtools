<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Model\Edit;
use App\Model\Page;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * BlameRepository is responsible for retrieving authorship data about a single page.
 * @codeCoverageIgnore
 */
class BlameRepository extends AuthorshipRepository
{
    protected EditRepository $editRepo;
    protected UserRepository $userRepo;

    public function __construct(
        ContainerInterface $container,
        CacheItemPoolInterface $cache,
        Client $guzzle,
        LoggerInterface $logger,
        bool $isWMF,
        int $queryTimeout,
        EditRepository $editRepo,
        UserRepository $userRepo
    ) {
        parent::__construct($container, $cache, $guzzle, $logger, $isWMF, $queryTimeout);
        $this->editRepo = $editRepo;
        $this->userRepo = $userRepo;
    }

    /**
     * Get an Edit given the revision ID.
     * @param Page $page Given so that the Edit will point to the same instance, rather than create a new Page.
     * @param int $revId
     * @return Edit|null null if not found.
     */
    public function getEditFromRevId(Page $page, int $revId): ?Edit
    {
        return $this->editRepo->getEditFromRevIdForPage($this->userRepo, $page->getProject(), $revId, $page);
    }
}
