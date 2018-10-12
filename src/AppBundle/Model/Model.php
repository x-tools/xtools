<?php
/**
 * This file contains only the Model class.
 */

declare(strict_types = 1);

namespace AppBundle\Model;

use AppBundle\Repository\Repository;
use Exception;

/**
 * A model is any domain-side entity to be represented in the application.
 * Models know nothing of persistence, transport, or presentation.
 */
abstract class Model
{
    /**
     * Below are the class properties. Some subclasses may not use all of these.
     */

    /** @var Repository The repository for this model. */
    private $repository;

    /** @var Project The project. */
    protected $project;

    /** @var User The user. */
    protected $user;

    /** @var Page the page associated with this edit */
    protected $page;

    /** @var int|string Which namespace we are querying for. 'all' for all namespaces. */
    protected $namespace;

    /** @var false|int|string Start of time period as UTC timestamp, or YYYY-MM-DD format. */
    protected $start;

    /** @var false|int|string End of time period as UTC timestamp, or YYYY-MM-DD format. */
    protected $end;

    /** @var int Number of rows to fetch. */
    protected $limit;

    /** @var int Number of rows to OFFSET, used for pagination. */
    protected $offset;

    /**
     * Set this model's data repository.
     * @param Repository $repository
     */
    public function setRepository(Repository $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * Get this model's repository.
     * @return Repository A subclass of Repository.
     * @throws Exception If the repository hasn't been set yet.
     */
    public function getRepository(): Repository
    {
        if (!$this->repository instanceof Repository) {
            $msg = sprintf('Repository for %s must be set before using.', static::class);
            throw new Exception($msg);
        }
        return $this->repository;
    }

    /**
     * Get the associated Project.
     * @return Project
     */
    public function getProject(): Project
    {
        return $this->project;
    }

    /**
     * Get the associated User.
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Get the associated Page.
     * @return Page
     */
    public function getPage(): Page
    {
        return $this->page;
    }

    /**
     * Get the associated namespace.
     * @return int|string Namespace ID or 'all' for all namespaces.
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Get date opening date range as Unix timestamp.
     * @return false|int
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Get date closing date range as Unix timestamp.
     * @return false|int
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * Has date range?
     * @return bool
     */
    public function hasDateRange(): bool
    {
        return '' != $this->start || '' != $this->end;
    }

    /**
     * Get the limit set on number of rows to fetch.
     * @return int
     */
    public function getLimit(): int
    {
        return (int)$this->limit;
    }

    /**
     * Get the number of rows to OFFSET, used for pagination.
     * @return int
     */
    public function getOffset(): int
    {
        return (int)$this->offset;
    }
}
