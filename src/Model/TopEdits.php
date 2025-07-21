<?php

declare(strict_types = 1);

namespace App\Model;

use App\Helper\AutomatedEditsHelper;
use App\Repository\TopEditsRepository;

/**
 * TopEdits returns the top-edited pages by a user.
 */
class TopEdits extends Model
{
    protected AutomatedEditsHelper $autoEditsHelper;

    /** @var string[]|Edit[] Top edits, either to a page or across namespaces. */
    protected array $topEdits = [];

    /** @var int Number of bytes added across all top edits. */
    protected int $totalAdded = 0;

    /** @var int Number of bytes removed across all top edits. */
    protected int $totalRemoved = 0;

    /** @var int Number of top edits marked as minor. */
    protected int $totalMinor = 0;

    /** @var int Number of automated top edits. */
    protected int $totalAutomated = 0;

    /** @var int Number of reverted top edits. */
    protected int $totalReverted = 0;

    /** @var int Which page of results to show. */
    protected int $pagination = 0;

    private const DEFAULT_LIMIT_SINGLE_NAMESPACE = 1000;
    private const DEFAULT_LIMIT_ALL_NAMESPACES = 20;

    /**
     * TopEdits constructor.
     * @param TopEditsRepository $repository
     * @param AutomatedEditsHelper $autoEditsHelper
     * @param Project $project
     * @param User $user
     * @param Page|null $page
     * @param string|int $namespace Namespace ID or 'all'.
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @param int|null $limit Number of rows to fetch. This defaults to DEFAULT_LIMIT_SINGLE_NAMESPACE if
     *   $this->namespace is a single namespace (int), and DEFAULT_LIMIT_ALL_NAMESPACES if $this->namespace is 'all'.
     * @param int $pagination Which page of results to show.
     */
    public function __construct(
        TopEditsRepository $repository,
        AutomatedEditsHelper $autoEditsHelper,
        Project $project,
        User $user,
        ?Page $page = null,
        $namespace = 0,
        $start = false,
        $end = false,
        ?int $limit = null,
        int $pagination = 0
    ) {
        $this->repository = $repository;
        $this->autoEditsHelper = $autoEditsHelper;
        $this->project = $project;
        $this->user = $user;
        $this->page = $page;
        $this->namespace = 'all' === $namespace ? 'all' : (int)$namespace;
        $this->start = $start;
        $this->end = $end;
        $this->pagination = $pagination;

        if (null !== $limit) {
            $this->limit = $limit;
        } else {
            $this->limit = 'all' === $this->namespace
                ? self::DEFAULT_LIMIT_ALL_NAMESPACES
                : self::DEFAULT_LIMIT_SINGLE_NAMESPACE;
        }
    }

    /**
     * Which page of results we're showing.
     * @return int
     */
    public function getPagination(): int
    {
        return $this->pagination;
    }

    /**
     * Get total number of bytes added.
     * @return int
     */
    public function getTotalAdded(): int
    {
        return $this->totalAdded;
    }

    /**
     * Get total number of bytes removed.
     * @return int
     */
    public function getTotalRemoved(): int
    {
        return $this->totalRemoved;
    }

    /**
     * Get total number of edits marked as minor.
     * @return int
     */
    public function getTotalMinor(): int
    {
        return $this->totalMinor;
    }

    /**
     * Get total number of automated edits.
     * @return int
     */
    public function getTotalAutomated(): int
    {
        return $this->totalAutomated;
    }

    /**
     * Get total number of edits that were reverted.
     * @return int
     */
    public function getTotalReverted(): int
    {
        return $this->totalReverted;
    }

    /**
     * Get the top edits data.
     * @return Edit[]
     */
    public function getTopEdits(): array
    {
        return $this->topEdits;
    }

    /**
     * Get the total number of top edits.
     * @return int
     */
    public function getNumTopEdits(): int
    {
        return count($this->topEdits);
    }

    /**
     * Get the WikiProject totals.
     * @param int Namespace ID.
     * @return string[]|int
     */
    public function getProjectTotals(int $ns) : array
    {
        $projectTotals = [];
        // List of pages for this namespace
        $rows = $this->topEdits[$ns];
        foreach ($rows as $row) {
            $num = $row["count"];
            // May be null or nonexistent for assessment-less pages
            $titles = $row["pap_project_title"] ?? "{}";
            // Had to use json to pass multiple values in SQL select
            foreach (json_decode($titles) as $projectName) {
                if (!array_key_exists($projectName, $projectTotals)) {
                    $projectTotals[$projectName] = $num;
                } else {
                    $projectTotals[$projectName] += $num;
                }
            }
        }
        arsort($projectTotals);
        $projectTotals = array_slice($projectTotals, 0, 10);
        return $projectTotals;
    }

    /**
     * Get this project's prefix for WikiProjects' pages.
     * Used to link.
     * @return string
     */
    public function getWikiprojectPrefix(): string
    {
        return $this->project
            ->getPageAssessments()
            ->getConfig()['wikiproject_prefix'];
    }

    /**
     * Get the average time between edits (in days).
     * @return float
     */
    public function getAtbe(): float
    {
        $firstDateTime = $this->topEdits[0]->getTimestamp();
        $lastDateTime = end($this->topEdits)->getTimestamp();
        $secs = $firstDateTime->getTimestamp() - $lastDateTime->getTimestamp();
        $days = $secs / (60 * 60 * 24);
        return $days / count($this->topEdits);
    }

    /**
     * Set the Page on the TopEdits instance.
     * @param Page $page
     */
    public function setPage(Page $page): void
    {
        $this->page = $page;
    }

    /**
     * Fetch and store all the data we need to show the TopEdits view.
     * This is the public method that should be called before using
     * the getter methods.
     */
    public function prepareData(): void
    {
        if (!$this->project->userHasOptedIn($this->user)) {
            $this->topEdits = [];
            return;
        }
        if (isset($this->page)) {
            $this->topEdits = $this->getTopEditsPage();
        } else {
            $this->topEdits = $this->getTopEditsNamespace();
        }
    }

    /**
     * Get the top edits by a user in the given namespace, or 'all' namespaces.
     * @return string[] Results keyed by namespace.
     */
    private function getTopEditsNamespace(): array
    {
        if ('all' === $this->namespace) {
            $pages = $this->repository->getTopEditsAllNamespaces(
                $this->project,
                $this->user,
                $this->start,
                $this->end,
                $this->limit
            );
        } else {
            $pages = $this->repository->getTopEditsNamespace(
                $this->project,
                $this->user,
                $this->namespace,
                $this->start,
                $this->end,
                $this->limit,
                $this->pagination
            );
        }

        return $this->formatTopPagesNamespace($pages);
    }

    /**
     * Get the total number of pages edited in the namespace.
     * @return int|null
     */
    public function getNumPagesNamespace(): ?int
    {
        if ('all' === $this->namespace) {
            return null;
        }

        return (int)$this->repository->countEditsNamespace(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end
        );
    }

    /**
     * Get the total number of pages edited in a given namespace.
     * @param int $ns
     * @return int|null
     */
    public function getNumPagesAnyNamespace(int $ns): ?int
    {
        return (int)$this->repository->countEditsNamespace(
            $this->project,
            $this->user,
            $ns,
            $this->start,
            $this->end
        );
    }

    /**
     * Get the top edits to the given page.
     * @return Edit[]
     */
    private function getTopEditsPage(): array
    {
        $revs = $this->repository->getTopEditsPage(
            $this->page,
            $this->user,
            $this->start,
            $this->end
        );

        return $this->formatTopEditsPage($revs);
    }

    /**
     * Format the results for top edits to a single page. This method also computes
     * totals for added/removed text, automated and reverted edits.
     * @param array[] $revs As returned by TopEditsRepository::getTopEditsPage.
     * @return Edit[]
     */
    private function formatTopEditsPage(array $revs): array
    {
        $edits = [];

        foreach ($revs as $revision) {
            // Check if the edit was reverted based on the edit summary of the following edit.
            // If so, update $revision so that when an Edit is instantiated, it will have the 'reverted' option set.
            if ($this->autoEditsHelper->isRevert($revision['parent_comment'], $this->project)) {
                $revision['reverted'] = 1;
            }

            $edits[] = $this->getEditAndIncrementCounts($revision);
        }

        return $edits;
    }

    /**
     * Create an Edit instance for the given revision, and increment running totals.
     * This is used by self::formatTopEditsPage().
     * @param string[] $revision Revision row as retrieved from the database.
     * @return Edit
     */
    private function getEditAndIncrementCounts(array $revision): Edit
    {
        $edit = $this->repository->getEdit($this->page, $revision);

        if ($edit->isAutomated()) {
            $this->totalAutomated++;
        }

        if ($edit->isMinor()) {
            $this->totalMinor++;
        }

        if ($edit->isReverted()) {
            $this->totalReverted++;
        } else {
            // Length changes don't count if they were reverted.
            if ($revision['length_change'] > 0) {
                $this->totalAdded += (int)$revision['length_change'];
            } else {
                $this->totalRemoved += (int)$revision['length_change'];
            }
        }

        return $edit;
    }

    /**
     * Format the results to be keyed by namespace.
     * @param array $pages As returned by TopEditsRepository::getTopEditsNamespace()
     *   or TopEditsRepository::getTopEditsAllNamespaces().
     * @return array Same results but keyed by namespace.
     */
    private function formatTopPagesNamespace(array $pages): array
    {
        /** @var string[] $topEditedPages The top edited pages, keyed by namespace ID. */
        $topEditedPages = [];

        foreach ($pages as $page) {
            $nsId = (int)$page['namespace'];
            $page['page_title'] = str_replace('_', ' ', $page['page_title']);

            // FIXME: needs refactoring, done in PagesController::getPagepileResult() and AppExtension::titleWithNs().
            if (0 === $nsId) {
                $page['full_page_title'] = $page['page_title'];
            } else {
                $page['full_page_title'] = str_replace('_', ' ', (
                    $this->project->getNamespaces()[$page['namespace']] ?? ''
                ).':'.$page['page_title']);
            }

            if (array_key_exists('pa_class', $page)) {
                $page['assessment'] = array_merge(
                    ['class' => $page['pa_class']],
                    $this->project->getPageAssessments()->getClassAttrs($page['pa_class'])
                );
                unset($page['pa_class']);
            }

            if (isset($topEditedPages[$nsId])) {
                $topEditedPages[$nsId][] = $page;
            } else {
                $topEditedPages[$nsId] = [$page];
            }
        }

        return $topEditedPages;
    }
}
