<?php
/**
 * This file contains only the ArticleInfo class.
 */

namespace Xtools;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DomCrawler\Crawler;
use DateTime;

/**
 * An ArticleInfo provides statistics about a page on a project. This model does not
 * have a separate Repository because it needs to use individual SQL statements to
 * traverse the page's history, saving class instance variables along the way.
 */
class ArticleInfo extends Model
{
    /** @const string[] Domain names of wikis supported by WikiWho. */
    const TEXTSHARE_WIKIS = [
        'en.wikipedia.org',
        'de.wikipedia.org',
        'eu.wikipedia.org',
        'tr.wikipedia.org',
        'es.wikipedia.org',
    ];

    /** @var Container The application's DI container. */
    protected $container;

    /** @var Page The page. */
    protected $page;

    /** @var false|int From what date to obtain records. */
    protected $startDate;

    /** @var false|int To what date to obtain records. */
    protected $endDate;

    /** @var int Number of revisions that belong to the page. */
    protected $numRevisions;

    /** @var int Maximum number of revisions to process, as configured. */
    protected $maxRevisions;

    /** @var int Number of revisions that were actually processed. */
    protected $numRevisionsProcessed;

    /**
     * Various statistics about editors to the page. These are not User objects
     * so as to preserve memory.
     * @var mixed[]
     */
    protected $editors;

    /** @var mixed[] The top 10 editors to the page by number of edits. */
    protected $topTenEditorsByEdits;

    /** @var mixed[] The top 10 editors to the page by added text. */
    protected $topTenEditorsByAdded;

    /** @var int Number of edits made by the top 10 editors. */
    protected $topTenCount;

    /** @var mixed[] Various statistics about bots that edited the page. */
    protected $bots;

    /** @var int Number of edits made to the page by bots. */
    protected $botRevisionCount;

    /** @var mixed[] Various counts about each individual year and month of the page's history. */
    protected $yearMonthCounts;

    /** @var Edit The first edit to the page. */
    protected $firstEdit;

    /** @var Edit The last edit to the page. */
    protected $lastEdit;

    /** @var Edit Edit that made the largest addition by number of bytes. */
    protected $maxAddition;

    /** @var Edit Edit that made the largest deletion by number of bytes. */
    protected $maxDeletion;

    /** @var int[] Number of in and outgoing links and redirects to the page. */
    protected $linksAndRedirects;

    /** @var string[] Assessments of the page (see Page::getAssessments). */
    protected $assessments;

    /**
     * Maximum number of edits that were created across all months. This is used as a comparison
     * for the bar charts in the months section.
     * @var int
     */
    protected $maxEditsPerMonth;

    /** @var string[] List of (semi-)automated tools that were used to edit the page. */
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
        'year' => 0
    ];

    /** @var string[] List of wikidata and Checkwiki errors. */
    protected $bugs;

    /** @var array List of editors and the percentage of the current content that they authored. */
    protected $textshares;

    /** @var array Number of categories, templates and files on the page. */
    protected $transclusionData;

    /**
     * ArticleInfo constructor.
     * @param Page $page The page to process.
     * @param Container $container The DI container.
     * @param false|int $start From what date to obtain records.
     * @param false|int $end To what date to obtain records.
     */
    public function __construct(Page $page, Container $container, $start = false, $end = false)
    {
        $this->page = $page;
        $this->container = $container;
        $this->startDate = $start;
        $this->endDate = $end;
    }

    /**
     * Get date opening date range.
     * @return false|int
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * Get date closing date range.
     * @return false|int
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * Get the day of last date we should show in the month/year sections,
     * based on $this->endDate or the current date.
     * @return int As Unix timestamp.
     */
    private function getLastDay()
    {
        if ($this->endDate !== false) {
            return (new DateTime('@'.$this->endDate))
                ->modify('last day of this month')
                ->getTimestamp();
        } else {
            return strtotime('last day of this month');
        }
    }

    /**
     * Has date range?
     * @return bool
     */
    public function hasDateRange()
    {
        return $this->startDate !== false || $this->endDate !== false;
    }

    /**
     * Return the start/end date values as associative array,
     * with YYYY-MM-DD as the date format. This is used mainly as
     * a helper to pass to the pageviews Twig macros.
     * @return array
     */
    public function getDateParams()
    {
        if (!$this->hasDateRange()) {
            return [];
        }

        $ret = [
            'start' => $this->firstEdit->getTimestamp()->format('Y-m-d'),
            'end' => $this->lastEdit->getTimestamp()->format('Y-m-d'),
        ];

        if ($this->startDate !== false) {
            $ret['start'] = date('Y-m-d', $this->startDate);
        }
        if ($this->endDate !== false) {
            $ret['end'] = date('Y-m-d', $this->endDate);
        }

        return $ret;
    }

    /**
     * Shorthand to get the page's project.
     * @return Project
     * @codeCoverageIgnore
     */
    public function getProject()
    {
        return $this->page->getProject();
    }

    /**
     * Get the number of revisions belonging to the page.
     * @return int
     */
    public function getNumRevisions()
    {
        if (!isset($this->numRevisions)) {
            $this->numRevisions = $this->page->getNumRevisions(null, $this->startDate, $this->endDate);
        }
        return $this->numRevisions;
    }

    /**
     * Get the maximum number of revisions that we should process.
     * @return int
     */
    public function getMaxRevisions()
    {
        if (!isset($this->maxRevisions)) {
            $this->maxRevisions = (int) $this->container->getParameter('app.max_page_revisions');
        }
        return $this->maxRevisions;
    }

    /**
     * Get the number of revisions that are actually getting processed.
     * This goes by the app.max_page_revisions parameter, or the actual
     * number of revisions, whichever is smaller.
     * @return int
     */
    public function getNumRevisionsProcessed()
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
     * Are there more revisions than we should process, based on the config?
     * @return bool
     */
    public function tooManyRevisions()
    {
        return $this->getMaxRevisions() > 0 && $this->getNumRevisions() > $this->getMaxRevisions();
    }

    /**
     * Fetch and store all the data we need to show the ArticleInfo view.
     * @codeCoverageIgnore
     */
    public function prepareData()
    {
        $this->parseHistory();
        $this->setLogsEvents();

        // Bots need to be set before setting top 10 counts.
        $this->setBots();

        $this->setTopTenCounts();
    }

    /**
     * Get the number of editors that edited the page.
     * @return int
     */
    public function getNumEditors()
    {
        return count($this->editors);
    }

    /**
     * Get the number of bots that edited the page.
     * @return int
     */
    public function getNumBots()
    {
        return count($this->getBots());
    }

    /**
     * Get the number of days between the first and last edit.
     * @return int
     */
    public function getTotalDays()
    {
        if (isset($this->totalDays)) {
            return $this->totalDays;
        }
        $dateFirst = $this->firstEdit->getTimestamp();
        $dateLast = $this->lastEdit->getTimestamp();
        $interval = date_diff($dateLast, $dateFirst, true);
        $this->totalDays = $interval->format('%a');
        return $this->totalDays;
    }

    /**
     * Returns length of the page.
     * @return int
     */
    public function getLength()
    {
        if ($this->hasDateRange()) {
            return $this->lastEdit->getLength();
        }

        return $this->page->getLength();
    }

    /**
     * Get the average number of days between edits to the page.
     * @return double
     */
    public function averageDaysPerEdit()
    {
        return round($this->getTotalDays() / $this->getNumRevisionsProcessed(), 1);
    }

    /**
     * Get the average number of edits per day to the page.
     * @return double
     */
    public function editsPerDay()
    {
        $editsPerDay = $this->getTotalDays()
            ? $this->getNumRevisionsProcessed() / ($this->getTotalDays() / (365 / 12 / 24))
            : 0;
        return round($editsPerDay, 1);
    }

    /**
     * Get the average number of edits per month to the page.
     * @return double
     */
    public function editsPerMonth()
    {
        $editsPerMonth = $this->getTotalDays()
            ? $this->getNumRevisionsProcessed() / ($this->getTotalDays() / (365 / 12))
            : 0;
        return min($this->getNumRevisionsProcessed(), round($editsPerMonth, 1));
    }

    /**
     * Get the average number of edits per year to the page.
     * @return double
     */
    public function editsPerYear()
    {
        $editsPerYear = $this->getTotalDays()
            ? $this->getNumRevisionsProcessed() / ($this->getTotalDays() / 365)
            : 0;
        return min($this->getNumRevisionsProcessed(), round($editsPerYear, 1));
    }

    /**
     * Get the average number of edits per editor.
     * @return double
     */
    public function editsPerEditor()
    {
        return round($this->getNumRevisionsProcessed() / count($this->editors), 1);
    }

    /**
     * Get the percentage of minor edits to the page.
     * @return double
     */
    public function minorPercentage()
    {
        return round(
            ($this->minorCount / $this->getNumRevisionsProcessed()) * 100,
            1
        );
    }

    /**
     * Get the percentage of anonymous edits to the page.
     * @return double
     */
    public function anonPercentage()
    {
        return round(
            ($this->anonCount / $this->getNumRevisionsProcessed()) * 100,
            1
        );
    }

    /**
     * Get the percentage of edits made by the top 10 editors.
     * @return double
     */
    public function topTenPercentage()
    {
        return round(($this->topTenCount / $this->getNumRevisionsProcessed()) * 100, 1);
    }

    /**
     * Get the number of times the page has been viewed in the given timeframe.
     * If the ArticleInfo instance has a date range, it is used instead of the
     * value of the $latest parameter.
     * @param  int $latest Last N days.
     * @return int
     */
    public function getPageviews($latest)
    {
        if (!$this->hasDateRange()) {
            return $this->page->getLastPageviews($latest);
        }

        $daterange = $this->getDateParams();
        return $this->page->getPageviews($daterange['start'], $daterange['end']);
    }

    /**
     * Get the page assessments of the page.
     * @see https://www.mediawiki.org/wiki/Extension:PageAssessments
     * @return string[]|false False if unsupported.
     * @codeCoverageIgnore
     */
    public function getAssessments()
    {
        if (!is_array($this->assessments)) {
            $this->assessments = $this->page->getAssessments();
        }
        return $this->assessments;
    }

    /**
     * Get the number of automated edits made to the page.
     * @return int
     */
    public function getAutomatedCount()
    {
        return $this->automatedCount;
    }

    /**
     * Get the number of edits to the page that were reverted with the subsequent edit.
     * @return int
     */
    public function getRevertCount()
    {
        return $this->revertCount;
    }

    /**
     * Get the number of edits to the page made by logged out users.
     * @return int
     */
    public function getAnonCount()
    {
        return $this->anonCount;
    }

    /**
     * Get the number of minor edits to the page.
     * @return int
     */
    public function getMinorCount()
    {
        return $this->minorCount;
    }

    /**
     * Get the number of edits to the page made in the past day, week, month and year.
     * @return int[] With keys 'day', 'week', 'month' and 'year'.
     */
    public function getCountHistory()
    {
        return $this->countHistory;
    }

    /**
     * Get the number of edits to the page made by the top 10 editors.
     * @return int
     */
    public function getTopTenCount()
    {
        return $this->topTenCount;
    }

    /**
     * Get the first edit to the page.
     * @return Edit
     */
    public function getFirstEdit()
    {
        return $this->firstEdit;
    }

    /**
     * Get the last edit to the page.
     * @return Edit
     */
    public function getLastEdit()
    {
        return $this->lastEdit;
    }

    /**
     * Get the edit that made the largest addition to the page (by number of bytes).
     * @return Edit
     */
    public function getMaxAddition()
    {
        return $this->maxAddition;
    }

    /**
     * Get the edit that made the largest removal to the page (by number of bytes).
     * @return Edit
     */
    public function getMaxDeletion()
    {
        return $this->maxDeletion;
    }

    /**
     * Get the list of editors to the page, including various statistics.
     * @return mixed[]
     */
    public function getEditors()
    {
        return $this->editors;
    }

    /**
     * Get the list of the top editors to the page (by edits), including various statistics.
     * @return mixed[]
     */
    public function topTenEditorsByEdits()
    {
        return $this->topTenEditorsByEdits;
    }

    /**
     * Get the list of the top editors to the page (by added text), including various statistics.
     * @return mixed[]
     */
    public function topTenEditorsByAdded()
    {
        return $this->topTenEditorsByAdded;
    }

    /**
     * Get various counts about each individual year and month of the page's history.
     * @return mixed[]
     */
    public function getYearMonthCounts()
    {
        return $this->yearMonthCounts;
    }

    /**
     * Get the maximum number of edits that were created across all months. This is used as a
     * comparison for the bar charts in the months section.
     * @return int
     */
    public function getMaxEditsPerMonth()
    {
        return $this->maxEditsPerMonth;
    }

    /**
     * Get a list of (semi-)automated tools that were used to edit the page, including
     * the number of times they were used, and a link to the tool's homepage.
     * @return mixed[]
     */
    public function getTools()
    {
        return $this->tools;
    }

    /**
     * Get the list of page's wikidata and Checkwiki errors.
     * @see Page::getErrors()
     * @return string[]
     */
    public function getBugs()
    {
        if (!is_array($this->bugs)) {
            $this->bugs = $this->page->getErrors();
        }
        return $this->bugs;
    }

    /**
     * Get the number of wikidata nad CheckWiki errors.
     * @return int
     */
    public function numBugs()
    {
        return count($this->getBugs());
    }

    /**
     * Get the number of external links on the page.
     * @return int
     */
    public function linksExtCount()
    {
        return $this->getLinksAndRedirects()['links_ext_count'];
    }

    /**
     * Get the number of incoming links to the page.
     * @return int
     */
    public function linksInCount()
    {
        return $this->getLinksAndRedirects()['links_in_count'];
    }

    /**
     * Get the number of outgoing links from the page.
     * @return int
     */
    public function linksOutCount()
    {
        return $this->getLinksAndRedirects()['links_out_count'];
    }

    /**
     * Get the number of redirects to the page.
     * @return int
     */
    public function redirectsCount()
    {
        return $this->getLinksAndRedirects()['redirects_count'];
    }

    /**
     * Get the number of external, incoming and outgoing links, along with
     * the number of redirects to the page.
     * @return int
     * @codeCoverageIgnore
     */
    private function getLinksAndRedirects()
    {
        if (!is_array($this->linksAndRedirects)) {
            $this->linksAndRedirects = $this->page->countLinksAndRedirects();
        }
        return $this->linksAndRedirects;
    }

    /**
     * Parse the revision history, collecting our core statistics.
     * @return mixed[] Associative "master" array of metadata about the page.
     *
     * Untestable because it relies on getting a PDO statement. All the important
     * logic lives in other methods which are tested.
     * @codeCoverageIgnore
     */
    private function parseHistory()
    {
        if ($this->tooManyRevisions()) {
            $limit = $this->getMaxRevisions();
        } else {
            $limit = null;
        }

        // Third parameter is ignored if $limit is null.
        $revStmt = $this->page->getRevisionsStmt(
            null,
            $limit,
            $this->getNumRevisions(),
            $this->startDate,
            $this->endDate
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
            // accruate revert detection.
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

            if ($revCount === 0) {
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
     * @param  Edit   $edit
     * @param  Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'
     * @return Edit[] Updated version of $prevEdits.
     */
    private function updateCounts(Edit $edit, $prevEdits)
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
        if ($prevEdits['prev'] !== null) {
            $prevEdits['prevSha'] = $prevEdits['prev']->getSha();
        }
        $prevEdits['prev'] = $edit;
        $this->lastEdit = $edit;

        return $prevEdits;
    }

    /**
     * Update various figures about content sizes based on the given edit.
     * @param  Edit   $edit
     * @param  Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
     * @return Edit[] Updated version of $prevEdits.
     */
    private function updateContentSizes(Edit $edit, $prevEdits)
    {
        // Check if it was a revert
        if ($this->isRevert($prevEdits, $edit)) {
            return $this->updateContentSizesRevert($prevEdits);
        } else {
            return $this->updateContentSizesNonRevert($edit, $prevEdits);
        }
    }

    /**
     * Is the given Edit a revert?
     * @param  Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
     * @param  Edit $edit
     * @return bool
     */
    private function isRevert($prevEdits, $edit)
    {
        return $edit->getSha() === $prevEdits['prevSha'] || $edit->isRevert($this->container);
    }

    /**
     * Updates the figures on content sizes assuming the given edit was a revert of the previous one.
     * In such a case, we don't want to treat the previous edit as legit content addition or removal.
     * @param  Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
     * @return Edit[] Updated version of $prevEdits, for tracking.
     */
    private function updateContentSizesRevert($prevEdits)
    {
        $this->revertCount++;

        // Adjust addedBytes given this edit was a revert of the previous one.
        if ($prevEdits['prev'] && $prevEdits['prev']->getSize() > 0) {
            $this->addedBytes -= $prevEdits['prev']->getSize();

            // Also deduct from the user's individual added byte count.
            $username = $prevEdits['prev']->getUser()->getUsername();
            $this->editors[$username]['added'] -= $prevEdits['prev']->getSize();
        }

        // @TODO: Test this against an edit war (use your sandbox).
        // Also remove as max added or deleted, if applicable.
        if ($this->maxAddition && $prevEdits['prev']->getId() === $this->maxAddition->getId()) {
            // $this->editors[$prevEdits->getUser()->getUsername()]['sizes'] = $edit->getLength() / 1024;
            $this->maxAddition = $prevEdits['maxAddition'];
            $prevEdits['maxAddition'] = $prevEdits['prev']; // In the event of edit wars.
        } elseif ($this->maxDeletion && $prevEdits['prev']->getId() === $this->maxDeletion->getId()) {
            $this->maxDeletion = $prevEdits['maxDeletion'];
            $prevEdits['maxDeletion'] = $prevEdits['prev']; // In the event of edit wars.
        }

        return $prevEdits;
    }

    /**
     * Updates the figures on content sizes assuming the given edit
     * was NOT a revert of the previous edit.
     * @param  Edit   $edit
     * @param  Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
     * @return Edit[] Updated version of $prevEdits, for tracking.
     */
    private function updateContentSizesNonRevert(Edit $edit, $prevEdits)
    {
        $editSize = $this->getEditSize($edit, $prevEdits);

        // Edit was not a revert, so treat size > 0 as content added.
        if ($editSize > 0) {
            $this->addedBytes += $editSize;
            $this->editors[$edit->getUser()->getUsername()]['added'] += $editSize;

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
     * @param  Edit   $edit
     * @param  Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
     * @return Edit[] Updated version of $prevEdits, for tracking.
     */
    private function getEditSize(Edit $edit, $prevEdits)
    {
        if ($prevEdits['prev'] && $prevEdits['prev']->getLength() === null) {
            return 0;
        } else {
            return $edit->getSize();
        }
    }

    /**
     * Update counts of automated tool usage for the given edit.
     * @param Edit $edit
     */
    private function updateToolCounts(Edit $edit)
    {
        $automatedTool = $edit->getTool($this->container);

        if ($automatedTool === false) {
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
    private function updateYearMonthCounts(Edit $edit)
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
        $this->yearMonthCounts[$editYear]['size'] = (int) $edit->getLength();

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
    private function addYearMonthCountEntry(Edit $edit)
    {
        $editYear = $edit->getYear();

        // Beginning of the month at 00:00:00.
        $firstEditTime = mktime(0, 0, 0, (int) $this->firstEdit->getMonth(), 1, $this->firstEdit->getYear());

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
            $timeObj = mktime(0, 0, 0, $i, 1, $editYear);

            // Don't show zeros for months before the first edit or after the current month.
            if ($timeObj < $firstEditTime || $timeObj > $this->getLastDay()) {
                continue;
            }

            $this->yearMonthCounts[$editYear]['months'][sprintf('%02d', $i)] = [
                'all' => 0,
                'minor' => 0,
                'anon' => 0,
                'automated' => 0,
            ];
        }
    }

    /**
     * Update the counts of anon and minor edits for year, month,
     * and user of the given edit.
     * @param Edit $edit
     */
    private function updateAnonMinorCounts(Edit $edit)
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
    private function updateUserCounts(Edit $edit)
    {
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
                'sizes' => [],
            ];
        }

        // Increment user counts
        $this->editors[$username]['all']++;
        $this->editors[$username]['last'] = $edit->getTimestamp();
        $this->editors[$username]['lastId'] = $edit->getId();

        // Store number of KB added with this edit
        $this->editors[$username]['sizes'][] = $edit->getLength() / 1024;

        // Increment minor counts for this user
        if ($edit->isMinor()) {
            $this->editors[$username]['minor']++;
        }
    }

    /**
     * Increment "edits per <time>" counts based on the given edit.
     * @param Edit $edit
     */
    private function updateCountHistory(Edit $edit)
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
     * Get info about bots that edited the page.
     * @return mixed[] Contains the bot's username, edit count to the page,
     *   and whether or not they are currently a bot.
     */
    public function getBots()
    {
        return $this->bots;
    }

    /**
     * Set info about bots that edited the page. This is done as a private setter
     * because we need this information when computing the top 10 editors,
     * where we don't want to include bots.
     */
    private function setBots()
    {
        // Parse the botedits
        $bots = [];
        $botData = $this->getRepository()->getBotData($this->page, $this->startDate, $this->endDate);
        while ($bot = $botData->fetch()) {
            $bots[$bot['username']] = [
                'count' => (int) $bot['count'],
                'current' => $bot['current'] === 'bot',
            ];
        }

        // Sort by edit count.
        uasort($bots, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        $this->bots = $bots;
    }

    /**
     * Number of edits made to the page by current or former bots.
     * @param string[] $bots Used only in unit tests, where we
     *   supply mock data for the bots that will get processed.
     * @return int
     */
    public function getBotRevisionCount($bots = null)
    {
        if (isset($this->botRevisionCount)) {
            return $this->botRevisionCount;
        }

        if ($bots === null) {
            $bots = $this->getBots();
        }

        $count = 0;

        foreach ($bots as $username => $data) {
            $count += $data['count'];
        }

        $this->botRevisionCount = $count;
        return $count;
    }

    /**
     * Query for log events during each year of the article's history,
     *   and set the results in $this->yearMonthCounts.
     */
    private function setLogsEvents()
    {
        $logData = $this->getRepository()->getLogEvents(
            $this->page,
            $this->startDate,
            $this->endDate
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
                case 'protect':
                    $action = 'protections';
                    break;
                case 'delete':
                    $action = 'deletions';
                    break;
                case 'move':
                    $action = 'moves';
                    break;
                // count pending-changes protections along with normal protections.
                case 'stable':
                    $action = 'protections';
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
     * @return integer Number of edits
     */
    private function setTopTenCounts()
    {
        $topTenCount = $counter = 0;
        $topTenEditors = [];

        foreach ($this->editors as $editor => $info) {
            // Count how many users are in the top 10% by number of edits, excluding bots.
            if ($counter < 10 && !in_array($editor, array_keys($this->bots))) {
                $topTenCount += $info['all'];
                $counter++;

                // To be used in the Top Ten charts.
                $topTenEditors[] = [
                    'label' => $editor,
                    'value' => $info['all'],
                    'percentage' => (
                        100 * ($info['all'] / $this->getNumRevisionsProcessed())
                    )
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
                $this->editors[$editor]['atbe'] = $days / $info['all'];
            }

            if (count($info['sizes'])) {
                // Average Total KB divided by number of stored sizes (usually the user's edit count to this page).
                $this->editors[$editor]['size'] = array_sum($info['sizes']) / count($info['sizes']);
            } else {
                $this->editors[$editor]['size'] = 0;
            }
        }

        $this->topTenEditorsByEdits = $topTenEditors;

        // First sort editors array by the amount of text they added.
        $topTenEditorsByAdded = $this->editors;
        uasort($topTenEditorsByAdded, function ($a, $b) {
            if ($a['added'] === $b['added']) {
                return 0;
            }
            return $a['added'] > $b['added'] ? -1 : 1;
        });

        // Then build a new array of top 10 editors by added text,
        // in the data structure needed for the chart.
        $this->topTenEditorsByAdded = array_map(function ($editor) {
            $added = $this->editors[$editor]['added'];
            return [
                'label' => $editor,
                'value' => $added,
                'percentage' => (
                    100 * ($added / $this->addedBytes)
                )
            ];
        }, array_keys(array_slice($topTenEditorsByAdded, 0, 10)));

        $this->topTenCount = $topTenCount;
    }

    /**
     * Get authorship attribution from the WikiWho API.
     * @see https://f-squared.org/wikiwho/
     * @param  int $limit Max number of results.
     * @return array
     */
    public function getTextshares($limit = null)
    {
        if (isset($this->textshares)) {
            return $this->textshares;
        }

        // TODO: check for failures. Should have a success:true
        $ret = $this->getRepository()->getTextshares($this->page);

        // If revision can't be found, return error message.
        if (!isset($ret['revisions'][0])) {
            return [
                'error' => isset($ret['Error']) ? $ret['Error'] : 'Unknown'
            ];
        }

        $revId = array_keys($ret['revisions'][0])[0];
        $tokens = $ret['revisions'][0][$revId]['tokens'];

        list($counts, $totalCount, $userIds) = $this->countTokens($tokens);
        $usernameMap = $this->getUsernameMap($userIds);

        if ($limit !== null) {
            $countsToProcess = array_slice($counts, 0, $limit, true);
        } else {
            $countsToProcess = $counts;
        }

        $textshares = [];

        // Loop through once more, creating an array with the user names (or IP address)
        // as the key, and the count and percentage as the value.
        foreach ($countsToProcess as $editor => $count) {
            if (isset($usernameMap[$editor])) {
                $index = $usernameMap[$editor];
            } else {
                $index = $editor;
            }
            $textshares[$index] = [
                'count' => $count,
                'percentage' => round(100 * ($count / $totalCount), 1)
            ];
        }

        $this->textshares = [
            'list' => $textshares,
            'totalAuthors' => count($counts),
            'totalCount' => $totalCount,
        ];

        return $this->textshares;
    }

    /**
     * Get a map of user IDs to usernames, given the IDs.
     * @param  int[] $userIds
     * @return array IDs as keys, usernames as values.
     */
    private function getUsernameMap($userIds)
    {
        $userIdsNames = $this->getRepository()->getUsernamesFromIds(
            $this->page->getProject(),
            $userIds
        );

        $usernameMap = [];
        foreach ($userIdsNames as $userIdName) {
            $usernameMap[$userIdName['user_id']] = $userIdName['user_name'];
        }

        return $usernameMap;
    }

    /**
     * Get counts of token lengths for each author. Used in self::getTextshares()
     * @param  array $tokens
     * @return array [counts by user, total count, IDs of accounts]
     */
    private function countTokens($tokens)
    {
        $counts = [];
        $userIds = [];
        $totalCount = 0;

        // Loop through the tokens, keeping totals (token length) for each author.
        foreach ($tokens as $token) {
            $editor = $token['editor'];

            // IPs are prefixed with '0|', otherwise it's the user ID.
            if (substr($editor, 0, 2) === '0|') {
                $editor = substr($editor, 2);
            } else {
                $userIds[] = $editor;
            }

            if (!isset($counts[$editor])) {
                $counts[$editor] = 0;
            }

            $counts[$editor] += strlen($token['str']);
            $totalCount += strlen($token['str']);
        }

        // Sort authors by count.
        arsort($counts);

        return [$counts, $totalCount, $userIds];
    }

    /**
     * Get a list of wikis supported by WikiWho.
     * @return string[]
     * @codeCoverageIgnore
     */
    public function getTextshareWikis()
    {
        return self::TEXTSHARE_WIKIS;
    }

    /**
     * Get prose and reference information.
     * @return array With keys 'characters', 'words', 'references', 'unique_references'
     */
    public function getProseStats()
    {
        $datetime = $this->endDate !== false ? new DateTime('@'.$this->endDate) : null;
        $html = $this->page->getHTMLContent($datetime);

        $crawler = new Crawler($html);

        list($chars, $words) = $this->countCharsAndWords($crawler, '#mw-content-text p');

        $refs = $crawler->filter('#mw-content-text .reference');
        $refContent = [];
        $refs->each(function ($ref) use (&$refContent) {
            $refContent[] = $ref->text();
        });
        $uniqueRefs = count(array_unique($refContent));

        $sections = count($crawler->filter('#mw-content-text .mw-headline'));

        return [
            'characters' => $chars,
            'words' => $words,
            'references' => $refs->count(),
            'unique_references' => $uniqueRefs,
            'sections' => $sections,
        ];
    }

    /**
     * Count the number of characters and words of the plain text
     * within the DOM element matched by the given selector.
     * @param  Crawler $crawler
     * @param  string $selector HTML selector.
     * @return array [num chars, num words]
     */
    private function countCharsAndWords($crawler, $selector)
    {
        $totalChars = 0;
        $totalWords = 0;
        $paragraphs = $crawler->filter($selector);
        $paragraphs->each(function ($node) use (&$totalChars, &$totalWords) {
            $text = preg_replace('/\[\d+\]/', '', trim($node->text()));
            $totalChars += strlen($text);
            $totalWords += count(explode(' ', $text));
        });

        return [$totalChars, $totalWords];
    }

    /**
     * Fetch transclusion data (categories, templates and files)
     * that are on the page.
     * @return array With keys 'categories', 'templates' and 'files'.
     */
    private function getTransclusionData()
    {
        if (!is_array($this->transclusionData)) {
            $this->transclusionData = $this->getRepository()
                ->getTransclusionData($this->page);
        }
        return $this->transclusionData;
    }

    /**
     * Get the number of categories that are on the page.
     * @return int
     */
    public function getNumCategories()
    {
        return $this->getTransclusionData()['categories'];
    }

    /**
     * Get the number of templates that are on the page.
     * @return int
     */
    public function getNumTemplates()
    {
        return $this->getTransclusionData()['templates'];
    }

    /**
     * Get the number of files that are on the page.
     * @return int
     */
    public function getNumFiles()
    {
        return $this->getTransclusionData()['files'];
    }
}
