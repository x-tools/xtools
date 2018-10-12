<?php
/**
 * This file contains only the EditCounter class.
 */

declare(strict_types = 1);

namespace AppBundle\Model;

use AppBundle\Helper\I18nHelper;
use DateInterval;
use DatePeriod;
use DateTime;

/**
 * An EditCounter provides statistics about a user's edits on a project.
 */
class EditCounter extends UserRights
{
    /** @var int[] Revision and page counts etc. */
    protected $pairData;

    /** @var string[] The IDs and timestamps of first/latest edit and logged action. */
    protected $firstAndLatestActions;

    /** @var int[] The total page counts. */
    protected $pageCounts;

    /** @var int[] The lot totals. */
    protected $logCounts;

    /** @var mixed[] Total numbers of edits per month */
    protected $monthCounts;

    /** @var mixed[] Total numbers of edits per year */
    protected $yearCounts;

    /** @var int[] Keys are project DB names. */
    protected $globalEditCounts;

    /** @var array Block data, with keys 'set' and 'received'. */
    protected $blocks;

    /** @var integer[] Array keys are namespace IDs, values are the edit counts. */
    protected $namespaceTotals;

    /** @var int Number of semi-automated edits. */
    protected $autoEditCount;

    /** @var string[] Data needed for time card chart. */
    protected $timeCardData;

    /** @var array Most recent revisions across all projects. */
    protected $globalEdits;

    /**
     * Revision size data, with keys 'average_size', 'large_edits' and 'small_edits'.
     * @var string[] As returned by the DB, unconverted to int or float
     */
    protected $editSizeData;

    /**
     * Duration of the longest block in seconds; -1 if indefinite,
     *   or false if could not be parsed from log params
     * @var int|bool
     */
    protected $longestBlockSeconds;

    /**
     * EditCounter constructor.
     * @param Project $project The base project to count edits
     * @param User $user
     * @param I18nHelper $i18n
     */
    public function __construct(Project $project, User $user, I18nHelper $i18n)
    {
        $this->project = $project;
        $this->user = $user;
        $this->i18n = $i18n;
    }

    /**
     * Get revision and page counts etc.
     * @return int[]
     */
    public function getPairData(): array
    {
        if (!is_array($this->pairData)) {
            $this->pairData = $this->getRepository()
                ->getPairData($this->project, $this->user);
        }
        return $this->pairData;
    }

    /**
     * Get revision dates.
     * @return array
     */
    public function getLogCounts(): array
    {
        if (!is_array($this->logCounts)) {
            $this->logCounts = $this->getRepository()
                ->getLogCounts($this->project, $this->user);
        }
        return $this->logCounts;
    }

    /**
     * Get the IDs and timestamps of the latest edit and logged action.
     * @return string[] With keys 'rev_first', 'rev_latest', 'log_latest', each with 'id' and 'timestamp'.
     */
    public function getFirstAndLatestActions(): array
    {
        if (!isset($this->firstAndLatestActions)) {
            $this->firstAndLatestActions = $this->getRepository()->getFirstAndLatestActions(
                $this->project,
                $this->user
            );
        }
        return $this->firstAndLatestActions;
    }

    /**
     * Get block data.
     * @param string $type Either 'set', 'received'
     * @param bool $blocksOnly Whether to include only blocks, and not reblocks and unblocks.
     * @return array
     */
    protected function getBlocks(string $type, bool $blocksOnly = true): array
    {
        if (isset($this->blocks[$type]) && is_array($this->blocks[$type])) {
            return $this->blocks[$type];
        }
        $method = "getBlocks".ucfirst($type);
        $blocks = $this->getRepository()->$method($this->project, $this->user);
        $this->blocks[$type] = $blocks;

        // Filter out unblocks unless requested.
        if ($blocksOnly) {
            $blocks = array_filter($blocks, function ($block) {
                return 'block' === $block['log_action'];
            });
        }

        return $blocks;
    }

    /**
     * Get the total number of currently-live revisions.
     * @return int
     */
    public function countLiveRevisions(): int
    {
        $revCounts = $this->getPairData();
        return isset($revCounts['live']) ? (int)$revCounts['live'] : 0;
    }

    /**
     * Get the total number of the user's revisions that have been deleted.
     * @return int
     */
    public function countDeletedRevisions(): int
    {
        $revCounts = $this->getPairData();
        return isset($revCounts['deleted']) ? (int)$revCounts['deleted'] : 0;
    }

    /**
     * Get the total edit count (live + deleted).
     * @return int
     */
    public function countAllRevisions(): int
    {
        return $this->countLiveRevisions() + $this->countDeletedRevisions();
    }

    /**
     * Get the total number of live revisions with comments.
     * @return int
     */
    public function countRevisionsWithComments(): int
    {
        $revCounts = $this->getPairData();
        return isset($revCounts['with_comments']) ? (int)$revCounts['with_comments'] : 0;
    }

    /**
     * Get the total number of live revisions without comments.
     * @return int
     */
    public function countRevisionsWithoutComments(): int
    {
        return $this->countLiveRevisions() - $this->countRevisionsWithComments();
    }

    /**
     * Get the total number of revisions marked as 'minor' by the user.
     * @return int
     */
    public function countMinorRevisions(): int
    {
        $revCounts = $this->getPairData();
        return isset($revCounts['minor']) ? (int)$revCounts['minor'] : 0;
    }

    /**
     * Get the total number of non-deleted pages edited by the user.
     * @return int
     */
    public function countLivePagesEdited(): int
    {
        $pageCounts = $this->getPairData();
        return isset($pageCounts['edited-live']) ? (int)$pageCounts['edited-live'] : 0;
    }

    /**
     * Get the total number of deleted pages ever edited by the user.
     * @return int
     */
    public function countDeletedPagesEdited(): int
    {
        $pageCounts = $this->getPairData();
        return isset($pageCounts['edited-deleted']) ? (int)$pageCounts['edited-deleted'] : 0;
    }

    /**
     * Get the total number of pages ever edited by this user (both live and deleted).
     * @return int
     */
    public function countAllPagesEdited(): int
    {
        return $this->countLivePagesEdited() + $this->countDeletedPagesEdited();
    }

    /**
     * Get the total number of pages (both still live and those that have been deleted) created
     * by the user.
     * @return int
     */
    public function countPagesCreated(): int
    {
        return $this->countCreatedPagesLive() + $this->countPagesCreatedDeleted();
    }

    /**
     * Get the total number of pages created by the user, that have not been deleted.
     * @return int
     */
    public function countCreatedPagesLive(): int
    {
        $pageCounts = $this->getPairData();
        return isset($pageCounts['created-live']) ? (int)$pageCounts['created-live'] : 0;
    }

    /**
     * Get the total number of pages created by the user, that have since been deleted.
     * @return int
     */
    public function countPagesCreatedDeleted(): int
    {
        $pageCounts = $this->getPairData();
        return isset($pageCounts['created-deleted']) ? (int)$pageCounts['created-deleted'] : 0;
    }

    /**
     * Get the total number of pages that have been deleted by the user.
     * @return int
     */
    public function countPagesDeleted(): int
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['delete-delete']) ? (int)$logCounts['delete-delete'] : 0;
    }

    /**
     * Get the total number of pages moved by the user.
     * @return int
     */
    public function countPagesMoved(): int
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['move-move']) ? (int)$logCounts['move-move'] : 0;
    }

    /**
     * Get the total number of times the user has blocked a user.
     * @return int
     */
    public function countBlocksSet(): int
    {
        $logCounts = $this->getLogCounts();
        $reBlock = isset($logCounts['block-block']) ? (int)$logCounts['block-block'] : 0;
        return $reBlock;
    }

    /**
     * Get the total number of times the user has re-blocked a user.
     * @return int
     */
    public function countReblocksSet(): int
    {
        $logCounts = $this->getLogCounts();
        $reBlock = isset($logCounts['block-reblock']) ? (int)$logCounts['block-reblock'] : 0;
        return $reBlock;
    }

    /**
     * Get the total number of times the user has unblocked a user.
     * @return int
     */
    public function countUnblocksSet(): int
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['block-unblock']) ? (int)$logCounts['block-unblock'] : 0;
    }

    /**
     * Get the total number of blocks that have been lifted (i.e. unblocks) by this user.
     * @return int
     */
    public function countBlocksLifted(): int
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['block-unblock']) ? (int)$logCounts['block-unblock'] : 0;
    }

    /**
     * Get the total number of times the user has been blocked.
     * @return int
     */
    public function countBlocksReceived(): int
    {
        $blocks = $this->getBlocks('received');
        return count($blocks);
    }

    /**
     * Get the length of the longest block the user received, in seconds.
     * If the user is blocked, the time since the block is returned. If the block is
     * indefinite, -1 is returned. 0 if there was never a block.
     * @return int|false Number of seconds or false if it could not be determined.
     */
    public function getLongestBlockSeconds()
    {
        if (isset($this->longestBlockSeconds)) {
            return $this->longestBlockSeconds;
        }

        $blocks = $this->getBlocks('received', false);
        $this->longestBlockSeconds = false;

        // If there was never a block, the longest was zero seconds.
        if (empty($blocks)) {
            return 0;
        }

        /**
         * Keep track of the last block so we can determine the duration
         * if the current block in the loop is an unblock.
         * @var int[] [
         *              Unix timestamp,
         *              Duration in seconds (-1 if indefinite)
         *            ]
         */
        $lastBlock = [null, null];

        foreach (array_values($blocks) as $block) {
            [$timestamp, $duration] = $this->parseBlockLogEntry($block);

            if ('block' === $block['log_action']) {
                // This is a new block, so first see if the duration of the last
                // block exceeded our longest duration. -1 duration means indefinite.
                if ($lastBlock[1] > $this->longestBlockSeconds || -1 === $lastBlock[1]) {
                    $this->longestBlockSeconds = $lastBlock[1];
                }

                // Now set this as the last block.
                $lastBlock = [$timestamp, $duration];
            } elseif ('unblock' === $block['log_action']) {
                // The last block was lifted. So the duration will be the time from when the
                // last block was set to the time of the unblock.
                $timeSinceLastBlock = $timestamp - $lastBlock[0];
                if ($timeSinceLastBlock > $this->longestBlockSeconds) {
                    $this->longestBlockSeconds = $timeSinceLastBlock;

                    // Reset the last block, as it has now been accounted for.
                    $lastBlock = null;
                }
            } elseif ('reblock' === $block['log_action'] && -1 !== $lastBlock[1]) {
                // The last block was modified. So we will adjust $lastBlock to include
                // the difference of the duration of the new reblock, and time since the last block.
                // $lastBlock is left unchanged if its duration was indefinite.
                $timeSinceLastBlock = $timestamp - $lastBlock[0];
                $lastBlock[1] = $timeSinceLastBlock + $duration;
            }
        }

        // If the last block was indefinite, we'll return that as the longest duration.
        if (-1 === $lastBlock[1]) {
            return -1;
        }

        // Test if the last block is still active, and if so use the expiry as the duration.
        $lastBlockExpiry = $lastBlock[0] + $lastBlock[1];
        if ($lastBlockExpiry > time() && $lastBlockExpiry > $this->longestBlockSeconds) {
            $this->longestBlockSeconds = $lastBlock[1];
        // Otherwise, test if the duration of the last block is now the longest overall.
        } elseif ($lastBlock[1] > $this->longestBlockSeconds) {
            $this->longestBlockSeconds = $lastBlock[1];
        }

        return $this->longestBlockSeconds;
    }

    /**
     * Given a block log entry from the database, get the timestamp and duration in seconds.
     * @param  mixed[] $block Block log entry as fetched via self::getBlocks()
     * @return int[] [
     *                 Unix timestamp,
     *                 Duration in seconds (-1 if indefinite, null if unparsable or unblock)
     *               ]
     */
    public function parseBlockLogEntry(array $block): array
    {
        $timestamp = strtotime($block['log_timestamp']);
        $duration = null;

        // First check if the string is serialized, and if so parse it to get the block duration.
        if (false !== @unserialize($block['log_params'])) {
            $parsedParams = unserialize($block['log_params']);
            $durationStr = $parsedParams['5::duration'] ?? '';
        } else {
            // Old format, the duration in English + block options separated by new lines.
            $durationStr = explode("\n", $block['log_params'])[0];
        }

        if (in_array($durationStr, ['indefinite', 'infinity', 'infinite'])) {
            $duration = -1;
        }

        // Make sure $durationStr is valid just in case it is in an older, unpredictable format.
        // If invalid, $duration is left as null.
        if (strtotime($durationStr)) {
            $expiry = strtotime($durationStr, $timestamp);
            $duration = $expiry - $timestamp;
        }

        return [$timestamp, $duration];
    }

    /**
     * Get the total number of pages protected by the user.
     * @return int
     */
    public function countPagesProtected(): int
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['protect-protect']) ? (int)$logCounts['protect-protect'] : 0;
    }

    /**
     * Get the total number of pages reprotected by the user.
     * @return int
     */
    public function countPagesReprotected(): int
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['protect-modify']) ? (int)$logCounts['protect-modify'] : 0;
    }

    /**
     * Get the total number of pages unprotected by the user.
     * @return int
     */
    public function countPagesUnprotected(): int
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['protect-unprotect']) ? (int)$logCounts['protect-unprotect'] : 0;
    }

    /**
     * Get the total number of edits deleted by the user.
     * @return int
     */
    public function countEditsDeleted(): int
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['delete-revision']) ? (int)$logCounts['delete-revision'] : 0;
    }

    /**
     * Get the total number of log entries deleted by the user.
     * @return int
     */
    public function countLogsDeleted(): int
    {
        $revCounts = $this->getLogCounts();
        return isset($revCounts['delete-event']) ? (int)$revCounts['delete-event'] : 0;
    }

    /**
     * Get the total number of pages restored by the user.
     * @return int
     */
    public function countPagesRestored(): int
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['delete-restore']) ? (int)$logCounts['delete-restore'] : 0;
    }

    /**
     * Get the total number of times the user has modified the rights of a user.
     * @return int
     */
    public function countRightsModified(): int
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['rights-rights']) ? (int)$logCounts['rights-rights'] : 0;
    }

    /**
     * Get the total number of pages imported by the user (through any import mechanism:
     * interwiki, or XML upload).
     * @return int
     */
    public function countPagesImported(): int
    {
        $logCounts = $this->getLogCounts();
        $import = isset($logCounts['import-import']) ? (int)$logCounts['import-import'] : 0;
        $interwiki = isset($logCounts['import-interwiki']) ? (int)$logCounts['import-interwiki'] : 0;
        $upload = isset($logCounts['import-upload']) ? (int)$logCounts['import-upload'] : 0;
        return $import + $interwiki + $upload;
    }

    /**
     * Get the average number of edits per page (including deleted revisions and pages).
     * @return float
     */
    public function averageRevisionsPerPage(): float
    {
        if (0 == $this->countAllPagesEdited()) {
            return 0;
        }
        return round($this->countAllRevisions() / $this->countAllPagesEdited(), 3);
    }

    /**
     * Average number of edits made per day.
     * @return float
     */
    public function averageRevisionsPerDay(): float
    {
        if (0 == $this->getDays()) {
            return 0;
        }
        return round($this->countAllRevisions() / $this->getDays(), 3);
    }

    /**
     * Get the total number of edits made by the user with semi-automating tools.
     */
    public function countAutomatedEdits(): int
    {
        if ($this->autoEditCount) {
            return $this->autoEditCount;
        }
        $this->autoEditCount = $this->getRepository()->countAutomatedEdits(
            $this->project,
            $this->user
        );
        return $this->autoEditCount;
    }

    /**
     * Get the count of (non-deleted) edits made in the given timeframe to now.
     * @param string $time One of 'day', 'week', 'month', or 'year'.
     * @return int The total number of live edits.
     */
    public function countRevisionsInLast(string $time): int
    {
        $revCounts = $this->getPairData();
        return $revCounts[$time] ?? 0;
    }

    /**
     * Get the number of days between the first and last edits.
     * If there's only one edit, this is counted as one day.
     * @return int
     */
    public function getDays(): int
    {
        $first = isset($this->getFirstAndLatestActions()['rev_first']['timestamp'])
            ? new DateTime($this->getFirstAndLatestActions()['rev_first']['timestamp'])
            : false;
        $latest = isset($this->getFirstAndLatestActions()['rev_latest']['timestamp'])
            ? new DateTime($this->getFirstAndLatestActions()['rev_latest']['timestamp'])
            : false;

        if (false === $first || false === $latest) {
            return 0;
        }

        $days = $latest->diff($first)->days;

        return $days > 0 ? $days : 1;
    }

    /**
     * Get the total number of files uploaded (including those now deleted).
     * @return int
     */
    public function countFilesUploaded(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['upload-upload'] ?: 0;
    }

    /**
     * Get the total number of files uploaded to Commons (including those now deleted).
     * This is only applicable for WMF labs installations.
     * @return int
     */
    public function countFilesUploadedCommons(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['files_uploaded_commons'] ?: 0;
    }

    /**
     * Get the total number of revisions the user has sent thanks for.
     * @return int
     */
    public function thanks(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['thanks-thank'] ?: 0;
    }

    /**
     * Get the total number of approvals
     * @return int
     */
    public function approvals(): int
    {
        $logCounts = $this->getLogCounts();
        $total = $logCounts['review-approve'] +
        (!empty($logCounts['review-approve-a']) ? $logCounts['review-approve-a'] : 0) +
        (!empty($logCounts['review-approve-i']) ? $logCounts['review-approve-i'] : 0) +
        (!empty($logCounts['review-approve-ia']) ? $logCounts['review-approve-ia'] : 0);
        return $total;
    }

    /**
     * Get the total number of patrols performed by the user.
     * @return int
     */
    public function patrols(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['patrol-patrol'] ?: 0;
    }

    /**
     * Get the total number of accounts created by the user.
     * @return int
     */
    public function accountsCreated(): int
    {
        $logCounts = $this->getLogCounts();
        $create2 = $logCounts['newusers-create2'] ?: 0;
        $byemail = $logCounts['newusers-byemail'] ?: 0;
        return $create2 + $byemail;
    }

    /**
     * Get the given user's total edit counts per namespace.
     * @return array Array keys are namespace IDs, values are the edit counts.
     */
    public function namespaceTotals(): array
    {
        if ($this->namespaceTotals) {
            return $this->namespaceTotals;
        }
        $counts = $this->getRepository()->getNamespaceTotals($this->project, $this->user);
        arsort($counts);
        $this->namespaceTotals = $counts;
        return $counts;
    }

    /**
     * Get a summary of the times of day and the days of the week that the user has edited.
     * @return string[]
     */
    public function timeCard(): array
    {
        if ($this->timeCardData) {
            return $this->timeCardData;
        }
        $totals = $this->getRepository()->getTimeCard($this->project, $this->user);

        // Scale the radii: get the max, then scale each radius.
        // This looks inefficient, but there's a max of 72 elements in this array.
        $max = 0;
        foreach ($totals as $total) {
            $max = max($max, $total['value']);
        }
        foreach ($totals as &$total) {
            $total['value'] = round($total['value'] / $max * 100);
        }

        // Fill in zeros for timeslots that have no values.
        $sortedTotals = [];
        $index = 0;
        $sortedIndex = 0;
        foreach (range(1, 7) as $day) {
            foreach (range(0, 24, 2) as $hour) {
                if (isset($totals[$index]) && (int)$totals[$index]['x'] === $hour) {
                    $sortedTotals[$sortedIndex] = $totals[$index];
                    $index++;
                } else {
                    $sortedTotals[$sortedIndex] = [
                        'y' => $day,
                        'x' => $hour,
                        'value' => 0,
                    ];
                }
                $sortedIndex++;
            }
        }

        $this->timeCardData = $sortedTotals;
        return $sortedTotals;
    }

    /**
     * Get the total numbers of edits per month.
     * @param null|DateTime $currentTime - *USED ONLY FOR UNIT TESTING* so we can mock the current DateTime.
     * @return mixed[] With keys 'yearLabels', 'monthLabels' and 'totals',
     *   the latter keyed by namespace, year and then month.
     */
    public function monthCounts(?DateTime $currentTime = null): array
    {
        if (isset($this->monthCounts)) {
            return $this->monthCounts;
        }

        // Set to current month if we're not unit-testing
        if (!($currentTime instanceof DateTime)) {
            $currentTime = new DateTime('last day of this month');
        }

        $totals = $this->getRepository()->getMonthCounts($this->project, $this->user);
        $out = [
            'yearLabels' => [],  // labels for years
            'monthLabels' => [], // labels for months
            'totals' => [], // actual totals, grouped by namespace, year and then month
        ];

        /** @var DateTime $firstEdit Keep track of the date of their first edit. */
        $firstEdit = new DateTime();

        [$out, $firstEdit] = $this->fillInMonthCounts($out, $totals, $firstEdit);

        $dateRange = new DatePeriod(
            $firstEdit,
            new DateInterval('P1M'),
            $currentTime->modify('first day of this month')
        );

        $out = $this->fillInMonthTotalsAndLabels($out, $dateRange);

        // One more set of loops to sort by year/month
        foreach (array_keys($out['totals']) as $nsId) {
            ksort($out['totals'][$nsId]);

            foreach ($out['totals'][$nsId] as &$yearData) {
                ksort($yearData);
            }
        }

        // Finally, sort the namespaces
        ksort($out['totals']);

        $this->monthCounts = $out;
        return $out;
    }

    /**
     * Get the counts keyed by month and then namespace.
     * Basically the opposite of self::monthCounts()['totals'].
     * @param null|DateTime $currentTime - *USED ONLY FOR UNIT TESTING*
     *   so we can mock the current DateTime.
     * @return array Months as keys, values are counts keyed by namesapce.
     * @fixme Create API for this!
     */
    public function monthCountsWithNamespaces(?DateTime $currentTime = null): array
    {
        $countsMonthNamespace = array_fill_keys(
            array_keys($this->monthTotals($currentTime)),
            []
        );

        foreach ($this->monthCounts($currentTime)['totals'] as $ns => $years) {
            foreach ($years as $year => $months) {
                foreach ($months as $month => $count) {
                    $monthKey = $year.'-'.sprintf('%02d', $month);
                    $countsMonthNamespace[$monthKey][$ns] = $count;
                }
            }
        }

        return $countsMonthNamespace;
    }

    /**
     * Loop through the database results and fill in the values
     * for the months that we have data for.
     * @param array $out
     * @param array $totals
     * @param DateTime $firstEdit
     * @return array [
     *           string[] - Modified $out filled with month stats,
     *           DateTime - timestamp of first edit
     *         ]
     * Tests covered in self::monthCounts().
     * @codeCoverageIgnore
     */
    private function fillInMonthCounts(array $out, array $totals, DateTime $firstEdit): array
    {
        foreach ($totals as $total) {
            // Keep track of first edit
            $date = new DateTime($total['year'].'-'.$total['month'].'-01');
            if ($date < $firstEdit) {
                $firstEdit = $date;
            }

            // Collate the counts by namespace, and then year and month.
            $ns = $total['page_namespace'];
            if (!isset($out['totals'][$ns])) {
                $out['totals'][$ns] = [];
            }

            // Start array for this year if not already present.
            if (!isset($out['totals'][$ns][$total['year']])) {
                $out['totals'][$ns][$total['year']] = [];
            }

            $out['totals'][$ns][$total['year']][$total['month']] = (int) $total['count'];
        }

        return [$out, $firstEdit];
    }

    /**
     * Given the output array, fill each month's totals and labels.
     * @param array $out
     * @param DatePeriod $dateRange From first edit to present.
     * @return array Modified $out filled with month stats.
     * Tests covered in self::monthCounts().
     * @codeCoverageIgnore
     */
    private function fillInMonthTotalsAndLabels(array $out, DatePeriod $dateRange): array
    {
        foreach ($dateRange as $monthObj) {
            $year = (int) $monthObj->format('Y');
            $yearLabel = $this->i18n->dateFormat($monthObj, 'yyyy');
            $month = (int) $monthObj->format('n');
            $monthLabel = $this->i18n->dateFormat($monthObj, 'yyyy-MM');

            // Fill in labels
            $out['monthLabels'][] = $monthLabel;
            if (!in_array($yearLabel, $out['yearLabels'])) {
                $out['yearLabels'][] = $yearLabel;
            }

            foreach (array_keys($out['totals']) as $nsId) {
                if (!isset($out['totals'][$nsId][$year])) {
                    $out['totals'][$nsId][$year] = [];
                }

                if (!isset($out['totals'][$nsId][$year][$month])) {
                    $out['totals'][$nsId][$year][$month] = 0;
                }
            }
        }

        return $out;
    }

    /**
     * Get total edits for each month. Used in wikitext export.
     * @param null|DateTime $currentTime *USED ONLY FOR UNIT TESTING*
     * @return array With the months as the keys, counts as the values.
     */
    public function monthTotals(?DateTime $currentTime = null): array
    {
        $months = [];

        foreach (array_values($this->monthCounts($currentTime)['totals']) as $nsData) {
            foreach ($nsData as $year => $monthData) {
                foreach ($monthData as $month => $count) {
                    $monthLabel = $year.'-'.sprintf('%02d', $month);
                    if (!isset($months[$monthLabel])) {
                        $months[$monthLabel] = 0;
                    }
                    $months[$monthLabel] += $count;
                }
            }
        }

        return $months;
    }

    /**
     * Get the total numbers of edits per year.
     * @param null|DateTime $currentTime - *USED ONLY FOR UNIT TESTING* so we can mock the current DateTime.
     * @return mixed[] With keys 'yearLabels' and 'totals', the latter keyed by namespace then year.
     */
    public function yearCounts(?DateTime $currentTime = null): array
    {
        if (isset($this->yearCounts)) {
            return $this->yearCounts;
        }

        $out = $this->monthCounts($currentTime);

        foreach ($out['totals'] as $nsId => $years) {
            foreach ($years as $year => $months) {
                $out['totals'][$nsId][$year] = array_sum(array_values($months));
            }
        }

        $this->yearCounts = $out;
        return $out;
    }

    /**
     * Get the counts keyed by year and then namespace.
     * Basically the opposite of self::yearCounts()['totals'].
     * @param null|DateTime $currentTime - *USED ONLY FOR UNIT TESTING*
     *   so we can mock the current DateTime.
     * @return array Years as keys, values are counts keyed by namesapce.
     */
    public function yearCountsWithNamespaces(?DateTime $currentTime = null): array
    {
        $countsYearNamespace = array_fill_keys(
            array_keys($this->yearTotals($currentTime)),
            []
        );

        foreach ($this->yearCounts($currentTime)['totals'] as $ns => $years) {
            foreach ($years as $year => $count) {
                $countsYearNamespace[$year][$ns] = $count;
            }
        }

        return $countsYearNamespace;
    }

    /**
     * Get total edits for each year. Used in wikitext export.
     * @param null|DateTime $currentTime *USED ONLY FOR UNIT TESTING*
     * @return array With the years as the keys, counts as the values.
     */
    public function yearTotals(?DateTime $currentTime = null): array
    {
        $years = [];

        foreach (array_values($this->yearCounts($currentTime)['totals']) as $nsData) {
            foreach ($nsData as $year => $count) {
                if (!isset($years[$year])) {
                    $years[$year] = 0;
                }
                $years[$year] += $count;
            }
        }

        return $years;
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

    /**
     * Get the most recent n revisions across all projects.
     * @param int $max The maximum number of revisions to return.
     * @param int $offset Offset results by this number of revisions.
     * @return Edit[]
     */
    public function globalEdits(int $max, int $offset = 0): array
    {
        if (is_array($this->globalEdits)) {
            return $this->globalEdits;
        }

        // Collect all projects with any edits.
        $projects = [];
        foreach ($this->globalEditCounts() as $editCount) {
            // Don't query revisions if there aren't any.
            if (0 == $editCount['total']) {
                continue;
            }
            $projects[$editCount['project']->getDatabaseName()] = $editCount['project'];
        }

        if (0 === count($projects)) {
            return [];
        }

        // Get all revisions for those projects.
        $globalRevisionsData = $this->getRepository()
            ->getRevisions($projects, $this->user, $max, $offset);
        $globalEdits = [];
        foreach ($globalRevisionsData as $revision) {
            /** @var Project $project */
            $project = $projects[$revision['project_name']];

            $nsName = '';
            if ($revision['page_namespace']) {
                $nsName = $project->getNamespaces()[$revision['page_namespace']];
            }

            $page = $project->getRepository()
                ->getPage($project, $nsName.':'.$revision['page_title']);
            $edit = new Edit($page, $revision);
            $globalEdits[$edit->getTimestamp()->getTimestamp().'-'.$edit->getId()] = $edit;
        }

        // Sort and prune, before adding more.
        krsort($globalEdits);
        $this->globalEdits = array_slice($globalEdits, 0, $max);

        return $this->globalEdits;
    }

    /**
     * Get average edit size, and number of large and small edits.
     * @return int[]
     */
    public function getEditSizeData(): array
    {
        if (!is_array($this->editSizeData)) {
            $this->editSizeData = $this->getRepository()
                ->getEditSizeData($this->project, $this->user);
        }
        return $this->editSizeData;
    }

    /**
     * Get the total edit count of this user or 5,000 if they've made more than 5,000 edits.
     * This is used to ensure percentages of small and large edits are computed properly.
     * @return int
     */
    public function countLast5000(): int
    {
        return $this->countLiveRevisions() > 5000 ? 5000 : $this->countLiveRevisions();
    }

    /**
     * Get the number of edits under 20 bytes of the user's past 5000 edits.
     * @return int
     */
    public function countSmallEdits(): int
    {
        $editSizeData = $this->getEditSizeData();
        return isset($editSizeData['small_edits']) ? (int) $editSizeData['small_edits'] : 0;
    }

    /**
     * Get the total number of edits over 1000 bytes of the user's past 5000 edits.
     * @return int
     */
    public function countLargeEdits(): int
    {
        $editSizeData = $this->getEditSizeData();
        return isset($editSizeData['large_edits']) ? (int) $editSizeData['large_edits'] : 0;
    }

    /**
     * Get the average size of the user's past 5000 edits.
     * @return float Size in bytes.
     */
    public function averageEditSize(): float
    {
        $editSizeData = $this->getEditSizeData();
        if (isset($editSizeData['average_size'])) {
            return round($editSizeData['average_size'], 3);
        } else {
            return 0;
        }
    }
}
