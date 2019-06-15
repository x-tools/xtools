<?php
declare(strict_types = 1);

namespace AppBundle\Model;

/**
 * A GlobalContribs provides a list of a user's edits to all projects.
 */
class GlobalContribs extends Model
{
    /** @var int[] Keys are project DB names. */
    protected $globalEditCounts;

    /** @var array Most recent revisions across all projects. */
    protected $globalEdits;

    /** @var int Number of results per page. */
    public const PAGE_SIZE = 50;

    /**
     * GlobalContribs constructor.
     * @param User $user
     * @param string|int|null $namespace Namespace ID or 'all'.
     * @param false|int $start As Unix timestamp.
     * @param false|int $end As Unix timestamp.
     * @param int $offset
     */
    public function __construct(User $user, $namespace = 'all', $start = false, $end = false, int $offset = 0)
    {
        $this->user = $user;
        $this->namespace = '' == $namespace ? 0 : $namespace;
        $this->start = $start;
        $this->end = $end;
        $this->offset = $offset;
    }

    /**
     * Get the number of results to show per page.
     * @return int
     * @codeCoverageIgnore
     */
    public function getPageSize(): int
    {
        return self::PAGE_SIZE;
    }

    /**
     * Get the total edit counts for the top n projects of this user.
     * @param int $numProjects
     * @return mixed[] Each element has 'total' and 'project' keys.
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
     * @return mixed[] Each element has 'total' and 'project' keys.
     */
    public function globalEditCounts(bool $sorted = false): array
    {
        if (empty($this->globalEditCounts)) {
            $this->globalEditCounts = $this->getRepository()
                ->globalEditCounts($this->user, $this->project);
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
        return count($this->getRepository()->getProjectsWithEdits($this->user));
    }

    /**
     * Get the most recent $this->limit revisions across all projects, offset by $this->offset.
     * @return Edit[]
     */
    public function globalEdits(): array
    {
        if (is_array($this->globalEdits)) {
            return $this->globalEdits;
        }

        // Get projects with edits.
        $projects = $this->getRepository()->getProjectsWithEdits($this->user);
        if (0 === count($projects)) {
            return [];
        }

        // Get all revisions for those projects.
        $globalRevisionsData = $this->getRepository()
            ->getRevisions(
                array_keys($projects),
                $this->user,
                $this->namespace,
                $this->start,
                $this->end,
                self::PAGE_SIZE + 1,
                $this->offset
            );
        $globalEdits = [];

        foreach ($globalRevisionsData as $revision) {
            /** @var Project $project */
            $project = $projects[$revision['dbName']];

            // Can happen if the project is given from CentralAuth API but the database is not being replicated.
            if (null === $project) {
                continue;
            }

            $edit = $this->getEditFromRevision($project, $revision);
            $globalEdits[$edit->getTimestamp()->getTimestamp().'-'.$edit->getId()] = $edit;
        }

        // Sort and prune, before adding more.
        krsort($globalEdits);
        $this->globalEdits = array_slice($globalEdits, 0, self::PAGE_SIZE);

        return $this->globalEdits;
    }

    private function getEditFromRevision(Project $project, array $revision): Edit
    {
        $nsName = '';
        if ($revision['page_namespace']) {
            $nsName = $project->getNamespaces()[$revision['page_namespace']];
        }

        $page = $project->getRepository()
            ->getPage($project, ltrim($nsName.':'.$revision['page_title'], ':'));
        return new Edit($page, $revision);
    }
}
