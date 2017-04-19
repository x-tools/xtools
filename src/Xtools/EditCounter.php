<?php

namespace Xtools;

use \DateTime;

/**
 * An EditCounter provides statistics about a user's edits on a project.
 */
class EditCounter extends Model
{
    
    /** @var Project */
    protected $project;
    
    /** @var User */
    protected $user;

    /** @var int[] */
    protected $revisionCounts;

    /** @var string[] */
    protected $revisionDates;

    /** @var int[] */
    protected $pageCounts;

    public function __construct(Project $project, User $user)
    {
        $this->project = $project;
        $this->user = $user;
    }

    /**
     * Get revision count data.
     * @return int[]
     */
    protected function getRevisionCounts()
    {
        if (! is_array($this->revisionCounts)) {
            $this->revisionCounts = $this->getRepository()
                ->getRevisionCounts($this->project, $this->user);
        }
        return $this->revisionCounts;
    }

    /**
     * Get page count data.
     * @return int[]
     */
    protected function getPageCounts()
    {
        if (! is_array($this->pageCounts)) {
            $this->pageCounts = $this->getRepository()
                ->getPageCounts($this->project, $this->user);
        }
        return $this->pageCounts;
    }

    /**
     * Get revision dates.
     * @return int[]
     */
    protected function getRevisionDates()
    {
        if (! is_array($this->revisionDates)) {
            $this->revisionDates = $this->getRepository()
                ->getRevisionDates($this->project, $this->user);
        }
        return $this->revisionDates;
    }

    public function getLiveEditCount()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['live']) ? $revCounts['live'] : 0;
    }

    public function getDeletedEditCount()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['deleted']) ? $revCounts['deleted'] : 0;
    }

    /**
     * Get the total edit count (live + deleted).
     * @return int
     */
    public function getTotalEditCount()
    {
        return $this->getLiveEditCount() + $this->getDeletedEditCount();
    }

    /**
     * Get the total number of non-deleted pages edited by the user.
     * @return int
     */
    public function getTotalLivePagesEdited()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['edited-total']) ? $pageCounts['edited-total'] : 0;
    }

    public function getTotalDeletedPagesEdited()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['edited-total']) ? $pageCounts['edited-total'] : 0;
    }

    /**
     * Get the total number of pages ever edited by this user (both live and deleted).
     * @return int
     */
    public function getTotalPagesEdited()
    {
        return $this->getTotalLivePagesEdited() + $this->getTotalDeletedPagesEdited();
    }

    /**
     * Get the total number of semi-automated edits.
     * @return int
     */
    public function getAutoEditsTotal()
    {
    }

    /**
     * Get the total number of pages (both still live and those that have been deleted) created
     * by the user.
     * @return int
     */
    public function getCreatedPagesTotal()
    {
        return $this->getCreatedPagesLive() + $this->getCreatedPagesDeleted();
    }

    /**
     * Get the total number of pages created by the user, that have not been deleted.
     * @return int
     */
    public function getCreatedPagesLive()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['created-live']) ? (int)$pageCounts['created-live'] : 0;
    }

    public function getMovedPagesTotal()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['moved']) ? (int)$pageCounts['moved'] : 0;
    }

    /**
     * Get the total number of pages created by the user, that have since been deleted.
     * @return int
     */
    public function getCreatedPagesDeleted()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['created-deleted']) ? (int)$pageCounts['created-deleted'] : 0;
    }

    /**
     * Get the average number of edits performed daily by the user (including deleted revisions
     * and pages).
     * @return float
     */
    public function getAverageEditCountPerPage()
    {
        return round($this->getTotalEditCount() / $this->getTotalPagesEdited(), 2);
    }

    /**
     * Get the count of (non-deleted) edits made in the given timeframe to now.
     * @param string $time One of 'day', 'week', 'month', or 'year'.
     * @return int The total number of live edits.
     */
    public function getEditCountInLast($time)
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts[$time]) ? $revCounts[$time] : 0;
    }

    /**
     * Get the date and time of the user's first edit.
     */
    public function getFirstEditDatetime()
    {
        $first = $this->getRevisionDates()['first'];
        return new DateTime($first);
    }

    /**
     * Get the date and time of the user's first edit.
     * @return DateTime
     */
    public function getLastEditDatetime()
    {
        $last = $this->getRevisionDates()['last'];
        return new DateTime($last);
    }

    /**
     * Get the number of days between the first and last edits.
     * If there's only one edit, this is counted as one day.
     * @return int
     */
    public function getDays()
    {
        $days = $this->getLastEditDatetime()->diff($this->getFirstEditDatetime())->days;
        return $days > 0 ? $days : 1;
    }

    /**
     * Average number of edits made per day.
     * @return float
     */
    public function getAverageDailyEditCount()
    {
        return round($this->getTotalEditCount() / $this->getDays(), 2);
    }
}
