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

    /** @var false|int Start of time period as Unix timestamp. */
    protected $start;

    /** @var false|int End of time period as Unix timestamp. */
    protected $end;

    /** @var false|int Unix timestamp to offset results which acts as a substitute for $end */
    protected $offset;

    /** @var int Number of rows to fetch. */
    protected $limit;

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
     * @return User|null
     */
    public function getUser(): ?User
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
     * Get date opening date range, formatted as this is used in the views.
     * @return string Blank if no value exists.
     */
    public function getStartDate(): string
    {
        return is_int($this->start) ? date('Y-m-d', $this->start) : '';
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
     * Get date closing date range, formatted as this is used in the views.
     * @return string Blank if no value exists.
     */
    public function getEndDate(): string
    {
        return is_int($this->end) ? date('Y-m-d', $this->end) : '';
    }

    /**
     * Has date range?
     * @return bool
     */
    public function hasDateRange(): bool
    {
        return $this->start || $this->end;
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
     * Get the offset timestamp as Unix timestamp. Used for pagination.
     * @return false|int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Get the offset timestamp as a formatted ISO timestamp.
     * @return null|string
     */
    public function getOffsetISO(): ?string
    {
        return is_int($this->offset) ? date('Y-m-d\TH:i:s', $this->offset) : null;
    }
}
