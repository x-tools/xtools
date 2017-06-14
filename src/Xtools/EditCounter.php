<?php
/**
 * This file contains only the EditCounter class.
 */

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
    
    /** @var int[] */
    protected $logCounts;

    /** @var int[] Keys are project DB names. */
    protected $globalEditCounts;

    /**
     * EditCounter constructor.
     * @param Project $project The base project to count edits
     * @param User $user
     */
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
    protected function getLogCounts()
    {
        if (! is_array($this->logCounts)) {
            $this->logCounts = $this->getRepository()
                ->getLogCounts($this->project, $this->user);
        }
        return $this->logCounts;
    }

    public function countLiveRevisions()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['live']) ? $revCounts['live'] : 0;
    }

    /**
     * Get the total number of revisions that have been deleted.
     * @return int
     */
    public function countDeletedRevisions()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['deleted']) ? $revCounts['deleted'] : 0;
    }

    /**
     * Get the total edit count (live + deleted).
     * @return int
     */
    public function countAllRevisions()
    {
        return $this->countLiveRevisions() + $this->countDeletedRevisions();
    }

    /**
     * Get the total number of revisions with comments.
     * @return int
     */
    public function countRevisionsWithComments()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['with_comments']) ? $revCounts['with_comments'] : 0;
    }

    /**
     * Get the total number of revisions without comments.
     * @return int
     */
    public function countRevisionsWithoutComments()
    {
        return $this->countAllRevisions() - $this->countRevisionsWithComments();
    }

    /**
     * Get the total number of revisions marked as 'minor' by the user.
     * @return int
     */
    public function countMinorRevisions()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['minor']) ? $revCounts['minor'] : 0;
    }

    /**
     * Get the total number of revisions under 20 bytes.
     */
    public function countSmallRevisions()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['small']) ? $revCounts['small'] : 0;
    }

    /**
     * Get the total number of revisions over 1000 bytes.
     */
    public function countLargeRevisions()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['large']) ? $revCounts['large'] : 0;
    }

    /**
     * Get the average revision size for the user.
     * @return float Size in bytes.
     */
    public function averageRevisionSize()
    {
        $revisionCounts = $this->getRevisionCounts();
        return round($revisionCounts['average_size'], 3);
    }

    /**
     * Get the total number of non-deleted pages edited by the user.
     * @return int
     */
    public function countLivePagesEdited()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['edited-live']) ? $pageCounts['edited-live'] : 0;
    }

    /**
     * Get the total number of deleted pages ever edited by the user.
     * @return int
     */
    public function countDeletedPagesEdited()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['edited-deleted']) ? $pageCounts['edited-deleted'] : 0;
    }

    /**
     * Get the total number of pages ever edited by this user (both live and deleted).
     * @return int
     */
    public function countAllPagesEdited()
    {
        return $this->countLivePagesEdited() + $this->countDeletedPagesEdited();
    }

    /**
     * Get the total number of pages (both still live and those that have been deleted) created
     * by the user.
     * @return int
     */
    public function countPagesCreated()
    {
        return $this->countCreatedPagesLive() + $this->countPagesCreatedDeleted();
    }

    /**
     * Get the total number of pages created by the user, that have not been deleted.
     * @return int
     */
    public function countCreatedPagesLive()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['created-live']) ? (int)$pageCounts['created-live'] : 0;
    }

    /**
     * Get the total number of pages created by the user, that have since been deleted.
     * @return int
     */
    public function countPagesCreatedDeleted()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['created-deleted']) ? (int)$pageCounts['created-deleted'] : 0;
    }

    /**
     * Get the total number of pages that have been deleted by the user.
     * @return int
     */
    public function countPagesDeleted()
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['delete-delete']) ? (int)$logCounts['delete-delete'] : 0;
    }

    /**
     * Get the total number of pages moved by the user.
     * @return int
     */
    public function countPagesMoved()
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['move-move']) ? (int)$logCounts['move-move'] : 0;
    }

    /**
     * Get the average number of edits per page (including deleted revisions and pages).
     * @return float
     */
    public function averageRevisionsPerPage()
    {
        if ($this->countAllPagesEdited() == 0) {
            return 0;
        }
        return round($this->countAllRevisions() / $this->countAllPagesEdited(), 3);
    }

    /**
     * Average number of edits made per day.
     * @return float
     */
    public function averageRevisionsPerDay()
    {
        if ($this->getDays() == 0) {
            return 0;
        }
        return round($this->countAllRevisions() / $this->getDays(), 3);
    }

    /**
     * Get the total number of edits made by the user with semi-automating tools.
     */
    public function countAutomatedRevisions()
    {
        $autoSummary = $this->automatedRevisionsSummary();
        $count = 0;
        foreach ($autoSummary as $summary) {
            $count += $summary;
        }
        return $count;
    }

    /**
     * Get a summary of the numbers of edits made by the user with semi-automating tools.
     */
    public function automatedRevisionsSummary()
    {
        return $this->getRepository()->countAutomatedRevisions($this->project, $this->user);
    }

    /**
     * Get the count of (non-deleted) edits made in the given timeframe to now.
     * @param string $time One of 'day', 'week', 'month', or 'year'.
     * @return int The total number of live edits.
     */
    public function countRevisionsInLast($time)
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts[$time]) ? $revCounts[$time] : 0;
    }

    /**
     * Get the date and time of the user's first edit.
     */
    public function datetimeFirstRevision()
    {
        $first = $this->getRevisionDates()['first'];
        return new DateTime($first);
    }

    /**
     * Get the date and time of the user's first edit.
     * @return DateTime
     */
    public function datetimeLastRevision()
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
        $days = $this->datetimeLastRevision()->diff($this->datetimeFirstRevision())->days;
        return $days > 0 ? $days : 1;
    }

    public function countFilesUploaded()
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['upload-upload'] ?: 0;
    }

    public function countFilesUploadedCommons()
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['files_uploaded_commons'] ?: 0;
    }

    /**
     * Get the total number of revisions the user has sent thanks for.
     * @return int
     */
    public function thanks()
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['thanks-thank'] ?: 0;
    }

    /**
     * Get the total number of approvals
     * @return int
     */
    public function approvals()
    {
        $logCounts = $this->getLogCounts();
        $total = $logCounts['review-approve'] +
        (!empty($logCounts['review-approve-a']) ? $logCounts['review-approve-a'] : 0) +
        (!empty($logCounts['review-approve-i']) ? $logCounts['review-approve-i'] : 0) +
        (!empty($logCounts['review-approve-ia']) ? $logCounts['review-approve-ia'] : 0);
        return $total;
    }

    /**
     * @return int
     */
    public function patrols()
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['patrol-patrol'] ?: 0;
    }

    /**
     * Get the given user's total edit counts per namespace.
     */
    public function namespaceTotals()
    {
        $counts = $this->getRepository()->getNamespaceTotals($this->project, $this->user);
        arsort($counts);
        return $counts;
    }

    /**
     * Get a summary of the times of day and the days of the week that the user has edited.
     */
    public function timeCard()
    {
        return $this->getRepository()->getTimeCard($this->project, $this->user);
    }

    /**
     *
     */
    public function yearCounts()
    {
        $totals = $this->getRepository()->getYearCounts($this->project, $this->user);
        $out = [
            'years' => [],
            'namespaces' => [],
            'totals' => [],
        ];
        foreach ($totals as $total) {
            $out['years'][$total['year']] = $total['year'];
            $out['namespaces'][$total['page_namespace']] = $total['page_namespace'];
            if (!isset($out['totals'][$total['page_namespace']])) {
                $out['totals'][$total['page_namespace']] = [];
            }
            $out['totals'][$total['page_namespace']][$total['year']] = $total['count'];
        }

        return $out;
    }

    /**
     *
     */
    public function monthCounts()
    {
        $totals = $this->getRepository()->getMonthCounts($this->project, $this->user);
        $out = [
            'years' => [],
            'namespaces' => [],
            'totals' => [],
        ];
        $out['max_year'] = 0;
        $out['min_year'] = date('Y');
        foreach ($totals as $total) {
            // Collect all applicable years and namespaces.
            $out['max_year'] = max($out['max_year'], $total['year']);
            $out['min_year'] = min($out['min_year'], $total['year']);
            // Collate the counts by namespace, and then year and month.
            $ns = $total['page_namespace'];
            if (!isset($out['totals'][$ns])) {
                $out['totals'][$ns] = [];
            }
            $out['totals'][$ns][$total['year'] . $total['month']] = $total['count'];
        }
        // Fill in the blanks (where no edits were made in a given month for a namespace).
        for ($y = $out['min_year']; $y <= $out['max_year']; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                foreach ($out['totals'] as $nsId => &$total) {
                    if (!isset($total[$y . $m])) {
                        $total[$y . $m] = 0;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Get the total edit counts for the top n projects of this user.
     * @param int $numProjects
     * @return mixed[] Each element has 'total' and 'project' keys.
     */
    public function globalEditCountsTopN($numProjects = 10)
    {
        // Get counts.
        $editCounts = $this->globalEditCounts();
        // Sort.
        uasort($editCounts, function ($a, $b) {
            return $b['total'] - $a['total'];
        });
        // Truncate, and return.
        return array_slice($editCounts, 0, $numProjects);
    }

    /**
     * Get the grand total of all edits on all projects.
     * @return int
     */
    public function globalEditCount()
    {
        $total = 0;
        foreach ($this->globalEditCounts() as $editCount) {
            $total += $editCount['total'];
        }
        return $total;
    }

    /**
     * Get the total revision counts for all projects for this user.
     * @return mixed[] Each element has 'total' and 'project' keys.
     */
    public function globalEditCounts()
    {
        if (!$this->globalEditCounts) {
            $this->globalEditCounts = $this->getRepository()
                ->globalEditCounts($this->user, $this->project);
        }
        return $this->globalEditCounts;
    }

    /**
     * Get the most recent n revisions across all projects.
     * @param int $max The maximum number of revisions to return.
     * @return Edit[]
     */
    public function globalEdits($max)
    {
        // Store the top n Edits.
        $globalRevisions = [];
        // Only look for revisions newer than this.
        $oldest = null;
        foreach ($this->globalEditCounts() as $editCount) {
            // Don't query revisions if there aren't any.
            if ($editCount['total'] == 0) {
                continue;
            }

            /** @var Project $otherProject */
            $otherProject = $editCount['project'];
            $revisions = $this->getRepository()
                ->getRevisions($otherProject, $this->user, $oldest, $max);
            foreach ($revisions as &$revision) {
                $nsName = $otherProject->getNamespaces()[$revision['page_namespace']];
                $page = $otherProject->getRepository()
                    ->getPage($otherProject, $nsName . ':' . $revision['page_title']);
                $edit = new Edit($page, $revision);

                // If we've already got enough, only check for those newer than the current oldest.
                $haveEnough = (count($globalRevisions) >= $max);
                $thisIsOlder = ($oldest === null
                    || ($oldest !== null && $edit->getTimestamp() < $oldest));
                if ($haveEnough && $thisIsOlder) {
                    // Use this as the new oldest time.
                    $oldest = $edit->getTimestamp(); //$revision['unix_timestamp'];
                }
                $globalRevisions[$edit->getTimestamp()->getTimestamp().'-'.$edit->getId()] = $edit;
            }
            // Sort and prune, before adding more.
            krsort($globalRevisions);
            $globalRevisions = array_slice($globalRevisions, 0, $max);
        }
        return $globalRevisions;
    }
}
