<?php
/**
 * This file contains only the AutoEdits class.
 */

declare(strict_types = 1);

namespace AppBundle\Model;

/**
 * AutoEdits returns statistics about automated edits made by a user.
 */
class AutoEdits extends Model
{
    /** @var null|string The tool we're searching for when fetching (semi-)automated edits. */
    protected $tool;

    /** @var Edit[] The list of non-automated contributions. */
    protected $nonAutomatedEdits;

    /** @var Edit[] The list of automated contributions. */
    protected $automatedEdits;

    /** @var int Total number of edits. */
    protected $editCount;

    /** @var int Total number of non-automated edits. */
    protected $automatedCount;

    /** @var array Counts of known automated tools used by the given user. */
    protected $toolCounts;

    /** @var int Total number of edits made with the tools. */
    protected $toolsTotal;

    /**
     * Constructor for the AutoEdits class.
     * @param Project $project
     * @param User $user
     * @param int|string $namespace Namespace ID or 'all'
     * @param int|false $start Start date in a format accepted by strtotime()
     * @param int|false $end End date in a format accepted by strtotime()
     * @param string $tool The tool we're searching for when fetching (semi-)automated edits.
     * @param int|string $offset Used for pagination, offset results by N edits.
     */
    public function __construct(
        Project $project,
        User $user,
        $namespace = 0,
        $start = false,
        $end = false,
        $tool = null,
        $offset = 0
    ) {
        $this->project = $project;
        $this->user = $user;
        $this->namespace = $namespace;
        $this->start = false === $start ? '' : date('Y-m-d', $start);
        $this->end = false === $end ? '' : date('Y-m-d', $end);
        $this->tool = $tool;
        $this->offset = $offset;
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
        if (!is_int($this->editCount)) {
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
        if (is_int($this->automatedCount)) {
            return $this->automatedCount;
        }

        $this->automatedCount = (int)$this->getRepository()->countAutomatedEdits(
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
     * @param bool $raw Wether to return raw data from the database, or get Edit objects.
     * @return string[]|Edit[]
     */
    public function getNonAutomatedEdits(bool $raw = false)
    {
        if (is_array($this->nonAutomatedEdits)) {
            return $this->nonAutomatedEdits;
        }

        $revs = $this->getRepository()->getNonAutomatedEdits(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end,
            $this->offset
        );

        if ($raw) {
            return $revs;
        }

        $this->nonAutomatedEdits = $this->getEditsFromRevs($revs);

        return $this->nonAutomatedEdits;
    }

    /**
     * Get automated contributions for this user.
     * @param bool $raw Whether to return raw data from the database, or get Edit objects.
     * @return Edit[]
     */
    public function getAutomatedEdits(bool $raw = false): array
    {
        if (is_array($this->automatedEdits)) {
            return $this->automatedEdits;
        }

        $revs = $this->getRepository()->getAutomatedEdits(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end,
            $this->tool,
            $this->offset
        );

        if ($raw) {
            return $revs;
        }

        $this->automatedEdits = $this->getEditsFromRevs($revs);

        return $this->automatedEdits;
    }

    /**
     * Transform database rows into Edit objects.
     * @param string[] $revs
     * @return Edit[]
     */
    private function getEditsFromRevs(array $revs): array
    {
        return array_map(function ($rev) {
            /** @var Page $page Page object to be passed to the Edit contructor. */
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
    private function getPageFromRev(array $rev): Page
    {
        $namespaces = $this->project->getNamespaces();
        $pageTitle = $rev['page_title'];

        if (0 === (int)$rev['page_namespace']) {
            $fullPageTitle = $pageTitle;
        } else {
            $fullPageTitle = $namespaces[$rev['page_namespace']].":$pageTitle";
        }

        return new Page($this->project, $fullPageTitle);
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
        if (is_array($this->toolCounts)) {
            return $this->toolCounts;
        }

        $this->toolCounts = $this->getRepository()->getToolCounts(
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
     */
    public function getAllTools(): array
    {
        return $this->getRepository()->getTools($this->project);
    }

    /**
     * Get the combined number of edits made with each tool. This is calculated separately from
     * self::getAutomatedCount() because the regex can sometimes overlap, and the counts are actually different.
     * @return int
     */
    public function getToolsTotal(): int
    {
        if (!is_int($this->toolsTotal)) {
            $this->toolsTotal = array_reduce($this->getToolCounts(), function ($a, $b) {
                return $a + $b['count'];
            });
        }

        return $this->toolsTotal;
    }
}
