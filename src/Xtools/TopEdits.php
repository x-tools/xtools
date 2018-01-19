<?php
/**
 * This file contains only the TopEdits class.
 */

namespace Xtools;

use DateTime;

/**
 * TopEdits returns the top-edited pages by a user.
 */
class TopEdits extends Model
{
    /** @var Project The project. */
    protected $project;

    /** @var User The user. */
    protected $user;

    /** @var Page The page (if applicable). */
    protected $page;

    /** @var string[]|Edit[] Top edits, either to a page or across namespaces. */
    protected $topEdits = [];

    /** @var int Number of rows to fetch. */
    protected $limit;

    /** @var int Which namespace we are querying for. */
    protected $namespace;

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

    const DEFAULT_LIMIT_SINGLE_NAMESPACE = 100;
    const DEFAULT_LIMIT_ALL_NAMESPACES = 20;

    /**
     * TopEdits constructor.
     * @param Project $project
     * @param User $user
     * @param Page $page
     * @param string|int Namespace ID or 'all'.
     * @param int $limit Number of rows to fetch. This defaults to
     *   DEFAULT_LIMIT_SINGLE_NAMESPACE if $this->namespace is a single namespace (int),
     *   and DEFAULT_LIMIT_ALL_NAMESPACES if $this->namespace is 'all'.
     */
    public function __construct(Project $project, User $user, Page $page = null, $namespace = 0, $limit = null)
    {
        $this->project = $project;
        $this->user = $user;
        $this->page = $page;
        $this->namespace = $namespace === 'all' ? 'all' : (int)$namespace;

        if ($limit) {
            $this->limit = $limit;
        } else {
            $this->limit = $this->namespace === 'all'
                ? self::DEFAULT_LIMIT_ALL_NAMESPACES
                : self::DEFAULT_LIMIT_SINGLE_NAMESPACE;
        }
    }

    /**
     * Get the limit set on number of rows to fetch.
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Get the namespace set on the instance.
     * @return int|string Namespace ID or 'all'.
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Get total number of bytes added.
     * @return int
     */
    public function getTotalAdded()
    {
        return $this->totalAdded;
    }

    /**
     * Get total number of bytes removed.
     * @return int
     */
    public function getTotalRemoved()
    {
        return $this->totalRemoved;
    }

    /**
     * Get total number of edits marked as minor.
     * @return int
     */
    public function getTotalMinor()
    {
        return $this->totalMinor;
    }

    /**
     * Get total number of automated edits.
     * @return int
     */
    public function getTotalAutomated()
    {
        return $this->totalAutomated;
    }

    /**
     * Get total number of edits that were reverted.
     * @return int
     */
    public function getTotalReverted()
    {
        return $this->totalReverted;
    }

    /**
     * Get the top edits data.
     * @return string[]|Edit[]
     */
    public function getTopEdits()
    {
        return $this->topEdits;
    }

    /**
     * Get the total number of top edits.
     * @return int
     */
    public function getNumTopEdits()
    {
        return count($this->topEdits);
    }

    /**
     * Get the averate time between edits (in days).
     * @return double
     */
    public function getAtbe()
    {
        $firstDateTime = $this->topEdits[0]->getTimestamp();
        $lastDateTime = end($this->topEdits)->getTimestamp();
        $secs = $firstDateTime->getTimestamp() - $lastDateTime->getTimestamp();
        $days = $secs / (60 * 60 * 24);
        return $days / count($this->topEdits);
    }

    /**
     * Fetch and store all the data we need to show the TopEdits view.
     * This is the public method that should be called before using
     * the getter methods.
     */
    public function prepareData()
    {
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
    private function getTopEditsNamespace()
    {
        if ($this->namespace === 'all') {
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
                $this->limit
            );
        }

        return $this->formatTopPagesNamespace($pages);
    }

    /**
     * Get the top edits to the given page.
     * @return Edit[]
     */
    private function getTopEditsPage()
    {
        $revs = $this->getRepository()->getTopEditsPage(
            $this->page,
            $this->user
        );

        return $this->formatTopEditsPage($revs);
    }

    /**
     * Format the results for top edits to a single page. This method also computes
     * totals for added/removed text, automated and reverted edits.
     * @param  string[] $revs As returned by TopEditsRepository::getTopEditsPage.
     * @return Edit[]
     */
    private function formatTopEditsPage($revs)
    {
        $edits = [];

        $aeh = $this->getRepository()
            ->getContainer()
            ->get('app.automated_edits_helper');

        foreach ($revs as $revision) {
            // Check if the edit was reverted based on the edit summary of the following edit.
            // If so, update $revision so that when an Edit is instantiated, it will
            // have the 'reverted' option set.
            if ($aeh->isRevert($revision['parent_comment'], $this->project->getDomain())) {
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
     * @param  string[] $revision Revision row as retrieved from the database.
     * @return Edit
     */
    private function getEditAndIncrementCounts($revision)
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
     * Format the results returned from the database.
     * @param  string[] $pages As returned by TopEditsRepository::getTopEditsNamespace
     *                         or TopEditsRepository::getTopEditsAllNamespaces.
     * @return string[] Same as input but with 'displaytitle' and 'page_title_ns'.
     */
    private function formatTopPagesNamespace($pages)
    {
        /** @var string[] The top edited pages, keyed by namespace ID. */
        $topEditedPages = [];

        /** @var string[] Display titles of the pages, which need to be fetched ahead of time. */
        $displayTitles = $this->getDisplayTitles($pages);

        foreach ($pages as $page) {
            $nsId = (int) $page['page_namespace'];
            $nsTitle = $nsId > 0 ? $this->project->getNamespaces()[$page['page_namespace']] . ':' : '';
            $pageTitle = $nsTitle . $page['page_title'];
            $page['displaytitle'] = $displayTitles[$pageTitle];

            // $page['page_title'] is retained without the namespace
            //  so we can link to TopEdits for that page.
            $page['page_title_ns'] = $pageTitle;

            if (isset($topEditedPages[$nsId])) {
                $topEditedPages[$nsId][] = $page;
            } else {
                $topEditedPages[$nsId] = [$page];
            }
        }

        return $topEditedPages;
    }

    /**
     * Get the display titles of the given pages.
     * @param  string[] $topPages As returned by $this->getRepository()->getTopEdits()
     * @return string[] Keys are the original supplied titles, and values are the display titles.
     */
    private function getDisplayTitles($topPages)
    {
        $namespaces = $this->project->getNamespaces();

        // First extract page titles including namespace.
        $pageTitles = array_map(function ($page) use ($namespaces) {
            // If non-mainspace, prepend namespace to the titles.
            $ns = $page['page_namespace'];
            $nsTitle = $ns > 0 ? $namespaces[$page['page_namespace']] . ':' : '';
            return $nsTitle . $page['page_title'];
        }, $topPages);

        return $this->getRepository()->getDisplayTitles($this->project, $pageTitles);
    }
}
