<?php

declare(strict_types = 1);

namespace App\Model;

use App\Repository\AutoEditsRepository;
use App\Repository\EditRepository;
use App\Repository\PageRepository;
use App\Repository\UserRepository;

/**
 * AutoEdits returns statistics about automated edits made by a user.
 */
class AutoEdits extends Model
{
    protected EditRepository $editRepo;
    protected PageRepository $pageRepo;
    protected UserRepository $userRepo;

    /** @var null|string The tool we're searching for when fetching (semi-)automated edits. */
    protected ?string $tool;

    /** @var Edit[] The list of non-automated contributions. */
    protected array $nonAutomatedEdits;

    /** @var Edit[] The list of automated contributions. */
    protected array $automatedEdits;

    /** @var int Total number of edits. */
    protected int $editCount;

    /** @var int Total number of non-automated edits. */
    protected int $automatedCount;

    /** @var array Counts of known automated tools used by the given user. */
    protected array $toolCounts;

    /** @var int Total number of edits made with the tools. */
    protected int $toolsTotal;

    /** @var int Default number of results to show per page when fetching (non-)automated edits. */
    public const RESULTS_PER_PAGE = 50;

    /**
     * Constructor for the AutoEdits class.
     * @param AutoEditsRepository $repository
     * @param EditRepository $editRepo
     * @param PageRepository $pageRepo
     * @param UserRepository $userRepo
     * @param Project $project
     * @param User $user
     * @param int|string $namespace Namespace ID or 'all'
     * @param false|int $start Start date as Unix timestamp.
     * @param false|int $end End date as Unix timestamp.
     * @param null $tool The tool we're searching for when fetching (semi-)automated edits.
     * @param false|int $offset Unix timestamp. Used for pagination.
     * @param int|null $limit Number of results to return.
     */
    public function __construct(
        AutoEditsRepository $repository,
        EditRepository $editRepo,
        PageRepository $pageRepo,
        UserRepository $userRepo,
        Project $project,
        User $user,
        $namespace = 0,
        $start = false,
        $end = false,
        $tool = null,
        $offset = false,
        ?int $limit = self::RESULTS_PER_PAGE
    ) {
        $this->repository = $repository;
        $this->editRepo = $editRepo;
        $this->pageRepo = $pageRepo;
        $this->userRepo = $userRepo;
        $this->project = $project;
        $this->user = $user;
        $this->namespace = $namespace;
        $this->start = $start;
        $this->end = $end;
        $this->tool = $tool;
        $this->offset = $offset;
        $this->limit = $limit ?? self::RESULTS_PER_PAGE;
    }

    /**
     * The tool we're limiting the results to when fetching
     * (semi-)automated contributions.
     * @return null|string
     */
    public function getTool(): ?string
    {
        return $this->tool;
    }

    /**
     * Get the raw edit count of the user.
     * @return int
     */
    public function getEditCount(): int
    {
        if (!isset($this->editCount)) {
            $this->editCount = $this->user->countEdits(
                $this->project,
                $this->namespace,
                $this->start,
                $this->end
            );
        }

        return $this->editCount;
    }

    /**
     * Get the number of edits this user made using semi-automated tools.
     * This is not the same as self::getToolCounts because the regex can overlap.
     * @return int Result of query, see below.
     */
    public function getAutomatedCount(): int
    {
        if (isset($this->automatedCount)) {
            return $this->automatedCount;
        }

        $this->automatedCount = $this->repository->countAutomatedEdits(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end
        );

        return $this->automatedCount;
    }

    /**
     * Get the percentage of all edits made using automated tools.
     * @return float
     */
    public function getAutomatedPercentage(): float
    {
        return $this->getEditCount() > 0
            ? ($this->getAutomatedCount() / $this->getEditCount()) * 100
            : 0;
    }

    /**
     * Get non-automated contributions for this user.
     * @param bool $forJson
     * @return string[]|Edit[]
     */
    public function getNonAutomatedEdits(bool $forJson = false): array
    {
        if (isset($this->nonAutomatedEdits)) {
            return $this->nonAutomatedEdits;
        }

        $revs = $this->repository->getNonAutomatedEdits(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end,
            $this->offset,
            $this->limit
        );

        $this->nonAutomatedEdits = Edit::getEditsFromRevs(
            $this->pageRepo,
            $this->editRepo,
            $this->userRepo,
            $this->project,
            $this->user,
            $revs
        );

        if ($forJson) {
            return array_map(function (Edit $edit) {
                return $edit->getForJson();
            }, $this->nonAutomatedEdits);
        }

        return $this->nonAutomatedEdits;
    }

    /**
     * Get automated contributions for this user.
     * @param bool $forJson
     * @return Edit[]
     */
    public function getAutomatedEdits(bool $forJson = false): array
    {
        if (isset($this->automatedEdits)) {
            return $this->automatedEdits;
        }

        $revs = $this->repository->getAutomatedEdits(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end,
            $this->tool,
            $this->offset,
            $this->limit
        );

        $this->automatedEdits = Edit::getEditsFromRevs(
            $this->pageRepo,
            $this->editRepo,
            $this->userRepo,
            $this->project,
            $this->user,
            $revs
        );

        if ($forJson) {
            return array_map(function (Edit $edit) {
                return $edit->getForJson();
            }, $this->automatedEdits);
        }

        return $this->automatedEdits;
    }

    /**
     * Get counts of known automated tools used by the given user.
     * @return array Each tool that they used along with the count and link:
     *                  [
     *                      'Twinkle' => [
     *                          'count' => 50,
     *                          'link' => 'Wikipedia:Twinkle',
     *                      ],
     *                  ]
     */
    public function getToolCounts(): array
    {
        if (isset($this->toolCounts)) {
            return $this->toolCounts;
        }

        $this->toolCounts = $this->repository->getToolCounts(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end
        );

        return $this->toolCounts;
    }

    /**
     * Get a list of all available tools for the Project.
     * @return array
     * Just passes along a repository result.
     * @codeCoverageIgnore
     */
    public function getAllTools(): array
    {
        return $this->repository->getTools($this->project);
    }

    /**
     * Get the combined number of edits made with each tool. This is calculated separately from
     * self::getAutomatedCount() because the regex can sometimes overlap, and the counts are actually different.
     * @return int
     */
    public function getToolsTotal(): int
    {
        if (!isset($this->toolsTotal)) {
            $this->toolsTotal = array_reduce($this->getToolCounts(), function ($a, $b) {
                return $a + $b['count'];
            });
        }

        return $this->toolsTotal;
    }

    /**
     * @return bool
     */
    public function getUseSandbox(): bool
    {
        return $this->repository->getUseSandbox();
    }
}
