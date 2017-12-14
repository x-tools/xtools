<?php
/**
 * This file contains only the EditCounter class.
 */

namespace Xtools;

use DateTime;
use Exception;
use DatePeriod;
use DateInterval;
use GuzzleHttp;
use GuzzleHttp\Promise\Promise;
use Xtools\Edit;

/**
 * An EditCounter provides statistics about a user's edits on a project.
 */
class EditCounter extends Model
{

    /** @var Project The project. */
    protected $project;

    /** @var User The user. */
    protected $user;

    /** @var int[] Revision and page counts etc. */
    protected $pairData;

    /** @var string[] The start and end dates of revisions. */
    protected $revisionDates;

    /** @var int[] The total page counts. */
    protected $pageCounts;

    /** @var int[] The lot totals. */
    protected $logCounts;

    /** @var mixed[] Total numbers of edits per month */
    protected $monthCounts;

    /** @var mixed[] Total numbers of edits per year */
    protected $yearCounts;

    /** @var string[] Rights changes, keyed by timestamp then 'added' and 'removed'. */
    protected $rightsChanges;

    /** @var int[] Keys are project DB names. */
    protected $globalEditCounts;

    /** @var array Block data, with keys 'set' and 'received'. */
    protected $blocks;

    /** @var integer[] Array keys are namespace IDs, values are the edit counts */
    protected $namespaceTotals;

    /** @var int Number of semi-automated edits */
    protected $autoEditCount;

    /** @var string[] Data needed for time card chart */
    protected $timeCardData;

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
     */
    public function __construct(Project $project, User $user)
    {
        $this->project = $project;
        $this->user = $user;
    }

    /**
     * This method asynchronously fetches all the expensive data, waits
     * for each request to finish, and copies the values to the class instance.
     * @return null
     */
    public function prepareData()
    {
        $project = $this->project->getDomain();
        $username = $this->user->getUsername();

        /**
         * The URL of each endpoint, keyed by the name of the corresponding class-level
         * instance variable.
         * @var array[]
         */
        $endpoints = [
            "pairData" => "ec/pairdata/$project/$username",
            "logCounts" => "ec/logcounts/$project/$username",
            "namespaceTotals" => "ec/namespacetotals/$project/$username",
            "editSizeData" => "ec/editsizes/$project/$username",
            "monthCounts" => "ec/monthcounts/$project/$username",
            // "globalEditCounts" => "ec-globaleditcounts/$project/$username",
            "autoEditCount" => "user/automated_editcount/$project/$username",
        ];

        /**
         * Keep track of all promises so we can wait for all of them to complete.
         * @var GuzzleHttp\Promise\Promise[]
         */
        $promises = [];

        foreach ($endpoints as $key => $endpoint) {
            $promise = $this->getRepository()->queryXToolsApi($endpoint, true);
            $promises[] = $promise;

            // Handle response of $promise asynchronously.
            $promise->then(function ($response) use ($key, $endpoint) {
                $result = (array) json_decode($response->getBody()->getContents());

                $this->getRepository()
                    ->getLog()
                    ->debug("$key promise resolved successfully.");

                if (isset($result)) {
                    // Copy result to the class class instance. From here any subsequent
                    // calls to the getters (e.g. getPairData()) will return these cached values.
                    $this->{$key} = $result;
                } else {
                    // The API should *always* return something, so if $result is not set,
                    // something went wrong, so we simply won't set it and the getters will in
                    // turn re-attempt to get the data synchronously.
                    // We'll log this to see how often it happens.
                    $this->getRepository()
                        ->getLog()
                        ->error("Failed to fetch data for $endpoint via async, " .
                            "re-attempting synchoronously.");
                }
            });
        }

        // Wait for all promises to complete, even if some of them fail.
        GuzzleHttp\Promise\settle($promises)->wait();

        // Everything we need now lives on the class instance, so we're done.
        return;
    }

    /**
     * Get revision and page counts etc.
     * @return int[]
     */
    public function getPairData()
    {
        if (!is_array($this->pairData)) {
            $this->pairData = $this->getRepository()
                ->getPairData($this->project, $this->user);
        }
        return $this->pairData;
    }

    /**
     * Get revision dates.
     * @return int[]
     */
    public function getLogCounts()
    {
        if (!is_array($this->logCounts)) {
            $this->logCounts = $this->getRepository()
                ->getLogCounts($this->project, $this->user);
        }
        return $this->logCounts;
    }

    /**
     * Get block data.
     * @param string $type Either 'set', 'received'
     * @param bool $blocksOnly Whether to include only blocks, and not reblocks and unblocks.
     * @return array
     */
    protected function getBlocks($type, $blocksOnly = true)
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
                return $block['log_action'] === 'block';
            });
        }

        return $blocks;
    }

    /**
     * Get user rights changes of the given user.
     * @param Project $project
     * @param User $user
     * @return string[] Keyed by timestamp then 'added' and 'removed'.
     */
    public function getRightsChanges()
    {
        if (isset($this->rightsChanges)) {
            return $this->rightsChanges;
        }

        $this->rightsChanges = [];
        $logData = $this->getRepository()
            ->getRightsChanges($this->project, $this->user);

        foreach ($logData as $row) {
            $unserialized = unserialize($row['log_params']);
            $old = $unserialized['4::oldgroups'];
            $new = $unserialized['5::newgroups'];

            $this->rightsChanges[$row['log_timestamp']] = [
                'logId' => $row['log_id'],
                'admin' => $row['log_user_text'],
                'comment' => Edit::wikifyString($row['log_comment'], $this->project),
                'added' => array_diff($new, $old),
                'removed' => array_diff($old, $new),
            ];
        }

        return $this->rightsChanges;
    }

    /**
     * Get the total number of currently-live revisions.
     * @return int
     */
    public function countLiveRevisions()
    {
        $revCounts = $this->getPairData();
        return isset($revCounts['live']) ? (int)$revCounts['live'] : 0;
    }

    /**
     * Get the total number of the user's revisions that have been deleted.
     * @return int
     */
    public function countDeletedRevisions()
    {
        $revCounts = $this->getPairData();
        return isset($revCounts['deleted']) ? (int)$revCounts['deleted'] : 0;
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
     * Get the total number of live revisions with comments.
     * @return int
     */
    public function countRevisionsWithComments()
    {
        $revCounts = $this->getPairData();
        return isset($revCounts['with_comments']) ? (int)$revCounts['with_comments'] : 0;
    }

    /**
     * Get the total number of live revisions without comments.
     * @return int
     */
    public function countRevisionsWithoutComments()
    {
        return $this->countLiveRevisions() - $this->countRevisionsWithComments();
    }

    /**
     * Get the total number of revisions marked as 'minor' by the user.
     * @return int
     */
    public function countMinorRevisions()
    {
        $revCounts = $this->getPairData();
        return isset($revCounts['minor']) ? (int)$revCounts['minor'] : 0;
    }

    /**
     * Get the total number of non-deleted pages edited by the user.
     * @return int
     */
    public function countLivePagesEdited()
    {
        $pageCounts = $this->getPairData();
        return isset($pageCounts['edited-live']) ? (int)$pageCounts['edited-live'] : 0;
    }

    /**
     * Get the total number of deleted pages ever edited by the user.
     * @return int
     */
    public function countDeletedPagesEdited()
    {
        $pageCounts = $this->getPairData();
        return isset($pageCounts['edited-deleted']) ? (int)$pageCounts['edited-deleted'] : 0;
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
        $pageCounts = $this->getPairData();
        return isset($pageCounts['created-live']) ? (int)$pageCounts['created-live'] : 0;
    }

    /**
     * Get the total number of pages created by the user, that have since been deleted.
     * @return int
     */
    public function countPagesCreatedDeleted()
    {
        $pageCounts = $this->getPairData();
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
     * Get the total number of times the user has blocked a user.
     * @return int
     */
    public function countBlocksSet()
    {
        $logCounts = $this->getLogCounts();
        $reBlock = isset($logCounts['block-block']) ? (int)$logCounts['block-block'] : 0;
        return $reBlock;
    }

    /**
     * Get the total number of times the user has re-blocked a user.
     * @return int
     */
    public function countReblocksSet()
    {
        $logCounts = $this->getLogCounts();
        $reBlock = isset($logCounts['block-reblock']) ? (int)$logCounts['block-reblock'] : 0;
        return $reBlock;
    }

    /**
     * Get the total number of times the user has unblocked a user.
     * @return int
     */
    public function countUnblocksSet()
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['block-unblock']) ? (int)$logCounts['block-unblock'] : 0;
    }

    /**
     * Get the total number of blocks that have been lifted (i.e. unblocks) by this user.
     * @return int
     */
    public function countBlocksLifted()
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['block-unblock']) ? (int)$logCounts['block-unblock'] : 0;
    }

    /**
     * Get the total number of times the user has been blocked.
     * @return int
     */
    public function countBlocksReceived()
    {
        $blocks = $this->getBlocks('received');
        return count($blocks);
    }

    /**
     * Get the length of the longest block the user received, in seconds.
     * @return int Number of seconds or false if it could not be determined.
     *   If the user is blocked, the time since the block is returned. If the block is
     *   indefinite, -1 is returned. 0 if there was never a block.
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

        foreach ($blocks as $index => $block) {
            list($timestamp, $duration) = $this->parseBlockLogEntry($block);

            if ($block['log_action'] === 'block') {
                // This is a new block, so first see if the duration of the last
                // block exceeded our longest duration. -1 duration means indefinite.
                if ($lastBlock[1] > $this->longestBlockSeconds || $lastBlock[1] === -1) {
                    $this->longestBlockSeconds = $lastBlock[1];
                }

                // Now set this as the last block.
                $lastBlock = [$timestamp, $duration];
            } elseif ($block['log_action'] === 'unblock') {
                // The last block was lifted. So the duration will be the time from when the
                // last block was set to the time of the unblock.
                $timeSinceLastBlock = $timestamp - $lastBlock[0];
                if ($timeSinceLastBlock > $this->longestBlockSeconds) {
                    $this->longestBlockSeconds = $timeSinceLastBlock;

                    // Reset the last block, as it has now been accounted for.
                    $lastBlock = null;
                }
            } elseif ($block['log_action'] === 'reblock' && $lastBlock[1] !== -1) {
                // The last block was modified. So we will adjust $lastBlock to include
                // the difference of the duration of the new reblock, and time since the last block.
                // $lastBlock is left unchanged if its duration was indefinite.
                $timeSinceLastBlock = $timestamp - $lastBlock[0];
                $lastBlock[1] = $timeSinceLastBlock + $duration;
            }
        }

        // If the last block was indefinite, we'll return that as the longest duration.
        if ($lastBlock[1] === -1) {
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
    public function parseBlockLogEntry($block)
    {
        $timestamp = strtotime($block['log_timestamp']);
        $duration = null;

        // First check if the string is serialized, and if so parse it to get the block duration.
        if (@unserialize($block['log_params']) !== false) {
            $parsedParams = unserialize($block['log_params']);
            $durationStr = isset($parsedParams['5::duration']) ? $parsedParams['5::duration'] : null;
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
    public function countPagesProtected()
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['protect-protect']) ? (int)$logCounts['protect-protect'] : 0;
    }

    /**
     * Get the total number of pages reprotected by the user.
     * @return int
     */
    public function countPagesReprotected()
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['protect-modify']) ? (int)$logCounts['protect-modify'] : 0;
    }

    /**
     * Get the total number of pages unprotected by the user.
     * @return int
     */
    public function countPagesUnprotected()
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['protect-unprotect']) ? (int)$logCounts['protect-unprotect'] : 0;
    }

    /**
     * Get the total number of edits deleted by the user.
     * @return int
     */
    public function countEditsDeleted()
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['delete-revision']) ? (int)$logCounts['delete-revision'] : 0;
    }

    /**
     * Get the total number of pages restored by the user.
     * @return int
     */
    public function countPagesRestored()
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['delete-restore']) ? (int)$logCounts['delete-restore'] : 0;
    }

    /**
     * Get the total number of times the user has modified the rights of a user.
     * @return int
     */
    public function countRightsModified()
    {
        $logCounts = $this->getLogCounts();
        return isset($logCounts['rights-rights']) ? (int)$logCounts['rights-rights'] : 0;
    }

    /**
     * Get the total number of pages imported by the user (through any import mechanism:
     * interwiki, or XML upload).
     * @return int
     */
    public function countPagesImported()
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
    public function countAutomatedEdits()
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
    public function countRevisionsInLast($time)
    {
        $revCounts = $this->getPairData();
        return isset($revCounts[$time]) ? $revCounts[$time] : 0;
    }

    /**
     * Get the date and time of the user's first edit.
     * @return DateTime|bool The time of the first revision, or false.
     */
    public function datetimeFirstRevision()
    {
        $revDates = $this->getPairData();
        return isset($revDates['first']) ? new DateTime($revDates['first']) : false;
    }

    /**
     * Get the date and time of the user's first edit.
     * @return DateTime|bool The time of the last revision, or false.
     */
    public function datetimeLastRevision()
    {
        $revDates = $this->getPairData();
        return isset($revDates['last']) ? new DateTime($revDates['last']) : false;
    }

    /**
     * Get the number of days between the first and last edits.
     * If there's only one edit, this is counted as one day.
     * @return int
     */
    public function getDays()
    {
        $first = $this->datetimeFirstRevision();
        $last = $this->datetimeLastRevision();
        if ($first === false || $last === false) {
            return 0;
        }
        $days = $last->diff($first)->days;
        return $days > 0 ? $days : 1;
    }

    /**
     * Get the total number of files uploaded (including those now deleted).
     * @return int
     */
    public function countFilesUploaded()
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['upload-upload'] ?: 0;
    }

    /**
     * Get the total number of files uploaded to Commons (including those now deleted).
     * This is only applicable for WMF labs installations.
     * @return int
     */
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
     * Get the total number of patrols performed by the user.
     * @return int
     */
    public function patrols()
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['patrol-patrol'] ?: 0;
    }

    /**
     * Get the total number of accounts created by the user.
     * @return int
     */
    public function accountsCreated()
    {
        $logCounts = $this->getLogCounts();
        $create2 = $logCounts['newusers-create2'] ?: 0;
        $byemail = $logCounts['newusers-byemail'] ?: 0;
        return $create2 + $byemail;
    }

    /**
     * Get the given user's total edit counts per namespace.
     * @return integer[] Array keys are namespace IDs, values are the edit counts.
     */
    public function namespaceTotals()
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
    public function timeCard()
    {
        if ($this->timeCardData) {
            return $this->timeCardData;
        }
        $totals = $this->getRepository()->getTimeCard($this->project, $this->user);
        $this->timeCardData = $totals;
        return $totals;
    }

    /**
     * Get the total numbers of edits per month.
     * @param null|DateTime $currentTime - *USED ONLY FOR UNIT TESTING*
     *   so we can mock the current DateTime.
     * @return mixed[] With keys 'yearLabels', 'monthLabels' and 'totals',
     *   the latter keyed by namespace, year and then month.
     */
    public function monthCounts($currentTime = null)
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

        /** @var DateTime Keep track of the date of their first edit. */
        $firstEdit = new DateTime();

        list($out, $firstEdit) = $this->fillInMonthCounts($out, $totals, $firstEdit);

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
     * Loop through the database results and fill in the values
     * for the months that we have data for.
     * @param array $out
     * @param string[] $totals
     * @param DateTime $firstEdit
     * @return array [
     *           string[] - Modified $out filled with month stats,
     *           DateTime - timestamp of first edit
     *         ]
     * Tests covered in self::monthCounts().
     * @codeCoverageIgnore
     */
    private function fillInMonthCounts($out, $totals, $firstEdit)
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
     * @return string[] - Modified $out filled with month stats.
     * Tests covered in self::monthCounts().
     * @codeCoverageIgnore
     */
    private function fillInMonthTotalsAndLabels($out, DatePeriod $dateRange)
    {
        foreach ($dateRange as $monthObj) {
            $year = (int) $monthObj->format('Y');
            $month = (int) $monthObj->format('n');

            // Fill in labels
            $out['monthLabels'][] = $monthObj->format('Y-m');
            if (!in_array($year, $out['yearLabels'])) {
                $out['yearLabels'][] = $year;
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
     * Get the total numbers of edits per year.
     * @param null|DateTime $currentTime - *USED ONLY FOR UNIT TESTING*
     *   so we can mock the current DateTime.
     * @return mixed[] With keys 'yearLabels' and 'totals', the latter
     *   keyed by namespace then year.
     */
    public function yearCounts($currentTime = null)
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
     * Get the total edit counts for the top n projects of this user.
     * @param int $numProjects
     * @return mixed[] Each element has 'total' and 'project' keys.
     */
    public function globalEditCountsTopN($numProjects = 10)
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
    public function globalEditCountWithoutTopN($numProjects = 10)
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
     * @param bool $sorted Whether to sort the list by total, or not.
     * @return mixed[] Each element has 'total' and 'project' keys.
     */
    public function globalEditCounts($sorted = false)
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
     * @return Edit[]
     */
    public function globalEdits($max)
    {
        // Collect all projects with any edits.
        $projects = [];
        foreach ($this->globalEditCounts() as $editCount) {
            // Don't query revisions if there aren't any.
            if ($editCount['total'] == 0) {
                continue;
            }
            $projects[$editCount['project']->getDatabaseName()] = $editCount['project'];
        }

        // Get all revisions for those projects.
        $globalRevisionsData = $this->getRepository()
            ->getRevisions($projects, $this->user, $max);
        $globalEdits = [];
        foreach ($globalRevisionsData as $revision) {
            /** @var Project $project */
            $project = $projects[$revision['project_name']];
            $nsName = '';
            if ($revision['page_namespace']) {
                $nsName = $project->getNamespaces()[$revision['page_namespace']];
            }
            $page = $project->getRepository()
                ->getPage($project, $nsName . ':' . $revision['page_title']);
            $edit = new Edit($page, $revision);
            $globalEdits[$edit->getTimestamp()->getTimestamp().'-'.$edit->getId()] = $edit;
        }

        // Sort and prune, before adding more.
        krsort($globalEdits);
        $globalEdits = array_slice($globalEdits, 0, $max);
        return $globalEdits;
    }

    /**
     * Get average edit size, and number of large and small edits.
     * @return int[]
     */
    public function getEditSizeData()
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
    public function countLast5000()
    {
        return $this->countLiveRevisions() > 5000 ? 5000 : $this->countLiveRevisions();
    }

    /**
     * Get the number of edits under 20 bytes of the user's past 5000 edits.
     * @return int
     */
    public function countSmallEdits()
    {
        $editSizeData = $this->getEditSizeData();
        return isset($editSizeData['small_edits']) ? (int) $editSizeData['small_edits'] : 0;
    }

    /**
     * Get the total number of edits over 1000 bytes of the user's past 5000 edits.
     * @return int
     */
    public function countLargeEdits()
    {
        $editSizeData = $this->getEditSizeData();
        return isset($editSizeData['large_edits']) ? (int) $editSizeData['large_edits'] : 0;
    }

    /**
     * Get the average size of the user's past 5000 edits.
     * @return float Size in bytes.
     */
    public function averageEditSize()
    {
        $editSizeData = $this->getEditSizeData();
        if (isset($editSizeData['average_size'])) {
            return round($editSizeData['average_size'], 3);
        } else {
            return 0;
        }
    }
}
