<?php
/**
 * This file contains only the CategoryEdits class.
 */

namespace Xtools;

/**
 * CategoryEdits returns statistics about edits made by a user to pages in given categories.
 */
class CategoryEdits extends Model
{
    /** @var Project The project. */
    protected $project;

    /** @var User The user. */
    protected $user;

    /** @var string[] The categories. */
    protected $categories;

    /** @var string Start date. */
    protected $start;

    /** @var string End date. */
    protected $end;

    /** @var int Number of rows to OFFSET, used for pagination. */
    protected $offset;

    /** @var Edit[] The list of contributions. */
    protected $categoryEdits;

    /** @var int Total number of edits. */
    protected $editCount;

    /** @var int Total number of edits within the category. */
    protected $categoryEditCount;

    /** @var array Counts of edits within each category, keyed by category name. */
    protected $categoryCounts;

    /**
     * Constructor for the CategoryEdits class.
     * @param Project $project
     * @param User $user
     * @param array $categories
     * @param int|false $start As Unix timestamp.
     * @param int|false $end As Unix timestamp.
     * @param int $offset Used for pagination, offset results by N edits.
     */
    public function __construct(
        Project $project,
        User $user,
        array $categories,
        $start = false,
        $end = false,
        $offset = 0
    ) {
        $this->project = $project;
        $this->user = $user;
        $this->categories = array_map(function ($category) {
            return str_replace(' ', '_', $category);
        }, $categories);
        $this->start = false === $start ? '' : date('Y-m-d', $start);
        $this->end = false === $end ? '' : date('Y-m-d', $end);
        $this->offset = (int)$offset;
    }

    /**
     * Get the categories.
     * @return string[]
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * Get the categories as a piped string.
     * @return string
     */
    public function getCategoriesPiped()
    {
        return implode('|', $this->categories);
    }

    /**
     * Get the categories as an array of normalized strings (without namespace).
     * @return string
     */
    public function getCategoriesNormalized()
    {
        return array_map(function ($category) {
            return str_replace('_', ' ', $category);
        }, $this->categories);
    }

    /**
     * Get the start date.
     * @return string
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Get the end date.
     * @return string
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * Get the offset value.
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Get the raw edit count of the user.
     * @return int
     */
    public function getEditCount()
    {
        if (!is_int($this->editCount)) {
            $this->editCount = $this->user->countEdits(
                $this->project,
                'all',
                $this->start,
                $this->end
            );
        }

        return $this->editCount;
    }

    /**
     * Get the number of edits this user made within the categories.
     * @return int Result of query, see below.
     */
    public function getCategoryEditCount()
    {
        if (is_int($this->categoryEditCount)) {
            return $this->categoryEditCount;
        }

        $this->categoryEditCount = (int)$this->getRepository()->countCategoryEdits(
            $this->project,
            $this->user,
            $this->categories,
            $this->start,
            $this->end
        );

        return $this->categoryEditCount;
    }

    /**
     * Get the percentage of all edits made to the categories.
     * @return float
     */
    public function getCategoryPercentage()
    {
        return $this->getEditCount() > 0
            ? ($this->getCategoryEditCount() / $this->getEditCount()) * 100
            : 0;
    }

    /**
     * Get contributions made to the categories.
     * @param bool $raw Wether to return raw data from the database, or get Edit objects.
     * @return string[]|Edit[]
     */
    public function getCategoryEdits($raw = false)
    {
        if (is_array($this->categoryEdits)) {
            return $this->categoryEdits;
        }

        $revs = $this->getRepository()->getCategoryEdits(
            $this->project,
            $this->user,
            $this->categories,
            $this->start,
            $this->end,
            $this->offset
        );

        if ($raw) {
            return $revs;
        }

        $this->categoryEdits = $this->getEditsFromRevs($revs);

        return $this->categoryEdits;
    }

    /**
     * Transform database rows into Edit objects.
     * @param string[] $revs
     * @return Edit[]
     */
    private function getEditsFromRevs(array $revs)
    {
        return array_map(function ($rev) {
            /* @var Page Page object to be passed to the Edit contructor. */
            $page = $this->getPageFromRev($rev);
            $rev['user'] = $this->user;

            return new Edit($page, $rev);
        }, $revs);
    }

    /**
     * Get a Page object given a revision row.
     * @param array $rev Revision as retrieved from the database.
     * @return Page
     */
    private function getPageFromRev(array $rev)
    {
        $namespaces = $this->project->getNamespaces();
        $pageTitle = $rev['page_title'];

        if ((int)$rev['page_namespace'] === 0) {
            $fullPageTitle = $pageTitle;
        } else {
            $fullPageTitle = $namespaces[$rev['page_namespace']].":$pageTitle";
        }

        return new Page($this->project, $fullPageTitle);
    }

    /**
     * Get counts of edits made to each individual category.
     * @return array Counts, keyed by category name.
     */
    public function getCategoryCounts()
    {
        if (is_array($this->categoryCounts)) {
            return $this->categoryCounts;
        }

        $this->categoryCounts = $this->getRepository()->getCategoryCounts(
            $this->project,
            $this->user,
            $this->categories,
            $this->start,
            $this->end
        );

        arsort($this->categoryCounts);

        return $this->categoryCounts;
    }
}
