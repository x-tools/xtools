<?php

declare(strict_types = 1);

namespace App\Model;

use App\Helper\I18nHelper;
use App\Repository\EditCounterRepository;
use DateInterval;
use DatePeriod;
use DateTime;

/**
 * An EditCounter provides statistics about a user's edits on a project.
 */
class EditCounter extends Model
{
    protected I18nHelper $i18n;
    protected UserRights $userRights;

    /** @var int[] Revision and page counts etc. */
    protected array $pairData;

    /** @var string[] The IDs and timestamps of first/latest edit and logged action. */
    protected array $firstAndLatestActions;

    /** @var int[] The lot totals. */
    protected array $logCounts;

    /** @var array Total numbers of edits per month */
    protected array $monthCounts;

    /** @var array Total numbers of edits per year */
    protected array $yearCounts;

    /** @var array Block data, with keys 'set' and 'received'. */
    protected array $blocks;

    /** @var integer[] Array keys are namespace IDs, values are the edit counts. */
    protected array $namespaceTotals;

    /** @var int Number of semi-automated edits. */
    protected int $autoEditCount;

    /** @var string[] Data needed for time card chart. */
    protected array $timeCardData;

    /**
     * Various data on the last 5000 edits.
     * @var string[]
     */
    protected array $editData;

    /**
     * Duration of the longest block in seconds; -1 if indefinite,
     *   or false if could not be parsed from log params
     * @var int|bool
     */
    protected $longestBlockSeconds;

    /** @var int Number of times the user has been thanked. */
    protected int $thanksReceived;

    /**
     * EditCounter constructor.
     * @param EditCounterRepository $repository
     * @param I18nHelper $i18n
     * @param UserRights $userRights
     * @param Project $project The base project to count edits
     * @param User $user
     */
    public function __construct(
        EditCounterRepository $repository,
        I18nHelper $i18n,
        UserRights $userRights,
        Project $project,
        User $user
    ) {
        $this->repository = $repository;
        $this->i18n = $i18n;
        $this->userRights = $userRights;
        $this->project = $project;
        $this->user = $user;
    }

    /**
     * @return UserRights
     */
    public function getUserRights(): UserRights
    {
        return $this->userRights;
    }

    /**
     * Get revision and page counts etc.
     * @return int[]
     */
    public function getPairData(): array
    {
        if (!isset($this->pairData)) {
            $this->pairData = $this->repository->getPairData($this->project, $this->user);
        }
        return $this->pairData;
    }

    /**
     * Get revision dates.
     * @return array
     */
    public function getLogCounts(): array
    {
        if (!isset($this->logCounts)) {
            $this->logCounts = $this->repository->getLogCounts($this->project, $this->user);
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
            $this->firstAndLatestActions = $this->repository->getFirstAndLatestActions(
                $this->project,
                $this->user
            );
        }
        return $this->firstAndLatestActions;
    }

    /**
     * Get the number of times the user was thanked.
     * @return int
     * @codeCoverageIgnore Simply returns the result of an SQL query.
     */
    public function getThanksReceived(): int
    {
        if (!isset($this->thanksReceived)) {
            $this->thanksReceived = $this->repository->getThanksReceived($this->project, $this->user);
        }
        return $this->thanksReceived;
    }

    /**
     * Get block data.
     * @param string $type Either 'set', 'received'
     * @param bool $blocksOnly Whether to include only blocks, and not reblocks and unblocks.
     * @return array
     */
    public function getBlocks(string $type, bool $blocksOnly = true): array
    {
        if (isset($this->blocks[$type]) && is_array($this->blocks[$type])) {
            $blocks = $this->blocks[$type];
        } else {
            $method = "getBlocks".ucfirst($type);
            $blocks = $this->repository->$method($this->project, $this->user);
            $this->blocks[$type] = $blocks;
        }

        // Filter out unblocks unless requested.
        // Expressly don't store this.
        if ($blocksOnly) {
            $blocks = array_filter($blocks, function ($block) {
                return ('block' === $block['log_action'] || 'reblock' === $block['log_action']);
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
        return $revCounts['live'] ?? 0;
    }

    /**
     * Get the total number of the user's revisions that have been deleted.
     * @return int
     */
    public function countDeletedRevisions(): int
    {
        $revCounts = $this->getPairData();
        return $revCounts['deleted'] ?? 0;
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
     * Get the total number of revisions marked as 'minor' by the user.
     * @return int
     */
    public function countMinorRevisions(): int
    {
        $revCounts = $this->getPairData();
        return $revCounts['minor'] ?? 0;
    }

    /**
     * Get the total number of non-deleted pages edited by the user.
     * @return int
     */
    public function countLivePagesEdited(): int
    {
        $pageCounts = $this->getPairData();
        return $pageCounts['edited-live'] ?? 0;
    }

    /**
     * Get the total number of deleted pages ever edited by the user.
     * @return int
     */
    public function countDeletedPagesEdited(): int
    {
        $pageCounts = $this->getPairData();
        return $pageCounts['edited-deleted'] ?? 0;
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
        return $pageCounts['created-live'] ?? 0;
    }

    /**
     * Get the total number of pages created by the user, that have since been deleted.
     * @return int
     */
    public function countPagesCreatedDeleted(): int
    {
        $pageCounts = $this->getPairData();
        return $pageCounts['created-deleted'] ?? 0;
    }

    /**
     * Get the total number of pages that have been deleted by the user.
     * @return int
     */
    public function countPagesDeleted(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['delete-delete'] ?? 0;
    }

    /**
     * Get the total number of pages moved by the user.
     * @return int
     */
    public function countPagesMoved(): int
    {
        $logCounts = $this->getLogCounts();
        return ($logCounts['move-move'] ?? 0) +
            ($logCounts['move-move_redir'] ?? 0);
    }

    /**
     * Get the total number of times the user has blocked a user.
     * @return int
     */
    public function countBlocksSet(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['block-block'] ?? 0;
    }

    /**
     * Get the total number of times the user has re-blocked a user.
     * @return int
     */
    public function countReblocksSet(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['block-reblock'] ?? 0;
    }

    /**
     * Get the total number of times the user has unblocked a user.
     * @return int
     */
    public function countUnblocksSet(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['block-unblock'] ?? 0;
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
        // @codeCoverageIgnoreStart
        if (isset($this->longestBlockSeconds)) {
            return $this->longestBlockSeconds;
        }
        // @codeCoverageIgnoreEnd

        $blocks = $this->getBlocks('received', false);
        $this->longestBlockSeconds = false;

        // If there was never a block, the longest was zero seconds.
        if (empty($blocks)) {
            return 0;
        }

        /**
         * Keep track of the last block so we can determine the duration
         * if the current block in the loop is an unblock.
         * @var int[] $lastBlock
         *   [
         *     Unix timestamp,
         *     Duration in seconds (-1 if indefinite)
         *   ]
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
                    $lastBlock = [null, null];
                }
            } elseif ('reblock' === $block['log_action'] && -1 !== $lastBlock[1]) {
                // The last block was modified.
                // $lastBlock is left unchanged if its duration was indefinite.
                
                // If this reblock set the block to infinite, set lastBlock manually to infinite
                if (-1 === $duration) {
                    $lastBlock[1] = -1;
                // Otherwise, we will adjust $lastBlock to include
                // the difference of the duration of the new reblock, and time since the last block.
                // we can't use this when $duration === -1.
                } else {
                    $timeSinceLastBlock = $timestamp - $lastBlock[0];
                    $lastBlock[1] = $timeSinceLastBlock + $duration;
                }
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
     * @param array $block Block log entry as fetched via self::getBlocks()
     * @return int[] [
     *                 Unix timestamp,
     *                 Duration in seconds (-1 if indefinite, null if unparsable or unblock)
     *               ]
     */
    public function parseBlockLogEntry(array $block): array
    {
        $timestamp = strtotime($block['log_timestamp']);
        $duration = null;

        // log_params may be null, but we need to treat it like a string.
        $block['log_params'] = (string)$block['log_params'];

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
        return $logCounts['protect-protect'] ?? 0;
    }

    /**
     * Get the total number of pages reprotected by the user.
     * @return int
     */
    public function countPagesReprotected(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['protect-modify'] ?? 0;
    }

    /**
     * Get the total number of pages unprotected by the user.
     * @return int
     */
    public function countPagesUnprotected(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['protect-unprotect'] ?? 0;
    }

    /**
     * Get the total number of edits deleted by the user.
     * @return int
     */
    public function countEditsDeleted(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['delete-revision'] ?? 0;
    }

    /**
     * Get the total number of log entries deleted by the user.
     * @return int
     */
    public function countLogsDeleted(): int
    {
        $revCounts = $this->getLogCounts();
        return $revCounts['delete-event'] ?? 0;
    }

    /**
     * Get the total number of pages restored by the user.
     * @return int
     */
    public function countPagesRestored(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['delete-restore'] ?? 0;
    }

    /**
     * Get the total number of times the user has modified the rights of a user.
     * @return int
     */
    public function countRightsModified(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['rights-rights'] ?? 0;
    }

    /**
     * Get the total number of pages imported by the user (through any import mechanism:
     * interwiki, or XML upload).
     * @return int
     */
    public function countPagesImported(): int
    {
        $logCounts = $this->getLogCounts();
        $import = $logCounts['import-import'] ?? 0;
        $interwiki = $logCounts['import-interwiki'] ?? 0;
        $upload = $logCounts['import-upload'] ?? 0;
        return $import + $interwiki + $upload;
    }

    /**
     * Get the number of changes the user has made to AbuseFilters.
     * @return int
     */
    public function countAbuseFilterChanges(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['abusefilter-modify'] ?? 0;
    }

    /**
     * Get the number of page content model changes made by the user.
     * @return int
     */
    public function countContentModelChanges(): int
    {
        $logCounts = $this->getLogCounts();
        $new = $logCounts['contentmodel-new'] ?? 0;
        $modified = $logCounts['contentmodel-change'] ?? 0;
        return $new + $modified;
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
        $fileCounts = $this->repository->getFileCounts($this->project, $this->user);
        return $fileCounts['files_uploaded_commons'] ?? 0;
    }

    /**
     * Get the total number of files that were renamed (including those now deleted).
     */
    public function countFilesMoved(): int
    {
        $fileCounts = $this->repository->getFileCounts($this->project, $this->user);
        return $fileCounts['files_moved'] ?? 0;
    }

    /**
     * Get the total number of files that were renamed on Commons (including those now deleted).
     */
    public function countFilesMovedCommons(): int
    {
        $fileCounts = $this->repository->getFileCounts($this->project, $this->user);
        return $fileCounts['files_moved_commons'] ?? 0;
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
        return (!empty($logCounts['review-approve']) ? $logCounts['review-approve'] : 0) +
            (!empty($logCounts['review-approve2']) ? $logCounts['review-approve2'] : 0) +
            (!empty($logCounts['review-approve-i']) ? $logCounts['review-approve-i'] : 0) +
            (!empty($logCounts['review-approve2-i']) ? $logCounts['review-approve2-i'] : 0);
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
     * Get the total number of PageCurations reviews performed by the user.
     * (Only exists on English Wikipedia.)
     * @return int
     */
    public function reviews(): int
    {
        $logCounts = $this->getLogCounts();
        $reviewed = $logCounts['pagetriage-curation-reviewed'] ?: 0;
        $reviewedRedirect = $logCounts['pagetriage-curation-reviewed-redirect'] ?: 0;
        $reviewedArticle = $logCounts['pagetriage-curation-reviewed-article'] ?: 0;
        return ($reviewed + $reviewedRedirect + $reviewedArticle);
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
     * Get the number of history merges performed by the user.
     * @return int
     */
    public function merges(): int
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['merge-merge'];
    }

    /**
     * Get the given user's total edit counts per namespace.
     * @return array Array keys are namespace IDs, values are the edit counts.
     */
    public function namespaceTotals(): array
    {
        if (isset($this->namespaceTotals)) {
            return $this->namespaceTotals;
        }
        $counts = $this->repository->getNamespaceTotals($this->project, $this->user);
        arsort($counts);
        $this->namespaceTotals = $counts;
        return $counts;
    }

    /**
     * Get the total number of live edits by summing the namespace totals.
     * This is used in the view for namespace totals so we don't unnecessarily run the self::getPairData() query.
     * @return int
     */
    public function liveRevisionsFromNamespaces(): int
    {
        return array_sum($this->namespaceTotals());
    }

    /**
     * Get a summary of the times of day and the days of the week that the user has edited.
     * @return string[]
     */
    public function timeCard(): array
    {
        // @codeCoverageIgnoreStart
        if (isset($this->timeCardData)) {
            return $this->timeCardData;
        }
        // @codeCoverageIgnoreEnd
        $totals = $this->repository->getTimeCard($this->project, $this->user);

        // Scale the radii: get the max, then scale each radius.
        // This looks inefficient, but there's a max of 72 elements in this array.
        $max = 0;
        foreach ($totals as $total) {
            $max = max($max, $total['value']);
        }
        foreach ($totals as $index => $total) {
            $totals[$index]['scale'] = round(($total['value'] / $max) * 20);
        }

        // Fill in zeros for timeslots that have no values.
        $sortedTotals = [];
        $index = 0;
        foreach (range(1, 7) as $day) {
            foreach (range(0, 23) as $hour) {
                if (isset($totals[$index])
                    && (int)$totals[$index]['day_of_week'] === $day
                    && (int)$totals[$index]['hour'] === $hour
                ) {
                    $sortedTotals[] = $totals[$index];
                    $index++;
                } else {
                    $sortedTotals[] = [
                        'day_of_week' => $day,
                        'hour' => $hour,
                        'value' => 0,
                    ];
                }
            }
        }

        $this->timeCardData = $sortedTotals;
        return $sortedTotals;
    }

    /**
     * Get the total numbers of edits per month.
     * @param null|DateTime $currentTime - *USED ONLY FOR UNIT TESTING* so we can mock the current DateTime.
     * @return array With keys 'yearLabels', 'monthLabels' and 'totals',
     *   the latter keyed by namespace, then year/month.
     */
    public function monthCounts(?DateTime $currentTime = null): array
    {
        if (isset($this->monthCounts)) {
            return $this->monthCounts;
        }

        // @codeCoverageIgnoreStart
        // Set to current month if we're not unit-testing
        if (!($currentTime instanceof DateTime)) {
            $currentTime = new DateTime('last day of this month');
        }
        // @codeCoverageIgnoreEnd

        $totals = $this->repository->getMonthCounts($this->project, $this->user);
        $out = [
            'yearLabels' => [],  // labels for years
            'monthLabels' => [], // labels for months
            'totals' => [], // actual totals, grouped by namespace, year and then month
        ];

        /** Keep track of the date of their first edit. */
        $firstEdit = new DateTime();

        [$out, $firstEdit] = $this->fillInMonthCounts($out, $totals, $firstEdit);

        $dateRange = new DatePeriod(
            $firstEdit,
            new DateInterval('P1M'),
            $currentTime->modify('first day of this month')
        );

        $out = $this->fillInMonthTotalsAndLabels($out, $dateRange);

        // One more loop to sort by year/month
        foreach (array_keys($out['totals']) as $nsId) {
            ksort($out['totals'][$nsId]);
        }

        // Finally, sort the namespaces
        ksort($out['totals']);

        $this->monthCounts = $out;
        return $out;
    }

    /**
     * Get the counts keyed by month and then namespace.
     * Basically the opposite of self::monthCounts()['totals'].
     * @param null|DateTime $currentTime - *USED ONLY FOR UNIT TESTING* so we can mock the current DateTime.
     * @return array Months as keys, values are counts keyed by namesapce.
     * @fixme Create API for this!
     */
    public function monthCountsWithNamespaces(?DateTime $currentTime = null): array
    {
        $countsMonthNamespace = array_fill_keys(
            array_values($this->monthCounts($currentTime)['monthLabels']),
            []
        );

        foreach ($this->monthCounts($currentTime)['totals'] as $ns => $months) {
            foreach ($months as $month => $count) {
                $countsMonthNamespace[$month][$ns] = $count;
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
     */
    private function fillInMonthCounts(array $out, array $totals, DateTime $firstEdit): array
    {
        foreach ($totals as $total) {
            // Keep track of first edit
            $date = new DateTime($total['year'].'-'.$total['month'].'-01');
            if ($date < $firstEdit) {
                $firstEdit = $date;
            }

            // Collate the counts by namespace, and then YYYY-MM.
            $ns = $total['namespace'];
            $out['totals'][$ns][$date->format('Y-m')] = (int)$total['count'];
        }

        return [$out, $firstEdit];
    }

    /**
     * Given the output array, fill each month's totals and labels.
     * @param array $out
     * @param DatePeriod $dateRange From first edit to present.
     * @return array Modified $out filled with month stats.
     */
    private function fillInMonthTotalsAndLabels(array $out, DatePeriod $dateRange): array
    {
        foreach ($dateRange as $monthObj) {
            $yearLabel = $monthObj->format('Y');
            $monthLabel = $monthObj->format('Y-m');

            // Fill in labels
            $out['monthLabels'][] = $monthLabel;
            if (!in_array($yearLabel, $out['yearLabels'])) {
                $out['yearLabels'][] = $yearLabel;
            }

            foreach (array_keys($out['totals']) as $nsId) {
                if (!isset($out['totals'][$nsId][$monthLabel])) {
                    $out['totals'][$nsId][$monthLabel] = 0;
                }
            }
        }

        return $out;
    }

    /**
     * Get the total numbers of edits per year.
     * @param null|DateTime $currentTime - *USED ONLY FOR UNIT TESTING* so we can mock the current DateTime.
     * @return array With keys 'yearLabels' and 'totals', the latter keyed by namespace then year.
     */
    public function yearCounts(?DateTime $currentTime = null): array
    {
        // @codeCoverageIgnoreStart
        if (isset($this->yearCounts)) {
            return $this->yearCounts;
        }
        // @codeCoverageIgnoreEnd

        $monthCounts = $this->monthCounts($currentTime);
        $yearCounts = [
            'yearLabels' => $monthCounts['yearLabels'],
            'totals' => [],
        ];

        foreach ($monthCounts['totals'] as $nsId => $months) {
            foreach ($months as $month => $count) {
                $year = substr($month, 0, 4);
                if (!isset($yearCounts['totals'][$nsId][$year])) {
                    $yearCounts['totals'][$nsId][$year] = 0;
                }
                $yearCounts['totals'][$nsId][$year] += $count;
            }
        }

        $this->yearCounts = $yearCounts;
        return $yearCounts;
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

        foreach ($this->yearCounts($currentTime)['totals'] as $nsData) {
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
     * Get average edit size, number of large and small edits, and change tags.
     * @return array With keys "sizes", "average_size", "small_edits", "large_edits", "tag_lists".
     */
    public function getEditData(): array
    {
        if (!isset($this->editData)) {
            $this->editData = $this->repository
                ->getEditData($this->project, $this->user);
        }
        return $this->editData;
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
     * Get the ProofreadPage tagged quality changes in the last 5000 edits.
     * @return int[] With keys 0, 1, 2, 3, 4, and 'total'.
     */
    public function countQualityChanges(): array
    {
        $tagLists = $this->getEditData()['tag_lists'];
        $res = [
            0 => 0,
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            'total' => 0,
        ];
        foreach ($tagLists as $list) {
            if (null !== $list) {
                $found = false;
                foreach ($list as $tag) {
                    if (preg_match('/^proofreadpage\-quality[0-4]$/', $tag)) {
                        $res[intval(substr($tag, -1))] += 1;
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $res['total'] += 1;
                }
            }
        }
        return $res;
    }

    /**
     * Get the average size of the user's past 5000 edits.
     * @return float Size in bytes.
     */
    public function averageEditSize(): float
    {
        $editData = $this->getEditData();
        if (isset($editData['average_size'])) {
            return round((float)$editData['average_size'], 3);
        } else {
            return 0;
        }
    }
}
