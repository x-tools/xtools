<?php
/**
 * This file contains only the EditSummary class.
 */

declare(strict_types = 1);

namespace AppBundle\Model;

use AppBundle\Helper\I18nHelper;
use DateTime;

/**
 * An EditSummary provides statistics about a user's edit summary usage over time.
 */
class EditSummary extends Model
{
    /** @var I18nHelper For i18n and l10n. */
    protected $i18n;

    /** @var int Number of edits from present to consider as 'recent'. */
    protected $numEditsRecent;

    /**
     * Counts of summaries, raw edits, and per-month breakdown.
     * Keys are underscored because this also is served in the API.
     * @var array
     */
    protected $data = [
        'recent_edits_minor' => 0,
        'recent_edits_major' => 0,
        'total_edits_minor' => 0,
        'total_edits_major' => 0,
        'total_edits' => 0,
        'recent_summaries_minor' => 0,
        'recent_summaries_major' => 0,
        'total_summaries_minor' => 0,
        'total_summaries_major' => 0,
        'total_summaries' => 0,
        'month_counts' => [],
    ];

    /**
     * EditSummary constructor.
     *
     * @param Project $project The project we're working with.
     * @param User $user The user to process.
     * @param int|string $namespace Namespace ID or 'all' for all namespaces.
     * @param int|false $start Start date in a format accepted by strtotime()
     * @param int|false $end End date in a format accepted by strtotime()
     * @param int $numEditsRecent Number of edits from present to consider as 'recent'.
     */
    public function __construct(
        Project $project,
        User $user,
        $namespace,
        $start = false,
        $end = false,
        int $numEditsRecent = 150
    ) {
        $this->project = $project;
        $this->user = $user;
        $this->namespace = $namespace;
        $this->start = false === $start ? '' : date('Y-m-d', $start);
        $this->end = false === $end ? '' : date('Y-m-d', $end);
        $this->numEditsRecent = $numEditsRecent;
    }

    /**
     * Make the I18nHelper accessible to EditSummary.
     * @param I18nHelper $i18n
     * @codeCoverageIgnore
     */
    public function setI18nHelper(I18nHelper $i18n): void
    {
        $this->i18n = $i18n;
    }

    /**
     * Get the total number of edits.
     * @return int
     */
    public function getTotalEdits(): int
    {
        return $this->data['total_edits'];
    }

    /**
     * Get the total number of minor edits.
     * @return int
     */
    public function getTotalEditsMinor(): int
    {
        return $this->data['total_edits_minor'];
    }

    /**
     * Get the total number of major (non-minor) edits.
     * @return int
     */
    public function getTotalEditsMajor(): int
    {
        return $this->data['total_edits_major'];
    }

    /**
     * Get the total number of recent minor edits.
     * @return int
     */
    public function getRecentEditsMinor(): int
    {
        return $this->data['recent_edits_minor'];
    }

    /**
     * Get the total number of recent major (non-minor) edits.
     * @return int
     */
    public function getRecentEditsMajor(): int
    {
        return $this->data['recent_edits_major'];
    }

    /**
     * Get the total number of edits with summaries.
     * @return int
     */
    public function getTotalSummaries(): int
    {
        return $this->data['total_summaries'];
    }

    /**
     * Get the total number of minor edits with summaries.
     * @return int
     */
    public function getTotalSummariesMinor(): int
    {
        return $this->data['total_summaries_minor'];
    }

    /**
     * Get the total number of major (non-minor) edits with summaries.
     * @return int
     */
    public function getTotalSummariesMajor(): int
    {
        return $this->data['total_summaries_major'];
    }

    /**
     * Get the total number of recent minor edits with with summaries.
     * @return int
     */
    public function getRecentSummariesMinor(): int
    {
        return $this->data['recent_summaries_minor'];
    }

    /**
     * Get the total number of recent major (non-minor) edits with with summaries.
     * @return int
     */
    public function getRecentSummariesMajor(): int
    {
        return $this->data['recent_summaries_major'];
    }

    /**
     * Get the month counts.
     * @return array Months as 'YYYY-MM' as the keys,
     *   with key 'total' and 'summaries' as the values.
     */
    public function getMonthCounts(): array
    {
        return $this->data['month_counts'];
    }

    /**
     * Get the whole blob of counts.
     * @return array Counts of summaries, raw edits, and per-month breakdown.
     * @codeCoverageIgnore
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Fetch the data from the database, process, and put in memory.
     * @codeCoverageIgnore
     */
    public function prepareData(): array
    {
        // Do our database work in the Repository, passing in reference
        // to $this->processRow so we can do post-processing here.
        $ret = $this->getRepository()->prepareData(
            [$this, 'processRow'],
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end
        );

        // We want to keep all the default zero values if there are no contributions.
        if (count($ret) > 0) {
            $this->data = $ret;
        }

        return $ret;
    }

    /**
     * Process a single row from the database, updating class properties with counts.
     * @param string[] $row As retrieved from the revision table.
     * @return string[]
     */
    public function processRow(array $row): array
    {
        // Extract the date out of the date field
        $timestamp = DateTime::createFromFormat('YmdHis', $row['rev_timestamp']);

        $monthKey = $this->i18n->dateFormat($timestamp, 'yyyy-MM');

        // Grand total for number of edits
        $this->data['total_edits']++;

        // Update total edit count for this month.
        $this->updateMonthCounts($monthKey, 'total');

        // Total edit summaries
        if ($this->hasSummary($row)) {
            $this->data['total_summaries']++;

            // Update summary count for this month.
            $this->updateMonthCounts($monthKey, 'summaries');
        }

        if ($this->isMinor($row)) {
            $this->updateMajorMinorCounts($row, 'minor');
        } else {
            $this->updateMajorMinorCounts($row, 'major');
        }

        return $this->data;
    }

    /**
     * Given the row in `revision`, update minor counts.
     * @param string[] $row As retrieved from the revision table.
     * @param string $type Either 'minor' or 'major'.
     * @codeCoverageIgnore
     */
    private function updateMajorMinorCounts(array $row, string $type): void
    {
        $this->data['total_edits_'.$type]++;

        $hasSummary = $this->hasSummary($row);
        $isRecent = $this->data['recent_edits_'.$type] < $this->numEditsRecent;

        if ($hasSummary) {
            $this->data['total_summaries_'.$type]++;
        }

        // Update recent edits counts.
        if ($isRecent) {
            $this->data['recent_edits_'.$type]++;

            if ($hasSummary) {
                $this->data['recent_summaries_'.$type]++;
            }
        }
    }

    /**
     * Was the given row in `revision` marked as a minor edit?
     * @param string[] $row As retrieved from the revision table.
     * @return boolean
     */
    private function isMinor(array $row): bool
    {
        return 1 === (int)$row['rev_minor_edit'];
    }

    /**
     * Taking into account automated edit summaries, does the given
     * row in `revision` have a user-supplied edit summary?
     * @param string[] $row As retrieved from the revision table.
     * @return boolean
     */
    private function hasSummary(array $row): bool
    {
        $summary = preg_replace("/^\/\* (.*?) \*\/\s*/", '', $row['comment']);
        return '' !== $summary;
    }

    /**
     * Check and see if the month is set for given $monthKey and $type.
     * If it is, increment it, otherwise set it to 1.
     * @param string $monthKey In the form 'YYYY-MM'.
     * @param string $type     Either 'total' or 'summaries'.
     * @codeCoverageIgnore
     */
    private function updateMonthCounts(string $monthKey, string $type): void
    {
        if (isset($this->data['month_counts'][$monthKey][$type])) {
            $this->data['month_counts'][$monthKey][$type]++;
        } else {
            $this->data['month_counts'][$monthKey][$type] = 1;
        }
    }
}
