<?php
/**
 * This file contains only the ArticleInfo class.
 */

declare(strict_types = 1);

namespace AppBundle\Model;

use AppBundle\Helper\I18nHelper;
use DateTime;

/**
 * An ArticleInfo provides statistics about a page on a project.
 */
class ArticleInfo extends ArticleInfoApi
{
    /** @var I18nHelper For i18n and l10n. */
    protected $i18n;

    /** @var int Number of revisions that were actually processed. */
    protected $numRevisionsProcessed;

    /**
     * Various statistics about editors to the page. These are not User objects
     * so as to preserve memory.
     * @var mixed[]
     */
    protected $editors = [];

    /** @var mixed[] The top 10 editors to the page by number of edits. */
    protected $topTenEditorsByEdits;

    /** @var mixed[] The top 10 editors to the page by added text. */
    protected $topTenEditorsByAdded;

    /** @var int Number of edits made by the top 10 editors. */
    protected $topTenCount;

    /** @var mixed[] Various counts about each individual year and month of the page's history. */
    protected $yearMonthCounts;

    /** @var string[] Localized labels for the years, to be used in the 'Year counts' chart. */
    protected $yearLabels = [];

    /** @var string[] Localized labels for the months, to be used in the 'Month counts' chart. */
    protected $monthLabels = [];

    /** @var Edit The first edit to the page. */
    protected $firstEdit;

    /** @var Edit The last edit to the page. */
    protected $lastEdit;

    /** @var Edit Edit that made the largest addition by number of bytes. */
    protected $maxAddition;

    /** @var Edit Edit that made the largest deletion by number of bytes. */
    protected $maxDeletion;

    /**
     * Maximum number of edits that were created across all months. This is used as a comparison
     * for the bar charts in the months section.
     * @var int
     */
    protected $maxEditsPerMonth;

    /** @var string[][] List of (semi-)automated tools that were used to edit the page. */
    protected $tools;

    /**
     * Total number of bytes added throughout the page's history. This is used as a comparison
     * when computing the top 10 editors by added text.
     * @var int
     */
    protected $addedBytes = 0;

    /** @var int Number of days between first and last edit. */
    protected $totalDays;

    /** @var int Number of minor edits to the page. */
    protected $minorCount = 0;

    /** @var int Number of anonymous edits to the page. */
    protected $anonCount = 0;

    /** @var int Number of automated edits to the page. */
    protected $automatedCount = 0;

    /** @var int Number of edits to the page that were reverted with the subsequent edit. */
    protected $revertCount = 0;

    /** @var int[] The "edits per <time>" counts. */
    protected $countHistory = [
        'day' => 0,
        'week' => 0,
        'month' => 0,
        'year' => 0,
    ];

    /**
     * Make the I18nHelper accessible to ArticleInfo.
     * @param I18nHelper $i18n
     * @codeCoverageIgnore
     */
    public function setI18nHelper(I18nHelper $i18n): void
    {
        $this->i18n = $i18n;
    }

    /**
     * Get the day of last date we should show in the month/year sections,
     * based on $this->end or the current date.
     * @return int As Unix timestamp.
     */
    private function getLastDay(): int
    {
        if (is_int($this->end)) {
            return (new DateTime("@$this->end"))
                ->modify('last day of this month')
                ->getTimestamp();
        } else {
            return strtotime('last day of this month');
        }
    }

    /**
     * Return the start/end date values as associative array, with YYYY-MM-DD as the date format.
     * This is used mainly as a helper to pass to the pageviews Twig macros.
     * @return array
     */
    public function getDateParams(): array
    {
        if (!$this->hasDateRange()) {
            return [];
        }

        $ret = [
            'start' => $this->firstEdit->getTimestamp()->format('Y-m-d'),
            'end' => $this->lastEdit->getTimestamp()->format('Y-m-d'),
        ];

        if (is_int($this->start)) {
            $ret['start'] = date('Y-m-d', $this->start);
        }
        if (is_int($this->end)) {
            $ret['end'] = date('Y-m-d', $this->end);
        }

        return $ret;
    }

    /**
     * Get the number of revisions that are actually getting processed. This goes by the app.max_page_revisions
     * parameter, or the actual number of revisions, whichever is smaller.
     * @return int
     */
    public function getNumRevisionsProcessed(): int
    {
        if (isset($this->numRevisionsProcessed)) {
            return $this->numRevisionsProcessed;
        }

        if ($this->tooManyRevisions()) {
            $this->numRevisionsProcessed = $this->getMaxRevisions();
        } else {
            $this->numRevisionsProcessed = $this->getNumRevisions();
        }

        return $this->numRevisionsProcessed;
    }

    /**
     * Fetch and store all the data we need to show the ArticleInfo view.
     * @codeCoverageIgnore
     */
    public function prepareData(): void
    {
        $this->parseHistory();
        $this->setLogsEvents();

        // Bots need to be set before setting top 10 counts.
        $this->bots = $this->getBots();

        $this->doPostPrecessing();
    }

    /**
     * Get the number of editors that edited the page.
     * @return int
     */
    public function getNumEditors(): int
    {
        return count($this->editors);
    }

    /**
     * Get the number of days between the first and last edit.
     * @return int
     */
    public function getTotalDays(): int
    {
        if (isset($this->totalDays)) {
            return $this->totalDays;
        }
        $dateFirst = $this->firstEdit->getTimestamp();
        $dateLast = $this->lastEdit->getTimestamp();
        $interval = date_diff($dateLast, $dateFirst, true);
        $this->totalDays = (int)$interval->format('%a');
        return $this->totalDays;
    }

    /**
     * Returns length of the page.
     * @return int
     */
    public function getLength(): int
    {
        if ($this->hasDateRange()) {
            return $this->lastEdit->getLength();
        }

        return $this->page->getLength();
    }

    /**
     * Get the average number of days between edits to the page.
     * @return float
     */
    public function averageDaysPerEdit(): float
    {
        return round($this->getTotalDays() / $this->getNumRevisionsProcessed(), 1);
    }

    /**
     * Get the average number of edits per day to the page.
     * @return float
     */
    public function editsPerDay(): float
    {
        $editsPerDay = $this->getTotalDays()
            ? $this->getNumRevisionsProcessed() / ($this->getTotalDays() / (365 / 12 / 24))
            : 0;
        return round($editsPerDay, 1);
    }

    /**
     * Get the average number of edits per month to the page.
     * @return float
     */
    public function editsPerMonth(): float
    {
        $editsPerMonth = $this->getTotalDays()
            ? $this->getNumRevisionsProcessed() / ($this->getTotalDays() / (365 / 12))
            : 0;
        return min($this->getNumRevisionsProcessed(), round($editsPerMonth, 1));
    }

    /**
     * Get the average number of edits per year to the page.
     * @return float
     */
    public function editsPerYear(): float
    {
        $editsPerYear = $this->getTotalDays()
            ? $this->getNumRevisionsProcessed() / ($this->getTotalDays() / 365)
            : 0;
        return min($this->getNumRevisionsProcessed(), round($editsPerYear, 1));
    }

    /**
     * Get the average number of edits per editor.
     * @return float
     */
    public function editsPerEditor(): float
    {
        if (count($this->editors) > 0) {
            return round($this->getNumRevisionsProcessed() / count($this->editors), 1);
        }

        // To prevent division by zero error; can happen if all usernames are removed (see T303724).
        return 0;
    }

    /**
     * Get the percentage of minor edits to the page.
     * @return float
     */
    public function minorPercentage(): float
    {
        return round(
            ($this->minorCount / $this->getNumRevisionsProcessed()) * 100,
            1
        );
    }

    /**
     * Get the percentage of anonymous edits to the page.
     * @return float
     */
    public function anonPercentage(): float
    {
        return round(
            ($this->anonCount / $this->getNumRevisionsProcessed()) * 100,
            1
        );
    }

    /**
     * Get the percentage of edits made by the top 10 editors.
     * @return float
     */
    public function topTenPercentage(): float
    {
        return round(($this->topTenCount / $this->getNumRevisionsProcessed()) * 100, 1);
    }

    /**
     * Get the number of automated edits made to the page.
     * @return int
     */
    public function getAutomatedCount(): int
    {
        return $this->automatedCount;
    }

    /**
     * Get the number of edits to the page that were reverted with the subsequent edit.
     * @return int
     */
    public function getRevertCount(): int
    {
        return $this->revertCount;
    }

    /**
     * Get the number of edits to the page made by logged out users.
     * @return int
     */
    public function getAnonCount(): int
    {
        return $this->anonCount;
    }

    /**
     * Get the number of minor edits to the page.
     * @return int
     */
    public function getMinorCount(): int
    {
        return $this->minorCount;
    }

    /**
     * Get the number of edits to the page made in the past day, week, month and year.
     * @return int[] With keys 'day', 'week', 'month' and 'year'.
     */
    public function getCountHistory(): array
    {
        return $this->countHistory;
    }

    /**
     * Get the number of edits to the page made by the top 10 editors.
     * @return int
     */
    public function getTopTenCount(): int
    {
        return $this->topTenCount;
    }

    /**
     * Get the first edit to the page.
     * @return Edit
     */
    public function getFirstEdit(): Edit
    {
        return $this->firstEdit;
    }

    /**
     * Get the last edit to the page.
     * @return Edit
     */
    public function getLastEdit(): Edit
    {
        return $this->lastEdit;
    }

    /**
     * Get the edit that made the largest addition to the page (by number of bytes).
     * @return Edit|null
     */
    public function getMaxAddition(): ?Edit
    {
        return $this->maxAddition;
    }

    /**
     * Get the edit that made the largest removal to the page (by number of bytes).
     * @return Edit|null
     */
    public function getMaxDeletion(): ?Edit
    {
        return $this->maxDeletion;
    }

    /**
     * Get the list of editors to the page, including various statistics.
     * @return mixed[]
     */
    public function getEditors(): array
    {
        return $this->editors;
    }

    /**
     * Get usernames of human editors (not bots).
     * @param int|null $limit
     * @return string[]
     */
    public function getHumans(?int $limit = null): array
    {
        return array_slice(array_diff(array_keys($this->getEditors()), array_keys($this->getBots())), 0, $limit);
    }

    /**
     * Get the list of the top editors to the page (by edits), including various statistics.
     * @return mixed[]
     */
    public function topTenEditorsByEdits(): array
    {
        return $this->topTenEditorsByEdits;
    }

    /**
     * Get the list of the top editors to the page (by added text), including various statistics.
     * @return mixed[]
     */
    public function topTenEditorsByAdded(): array
    {
        return $this->topTenEditorsByAdded;
    }

    /**
     * Get various counts about each individual year and month of the page's history.
     * @return mixed[]
     */
    public function getYearMonthCounts(): array
    {
        return $this->yearMonthCounts;
    }

    /**
     * Get the localized labels for the 'Year counts' chart.
     * @return string[]
     */
    public function getYearLabels(): array
    {
        return $this->yearLabels;
    }

    /**
     * Get the localized labels for the 'Month counts' chart.
     * @return string[]
     */
    public function getMonthLabels(): array
    {
        return $this->monthLabels;
    }

    /**
     * Get the maximum number of edits that were created across all months. This is used as a
     * comparison for the bar charts in the months section.
     * @return int
     */
    public function getMaxEditsPerMonth(): int
    {
        return $this->maxEditsPerMonth;
    }

    /**
     * Get a list of (semi-)automated tools that were used to edit the page, including
     * the number of times they were used, and a link to the tool's homepage.
     * @return string[]
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Parse the revision history, collecting our core statistics.
     *
     * Untestable because it relies on getting a PDO statement. All the important
     * logic lives in other methods which are tested.
     * @codeCoverageIgnore
     */
    private function parseHistory(): void
    {
        $limit = $this->tooManyRevisions() ? $this->getMaxRevisions() : null;

        // Third parameter is ignored if $limit is null.
        $revStmt = $this->page->getRevisionsStmt(
            null,
            $limit,
            $this->getNumRevisions(),
            $this->start,
            $this->end
        );
        $revCount = 0;

        /**
         * Data about previous edits so that we can use them as a basis for comparison.
         * @var Edit[]
         */
        $prevEdits = [
            // The previous Edit, used to discount content that was reverted.
            'prev' => null,

            // The SHA-1 of the edit *before* the previous edit. Used for more
            // accurate revert detection.
            'prevSha' => null,

            // The last edit deemed to be the max addition of content. This is kept track of
            // in case we find out the next edit was reverted (and was also a max edit),
            // in which case we'll want to discount it and use this one instead.
            'maxAddition' => null,

            // Same as with maxAddition, except the maximum amount of content deleted.
            // This is used to discount content that was reverted.
            'maxDeletion' => null,
        ];

        while ($rev = $revStmt->fetch()) {
            $edit = new Edit($this->page, $rev);

            if (0 === $revCount) {
                $this->firstEdit = $edit;
            }

            // Sometimes, with old revisions (2001 era), the revisions from 2002 come before 2001
            if ($edit->getTimestamp() < $this->firstEdit->getTimestamp()) {
                $this->firstEdit = $edit;
            }

            $prevEdits = $this->updateCounts($edit, $prevEdits);

            $revCount++;
        }

        $this->numRevisionsProcessed = $revCount;

        // Various sorts
        arsort($this->editors);
        ksort($this->yearMonthCounts);
        if ($this->tools) {
            arsort($this->tools);
        }
    }

    /**
     * Update various counts based on the current edit.
     * @param Edit $edit
     * @param Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'
     * @return Edit[] Updated version of $prevEdits.
     */
    private function updateCounts(Edit $edit, array $prevEdits): array
    {
        // Update the counts for the year and month of the current edit.
        $this->updateYearMonthCounts($edit);

        // Update counts for the user who made the edit.
        $this->updateUserCounts($edit);

        // Update the year/month/user counts of anon and minor edits.
        $this->updateAnonMinorCounts($edit);

        // Update counts for automated tool usage, if applicable.
        $this->updateToolCounts($edit);

        // Increment "edits per <time>" counts
        $this->updateCountHistory($edit);

        // Update figures regarding content addition/removal, and the revert count.
        $prevEdits = $this->updateContentSizes($edit, $prevEdits);

        // Now that we've updated all the counts, we can reset
        // the prev and last edits, which are used for tracking.
        // But first, let's copy over the SHA of the actual previous edit
        // and put it in our $prevEdits['prev'], so that we'll know
        // that content added after $prevEdit['prev'] was reverted.
        if (null !== $prevEdits['prev']) {
            $prevEdits['prevSha'] = $prevEdits['prev']->getSha();
        }
        $prevEdits['prev'] = $edit;
        $this->lastEdit = $edit;

        return $prevEdits;
    }

    /**
     * Update various figures about content sizes based on the given edit.
     * @param Edit $edit
     * @param Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
     * @return Edit[] Updated version of $prevEdits.
     */
    private function updateContentSizes(Edit $edit, array $prevEdits): array
    {
        // Check if it was a revert
        if ($this->isRevert($edit, $prevEdits)) {
            $edit->setReverted(true);
            return $this->updateContentSizesRevert($prevEdits);
        } else {
            return $this->updateContentSizesNonRevert($edit, $prevEdits);
        }
    }

    /**
     * Is the given Edit a revert?
     * @param Edit $edit
     * @param Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
     * @return bool
     */
    private function isRevert(Edit $edit, array $prevEdits): bool
    {
        return $edit->getSha() === $prevEdits['prevSha'] || $edit->isRevert($this->container);
    }

    /**
     * Updates the figures on content sizes assuming the given edit was a revert of the previous one.
     * In such a case, we don't want to treat the previous edit as legit content addition or removal.
     * @param Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
     * @return Edit[] Updated version of $prevEdits, for tracking.
     */
    private function updateContentSizesRevert(array $prevEdits): array
    {
        $this->revertCount++;

        // Adjust addedBytes given this edit was a revert of the previous one.
        if ($prevEdits['prev'] && !$prevEdits['prev']->isReverted() && $prevEdits['prev']->getSize() > 0) {
            $this->addedBytes -= $prevEdits['prev']->getSize();

            // Also deduct from the user's individual added byte count.
            // We don't do this if the previous edit was reverted, since that would make the net bytes zero.
            if ($prevEdits['prev']->getUser()) {
                $username = $prevEdits['prev']->getUser()->getUsername();
                $this->editors[$username]['added'] -= $prevEdits['prev']->getSize();
            }
        }

        // @TODO: Test this against an edit war (use your sandbox).
        // Also remove as max added or deleted, if applicable.
        if ($this->maxAddition && $prevEdits['prev']->getId() === $this->maxAddition->getId()) {
            $this->maxAddition = $prevEdits['maxAddition'];
            $prevEdits['maxAddition'] = $prevEdits['prev']; // In the event of edit wars.
        } elseif ($this->maxDeletion && $prevEdits['prev']->getId() === $this->maxDeletion->getId()) {
            $this->maxDeletion = $prevEdits['maxDeletion'];
            $prevEdits['maxDeletion'] = $prevEdits['prev']; // In the event of edit wars.
        }

        return $prevEdits;
    }

    /**
     * Updates the figures on content sizes assuming the given edit was NOT a revert of the previous edit.
     * @param Edit $edit
     * @param Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
     * @return Edit[] Updated version of $prevEdits, for tracking.
     */
    private function updateContentSizesNonRevert(Edit $edit, array $prevEdits): array
    {
        $editSize = $this->getEditSize($edit, $prevEdits);

        // Edit was not a revert, so treat size > 0 as content added.
        if ($editSize > 0) {
            $this->addedBytes += $editSize;

            if ($edit->getUser()) {
                $this->editors[$edit->getUser()->getUsername()]['added'] += $editSize;
            }

            // Keep track of edit with max addition.
            if (!$this->maxAddition || $editSize > $this->maxAddition->getSize()) {
                // Keep track of old maxAddition in case we find out the next $edit was reverted
                // (and was also a max edit), in which case we'll want to use this one ($edit).
                $prevEdits['maxAddition'] = $this->maxAddition;

                $this->maxAddition = $edit;
            }
        } elseif ($editSize < 0 && (!$this->maxDeletion || $editSize < $this->maxDeletion->getSize())) {
            // Keep track of old maxDeletion in case we find out the next edit was reverted
            // (and was also a max deletion), in which case we'll want to use this one.
            $prevEdits['maxDeletion'] = $this->maxDeletion;

            $this->maxDeletion = $edit;
        }

        return $prevEdits;
    }

    /**
     * Get the size of the given edit, based on the previous edit (if present).
     * We also don't return the actual edit size if last revision had a length of null.
     * This happens when the edit follows other edits that were revision-deleted.
     * @see T148857 for more information.
     * @todo Remove once T101631 is resolved.
     * @param Edit $edit
     * @param Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
     * @return int
     */
    private function getEditSize(Edit $edit, array $prevEdits): int
    {
        if ($prevEdits['prev'] && null === $prevEdits['prev']->getLength()) {
            return 0;
        } else {
            return $edit->getSize();
        }
    }

    /**
     * Update counts of automated tool usage for the given edit.
     * @param Edit $edit
     */
    private function updateToolCounts(Edit $edit): void
    {
        $automatedTool = $edit->getTool($this->container);

        if (false === $automatedTool) {
            // Nothing to do.
            return;
        }

        $editYear = $edit->getYear();
        $editMonth = $edit->getMonth();

        $this->automatedCount++;
        $this->yearMonthCounts[$editYear]['automated']++;
        $this->yearMonthCounts[$editYear]['months'][$editMonth]['automated']++;

        if (!isset($this->tools[$automatedTool['name']])) {
            $this->tools[$automatedTool['name']] = [
                'count' => 1,
                'link' => $automatedTool['link'],
            ];
        } else {
            $this->tools[$automatedTool['name']]['count']++;
        }
    }

    /**
     * Update various counts for the year and month of the given edit.
     * @param Edit $edit
     */
    private function updateYearMonthCounts(Edit $edit): void
    {
        $editYear = $edit->getYear();
        $editMonth = $edit->getMonth();

        // Fill in the blank arrays for the year and 12 months if needed.
        if (!isset($this->yearMonthCounts[$editYear])) {
            $this->addYearMonthCountEntry($edit);
        }

        // Increment year and month counts for all edits
        $this->yearMonthCounts[$editYear]['all']++;
        $this->yearMonthCounts[$editYear]['months'][$editMonth]['all']++;
        // This will ultimately be the size of the page by the end of the year
        $this->yearMonthCounts[$editYear]['size'] = $edit->getLength();

        // Keep track of which month had the most edits
        $editsThisMonth = $this->yearMonthCounts[$editYear]['months'][$editMonth]['all'];
        if ($editsThisMonth > $this->maxEditsPerMonth) {
            $this->maxEditsPerMonth = $editsThisMonth;
        }
    }

    /**
     * Add a new entry to $this->yearMonthCounts for the given year,
     * with blank values for each month. This called during self::parseHistory().
     * @param Edit $edit
     */
    private function addYearMonthCountEntry(Edit $edit): void
    {
        $this->yearLabels[] = $this->i18n->dateFormat($edit->getTimestamp(), 'yyyy');
        $editYear = $edit->getYear();

        // Beginning of the month at 00:00:00.
        $firstEditTime = mktime(0, 0, 0, (int)$this->firstEdit->getMonth(), 1, (int)$this->firstEdit->getYear());

        $this->yearMonthCounts[$editYear] = [
            'all' => 0,
            'minor' => 0,
            'anon' => 0,
            'automated' => 0,
            'size' => 0, // Keep track of the size by the end of the year.
            'events' => [],
            'months' => [],
        ];

        for ($i = 1; $i <= 12; $i++) {
            $timeObj = mktime(0, 0, 0, $i, 1, (int)$editYear);

            // Don't show zeros for months before the first edit or after the current month.
            if ($timeObj < $firstEditTime || $timeObj > $this->getLastDay()) {
                continue;
            }

            $this->monthLabels[] = $this->i18n->dateFormat($timeObj, 'yyyy-MM');
            $this->yearMonthCounts[$editYear]['months'][sprintf('%02d', $i)] = [
                'all' => 0,
                'minor' => 0,
                'anon' => 0,
                'automated' => 0,
            ];
        }
    }

    /**
     * Update the counts of anon and minor edits for year, month, and user of the given edit.
     * @param Edit $edit
     */
    private function updateAnonMinorCounts(Edit $edit): void
    {
        $editYear = $edit->getYear();
        $editMonth = $edit->getMonth();

        // If anonymous, increase counts
        if ($edit->isAnon()) {
            $this->anonCount++;
            $this->yearMonthCounts[$editYear]['anon']++;
            $this->yearMonthCounts[$editYear]['months'][$editMonth]['anon']++;
        }

        // If minor edit, increase counts
        if ($edit->isMinor()) {
            $this->minorCount++;
            $this->yearMonthCounts[$editYear]['minor']++;
            $this->yearMonthCounts[$editYear]['months'][$editMonth]['minor']++;
        }
    }

    /**
     * Update various counts for the user of the given edit.
     * @param Edit $edit
     */
    private function updateUserCounts(Edit $edit): void
    {
        if (!$edit->getUser()) {
            return;
        }

        $username = $edit->getUser()->getUsername();

        // Initialize various user stats if needed.
        if (!isset($this->editors[$username])) {
            $this->editors[$username] = [
                'all' => 0,
                'minor' => 0,
                'minorPercentage' => 0,
                'first' => $edit->getTimestamp(),
                'firstId' => $edit->getId(),
                'last' => null,
                'atbe' => null,
                'added' => 0,
            ];
        }

        // Increment user counts
        $this->editors[$username]['all']++;
        $this->editors[$username]['last'] = $edit->getTimestamp();
        $this->editors[$username]['lastId'] = $edit->getId();

        // Increment minor counts for this user
        if ($edit->isMinor()) {
            $this->editors[$username]['minor']++;
        }
    }

    /**
     * Increment "edits per <time>" counts based on the given edit.
     * @param Edit $edit
     */
    private function updateCountHistory(Edit $edit): void
    {
        $editTimestamp = $edit->getTimestamp();

        if ($editTimestamp > new DateTime('-1 day')) {
            $this->countHistory['day']++;
        }
        if ($editTimestamp > new DateTime('-1 week')) {
            $this->countHistory['week']++;
        }
        if ($editTimestamp > new DateTime('-1 month')) {
            $this->countHistory['month']++;
        }
        if ($editTimestamp > new DateTime('-1 year')) {
            $this->countHistory['year']++;
        }
    }

    /**
     * Query for log events during each year of the article's history, and set the results in $this->yearMonthCounts.
     */
    private function setLogsEvents(): void
    {
        $logData = $this->getRepository()->getLogEvents(
            $this->page,
            $this->start,
            $this->end
        );

        foreach ($logData as $event) {
            $time = strtotime($event['timestamp']);
            $year = date('Y', $time);

            if (!isset($this->yearMonthCounts[$year])) {
                break;
            }

            $yearEvents = $this->yearMonthCounts[$year]['events'];

            // Convert log type value to i18n key.
            switch ($event['log_type']) {
                // count pending-changes protections along with normal protections.
                case 'stable':
                case 'protect':
                    $action = 'protections';
                    break;
                case 'delete':
                    $action = 'deletions';
                    break;
                case 'move':
                    $action = 'moves';
                    break;
            }

            if (empty($yearEvents[$action])) {
                $yearEvents[$action] = 1;
            } else {
                $yearEvents[$action]++;
            }

            $this->yearMonthCounts[$year]['events'] = $yearEvents;
        }
    }

    /**
     * Set statistics about the top 10 editors by added text and number of edits.
     * This is ran *after* parseHistory() since we need the grand totals first.
     * Various stats are also set for each editor in $this->editors to be used in the charts.
     */
    private function doPostPrecessing(): void
    {
        $topTenCount = $counter = 0;
        $topTenEditorsByEdits = [];

        foreach ($this->editors as $editor => $info) {
            // Count how many users are in the top 10% by number of edits, excluding bots.
            if ($counter < 10 && !in_array($editor, array_keys($this->bots))) {
                $topTenCount += $info['all'];
                $counter++;

                // To be used in the Top Ten charts.
                $topTenEditorsByEdits[] = [
                    'label' => $editor,
                    'value' => $info['all'],
                ];
            }

            // Compute the percentage of minor edits the user made.
            $this->editors[$editor]['minorPercentage'] = $info['all']
                ? ($info['minor'] / $info['all']) * 100
                : 0;

            if ($info['all'] > 1) {
                // Number of seconds/days between first and last edit.
                $secs = $info['last']->getTimestamp() - $info['first']->getTimestamp();
                $days = $secs / (60 * 60 * 24);

                // Average time between edits (in days).
                $this->editors[$editor]['atbe'] = round($days / $info['all'], 1);
            }
        }

        // Loop through again and add percentages.
        $this->topTenEditorsByEdits = array_map(function ($editor) use ($topTenCount) {
            $editor['percentage'] = 100 * ($editor['value'] / $topTenCount);
            return $editor;
        }, $topTenEditorsByEdits);

        $this->topTenEditorsByAdded = $this->getTopTenByAdded();

        $this->topTenCount = $topTenCount;
    }

    /**
     * Get the top ten editors by added text.
     * @return array With keys 'label', 'value' and 'percentage', ready to be used by the pieChart Twig helper.
     */
    private function getTopTenByAdded(): array
    {
        // First sort editors array by the amount of text they added.
        $topTenEditorsByAdded = $this->editors;
        uasort($topTenEditorsByAdded, function ($a, $b) {
            if ($a['added'] === $b['added']) {
                return 0;
            }
            return $a['added'] > $b['added'] ? -1 : 1;
        });

        // Slice to the top 10.
        $topTenEditorsByAdded = array_keys(array_slice($topTenEditorsByAdded, 0, 10, true));

         // Get the sum of added text so that we can add in percentages.
         $topTenTotalAdded = array_sum(array_map(function ($editor) {
             return $this->editors[$editor]['added'];
         }, $topTenEditorsByAdded));

        // Then build a new array of top 10 editors by added text in the data structure needed for the chart.
        return array_map(function ($editor) use ($topTenTotalAdded) {
            $added = $this->editors[$editor]['added'];
            return [
                'label' => $editor,
                'value' => $added,
                'percentage' => 0 === $this->addedBytes
                    ? 0
                    : 100 * ($added / $topTenTotalAdded),
            ];
        }, $topTenEditorsByAdded);
    }

    /**
     * Get the number of times the page has been viewed in the given timeframe. If the ArticleInfo instance has a
     * date range, it is used instead of the value of the $latest parameter.
     * @param  int $latest Last N days.
     * @return int
     */
    public function getPageviews(int $latest): int
    {
        if (!$this->hasDateRange()) {
            return $this->page->getLastPageviews($latest);
        }

        $daterange = $this->getDateParams();
        return $this->page->getPageviews($daterange['start'], $daterange['end']);
    }
}
