<?php
declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Edit;
use AppBundle\Model\Page;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * BlameRepository is responsible for retrieving authorship data about a single page.
 * @codeCoverageIgnore
 */
class BlameRepository extends AuthorshipRepository
{
    /** @var EditRepository Instance of EditRepository. */
    protected $editRepo;

    /**
     * Set the EditRepository once the container is available.
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container): void
    {
        parent::setContainer($container);
        $this->editRepo = new EditRepository();
        $this->editRepo->setContainer($this->container);
    }

    /**
     * Get an Edit given the revision ID.
     * @param Page $page Given so that the Edit will point to the same instance, rather than create a new Page.
     * @param int $revId
     * @return Edit|null null if not found.
     */
    public function getEditFromRevId(Page $page, int $revId): ?Edit
    {
        return $this->editRepo->getEditFromRevIdForPage($page->getProject(), $revId, $page);
    }
}
