<?php
/**
 * This file contains only the TopEdits class.
 */

declare(strict_types = 1);

namespace AppBundle\Model;

use AppBundle\Helper\AutomatedEditsHelper;

/**
 * TopEdits returns the top-edited pages by a user.
 */
class TopEdits extends Model
{
    /** @var string[]|Edit[] Top edits, either to a page or across namespaces. */
    protected $topEdits = [];

    /** @var int Number of bytes added across all top edits. */
    protected $totalAdded = 0;

    /** @var int Number of bytes removed across all top edits. */
    protected $totalRemoved = 0;

    /** @var int Number of top edits marked as minor. */
    protected $totalMinor = 0;

    /** @var int Number of automated top edits. */
    protected $totalAutomated = 0;

    /** @var int Number of reverted top edits. */
    protected $totalReverted = 0;

    private const DEFAULT_LIMIT_SINGLE_NAMESPACE = 1000;
    private const DEFAULT_LIMIT_ALL_NAMESPACES = 20;

    /**
     * TopEdits constructor.
     * @param Project $project
     * @param User $user
     * @param Page $page
     * @param string|int $namespace Namespace ID or 'all'.
     * @param int $limit Number of rows to fetch. This defaults to DEFAULT_LIMIT_SINGLE_NAMESPACE if $this->namespace
     *   is a single namespace (int), and DEFAULT_LIMIT_ALL_NAMESPACES if $this->namespace is 'all'.
     * @param int $offset Number of pages past the initial dataset. Used for pagination.
     */
    public function __construct(
        Project $project,
        User $user,
        ?Page $page = null,
        $namespace = 0,
        $limit = null,
        $offset = 0
    ) {
        $this->project = $project;
        $this->user = $user;
        $this->page = $page;
        $this->namespace = 'all' === $namespace ? 'all' : (int)$namespace;
        $this->offset = (int)$offset;

        if (null !== $limit) {
            $this->limit = (int)$limit;
        } else {
            $this->limit = 'all' === $this->namespace
                ? self::DEFAULT_LIMIT_ALL_NAMESPACES
                : self::DEFAULT_LIMIT_SINGLE_NAMESPACE;
        }
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
     * @return array|Edit[]
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
     * @param bool $format Whether to format the results, including stats for
     *     number of reverts, etc. This is set to false for the API endpoint.
     */
    public function prepareData(bool $format = true): void
    {
        if (isset($this->page)) {
            $this->topEdits = $this->getTopEditsPage($format);
        } else {
            $this->topEdits = $this->getTopEditsNamespace($format);
        }
    }

    /**
     * Get the top edits by a user in the given namespace, or 'all' namespaces.
     * @param bool $format Whether to format the results, including stats for
     *     number of reverts, etc. This is set to false for the API endpoint.
     * @return string[] Results keyed by namespace.
     */
    private function getTopEditsNamespace(bool $format): array
    {
        if ('all' === $this->namespace) {
            $pages = $this->getRepository()->getTopEditsAllNamespaces(
                $this->project,
                $this->user,
                $this->limit
            );
        } else {
            $pages = $this->getRepository()->getTopEditsNamespace(
                $this->project,
                $this->user,
                $this->namespace,
                $this->limit,
                $this->offset * $this->limit
            );
        }

        if ($format) {
            return $this->formatTopPagesNamespace($pages);
        } else {
            return $pages;
        }
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

        return (int)$this->getRepository()->countEditsNamespace($this->project, $this->user, $this->namespace);
    }

    /**
     * Get the top edits to the given page.
     * @param bool $format Whether to format the results, including stats for
     *     number of reverts, etc. This is set to false for the API endpoint.
     * @return Edit[]
     */
    private function getTopEditsPage(bool $format = true): array
    {
        $revs = $this->getRepository()->getTopEditsPage(
            $this->page,
            $this->user
        );

        if ($format) {
            return $this->formatTopEditsPage($revs);
        } else {
            return $revs;
        }
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

        /** @var AutomatedEditsHelper $aeh */
        $aeh = $this->getRepository()
            ->getContainer()
            ->get('app.automated_edits_helper');

        foreach ($revs as $revision) {
            // Check if the edit was reverted based on the edit summary of the following edit.
            // If so, update $revision so that when an Edit is instantiated, it will have the 'reverted' option set.
            if ($aeh->isRevert($revision['parent_comment'], $this->project)) {
                $revision['reverted'] = 1;
            }

            $edit = $this->getEditAndIncrementCounts($revision);

            $edits[] = $edit;
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
        $edit = new Edit($this->page, $revision);

        if ($edit->isAutomated($this->getRepository()->getContainer())) {
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
                $this->totalAdded += $revision['length_change'];
            } else {
                $this->totalRemoved += $revision['length_change'];
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
            $nsId = (int)$page['page_namespace'];

            // FIXME: needs refactoring, done in PagesController::getPagepileResult() and AppExtension::titleWithNs().
            if (0 === $nsId) {
                $page['page_title_ns'] = $page['page_title'];
            } else {
                $page['page_title_ns'] = (
                    $this->project->getNamespaces()[$page['page_namespace']] ?? ''
                ).':'.$page['page_title'];
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
