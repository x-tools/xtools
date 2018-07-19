<?php
/**
 * This file contains only the EditSummary class.
 */

namespace Xtools;

use AppBundle\Helper\I18nHelper;
use DateTime;

/**
 * An EditSummary provides statistics about a user's edit summary usage over time.
 */
class EditSummary extends Model
{
    /** @var Project The project. */
    protected $project;

    /** @var User The user. */
    protected $user;

    /** @var I18nHelper For i18n and l10n. */
    protected $i18n;

    /** @var string|int The namespace to target. */
    protected $namespace;

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
     * @param string $namespace Namespace ID or 'all' for all namespaces.
     * @param int $numEditsRecent Number of edits from present to consider as 'recent'.
     */
    public function __construct(Project $project, User $user, $namespace, $numEditsRecent = 150)
    {
        $this->project = $project;
        $this->user = $user;
        $this->namespace = $namespace;
        $this->numEditsRecent = $numEditsRecent;
    }

    /**
     * Make the I18nHelper accessible to EditSummary.
     * @param I18nHelper $i18n
     * @codeCoverageIgnore
     */
    public function setI18nHelper(I18nHelper $i18n)
    {
        $this->i18n = $i18n;
    }

    /**
     * Get the total number of edits.
     * @return int
     */
    public function getTotalEdits()
    {
        return $this->data['total_edits'];
    }

    /**
     * Get the total number of minor edits.
     * @return int
     */
    public function getTotalEditsMinor()
    {
        return $this->data['total_edits_minor'];
    }

    /**
     * Get the total number of major (non-minor) edits.
     * @return int
     */
    public function getTotalEditsMajor()
    {
        return $this->data['total_edits_major'];
    }

    /**
     * Get the total number of recent minor edits.
     * @return int
     */
    public function getRecentEditsMinor()
    {
        return $this->data['recent_edits_minor'];
    }

    /**
     * Get the total number of recent major (non-minor) edits.
     * @return int
     */
    public function getRecentEditsMajor()
    {
        return $this->data['recent_edits_major'];
    }

    /**
     * Get the total number of edits with summaries.
     * @return int
     */
    public function getTotalSummaries()
    {
        return $this->data['total_summaries'];
    }

    /**
     * Get the total number of minor edits with summaries.
     * @return int
     */
    public function getTotalSummariesMinor()
    {
        return $this->data['total_summaries_minor'];
    }

    /**
     * Get the total number of major (non-minor) edits with summaries.
     * @return int
     */
    public function getTotalSummariesMajor()
    {
        return $this->data['total_summaries_major'];
    }

    /**
     * Get the total number of recent minor edits with with summaries.
     * @return int
     */
    public function getRecentSummariesMinor()
    {
        return $this->data['recent_summaries_minor'];
    }

    /**
     * Get the total number of recent major (non-minor) edits with with summaries.
     * @return int
     */
    public function getRecentSummariesMajor()
    {
        return $this->data['recent_summaries_major'];
    }

    /**
     * Get the month counts.
     * @return array Months as 'YYYY-MM' as the keys,
     *   with key 'total' and 'summaries' as the values.
     */
    public function getMonthCounts()
    {
        return $this->data['month_counts'];
    }

    /**
     * Get the whole blob of counts.
     * @return array Counts of summaries, raw edits, and per-month breakdown.
     * @codeCoverageIgnore
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Fetch the data from the database, process, and put in memory.
     * @codeCoverageIgnore
     */
    public function prepareData()
    {
        // Do our database work in the Repository, passing in reference
        // to $this->processRow so we can do post-processing here.
        $ret = $this->getRepository()->prepareData(
            $this->project,
            $this->user,
            $this->namespace,
            [$this, 'processRow']
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
     * @todo Somehow allow this to be private and still be accessible in the Repository.
     */
    public function processRow($row)
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
    private function updateMajorMinorCounts($row, $type)
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
    private function isMinor($row)
    {
        return (int)$row['rev_minor_edit'] === 1;
    }

    /**
     * Taking into account automated edit summaries, does the given
     * row in `revision` have a user-supplied edit summary?
     * @param string[] $row As retrieved from the revision table.
     * @return boolean
     */
    private function hasSummary($row)
    {
        $summary = preg_replace("/^\/\* (.*?) \*\/\s*/", '', $row['rev_comment']);
        return $summary !== '';
    }

    /**
     * Check and see if the month is set for given $monthKey and $type.
     * If it is, increment it, otherwise set it to 1.
     * @param string $monthKey In the form 'YYYY-MM'.
     * @param string $type     Either 'total' or 'summaries'.
     * @codeCoverageIgnore
     */
    private function updateMonthCounts($monthKey, $type)
    {
        if (isset($this->data['month_counts'][$monthKey][$type])) {
            $this->data['month_counts'][$monthKey][$type]++;
        } else {
            $this->data['month_counts'][$monthKey][$type] = 1;
        }
    }
}
