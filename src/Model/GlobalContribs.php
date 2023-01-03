<?php

declare(strict_types = 1);

namespace App\Model;

use App\Repository\EditRepository;
use App\Repository\GlobalContribsRepository;
use App\Repository\PageRepository;
use App\Repository\UserRepository;

/**
 * A GlobalContribs provides a list of a user's edits to all projects.
 */
class GlobalContribs extends Model
{
    protected EditRepository $editRepo;
    protected PageRepository $pageRepo;
    protected UserRepository $userRepo;

    /** @var int Number of results per page. */
    public const PAGE_SIZE = 50;

    /** @var int[] Keys are project DB names. */
    protected array $globalEditCounts;

    /** @var array Most recent revisions across all projects. */
    protected array $globalEdits;

    /**
     * GlobalContribs constructor.
     * @param GlobalContribsRepository $repository
     * @param PageRepository $pageRepo
     * @param UserRepository $userRepo
     * @param EditRepository $editRepo
     * @param User $user
     * @param string|int|null $namespace Namespace ID or 'all'.
     * @param false|int $start As Unix timestamp.
     * @param false|int $end As Unix timestamp.
     * @param false|int $offset As Unix timestamp.
     * @param int|null $limit Number of results to return.
     */
    public function __construct(
        GlobalContribsRepository $repository,
        PageRepository $pageRepo,
        UserRepository $userRepo,
        EditRepository $editRepo,
        User $user,
        $namespace = 'all',
        $start = false,
        $end = false,
        $offset = false,
        ?int $limit = null
    ) {
        $this->repository = $repository;
        $this->pageRepo = $pageRepo;
        $this->userRepo = $userRepo;
        $this->editRepo = $editRepo;
        $this->user = $user;
        $this->namespace = '' == $namespace ? 0 : $namespace;
        $this->start = $start;
        $this->end = $end;
        $this->offset = $offset;
        $this->limit = $limit ?? self::PAGE_SIZE;
    }

    /**
     * Get the total edit counts for the top n projects of this user.
     * @param int $numProjects
     * @return array Each element has 'total' and 'project' keys.
     */
    public function globalEditCountsTopN(int $numProjects = 10): array
    {
        // Get counts.
        $editCounts = $this->globalEditCounts(true);
        // Truncate, and return.
        return array_slice($editCounts, 0, $numProjects);
    }

    /**
     * Get the total number of edits excluding the top n.
     * @param int $numProjects
     * @return int
     */
    public function globalEditCountWithoutTopN(int $numProjects = 10): int
    {
        $editCounts = $this->globalEditCounts(true);
        $bottomM = array_slice($editCounts, $numProjects);
        $total = 0;
        foreach ($bottomM as $editCount) {
            $total += $editCount['total'];
        }
        return $total;
    }

    /**
     * Get the grand total of all edits on all projects.
     * @return int
     */
    public function globalEditCount(): int
    {
        $total = 0;
        foreach ($this->globalEditCounts() as $editCount) {
            $total += $editCount['total'];
        }
        return $total;
    }

    /**
     * Get the total revision counts for all projects for this user.
     * @param bool $sorted Whether to sort the list by total, or not.
     * @return array[] Each element has 'total' and 'project' keys.
     */
    public function globalEditCounts(bool $sorted = false): array
    {
        if (!isset($this->globalEditCounts)) {
            $this->globalEditCounts = $this->repository->globalEditCounts($this->user);
        }

        if ($sorted) {
            // Sort.
            uasort($this->globalEditCounts, function ($a, $b) {
                return $b['total'] - $a['total'];
            });
        }

        return $this->globalEditCounts;
    }

    public function numProjectsWithEdits(): int
    {
        return count($this->repository->getProjectsWithEdits($this->user));
    }

    /**
     * Get the most recent revisions across all projects.
     * @return Edit[]
     */
    public function globalEdits(): array
    {
        if (isset($this->globalEdits)) {
            return $this->globalEdits;
        }

        // Get projects with edits.
        $projects = $this->repository->getProjectsWithEdits($this->user);
        if (0 === count($projects)) {
            return [];
        }

        // Get all revisions for those projects.
        $globalContribsRepo = $this->repository;
        $globalRevisionsData = $globalContribsRepo->getRevisions(
            array_keys($projects),
            $this->user,
            $this->namespace,
            $this->start,
            $this->end,
            $this->limit + 1,
            $this->offset
        );
        $globalEdits = [];

        foreach ($globalRevisionsData as $revision) {
            $project = $projects[$revision['dbName']];

            // Can happen if the project is given from CentralAuth API but the database is not being replicated.
            if (null === $project || !$project->exists()) {
                continue;
            }

            $edit = $this->getEditFromRevision($project, $revision);
            $globalEdits[$edit->getTimestamp()->getTimestamp().'-'.$edit->getId()] = $edit;
        }

        // Sort and prune, before adding more.
        krsort($globalEdits);
        $this->globalEdits = array_slice($globalEdits, 0, $this->limit);

        return $this->globalEdits;
    }

    private function getEditFromRevision(Project $project, array $revision): Edit
    {
        $page = Page::newFromRow($this->pageRepo, $project, $revision);
        return new Edit($this->editRepo, $this->userRepo, $page, $revision);
    }
}
