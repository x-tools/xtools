<?php

declare(strict_types = 1);

namespace App\Twig;

use Twig\TwigFunction;

/**
 * Twig functions for top navigation.
 */
class TopNavExtension extends AppExtension
{
    /** @var string[] Entries for Edit Counter dropdown. */
    protected array $topNavEditCounter;

    /** @var string[] Entries for User dropdown. */
    protected array $topNavUser;

    /** @var string[] Entries for Page dropdown. */
    protected array $topNavPage;

    /** @var string[] Entries for Project dropdown. */
    protected array $topNavProject;

    /**
     * Twig functions this class provides.
     * @return TwigFunction[]
     * @codeCoverageIgnore
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('top_nav_ec', [$this, 'topNavEditCounter']),
            new TwigFunction('top_nav_user', [$this, 'topNavUser']),
            new TwigFunction('top_nav_page', [$this, 'topNavPage']),
            new TwigFunction('top_nav_project', [$this, 'topNavProject']),
        ];
    }

    /**
     * Sorted list of links for the Edit Counter dropdown.
     * @return string[] Keys are tool IDs, values are the localized labels.
     */
    public function topNavEditCounter(): array
    {
        if (isset($this->topNavEditCounter)) {
            return $this->topNavEditCounter;
        }

        $toolsMessages = [
            'EditCounterGeneralStatsIndex' => 'general-stats',
            'EditCounterMonthCountsIndex' => 'month-counts',
            'EditCounterNamespaceTotalsIndex' => 'namespace-totals',
            'EditCounterRightsChangesIndex' => 'rights-changes',
            'EditCounterTimecardIndex' => 'timecard',
            'TopEdits' => 'top-edited-pages',
            'EditCounterYearCountsIndex' => 'year-counts',
        ];

        $this->topNavEditCounter = $this->sortEntries($toolsMessages, 'EditCounter');
        return $this->topNavEditCounter;
    }

    /**
     * Sorted list of links for the User dropdown.
     * @return string[] Keys are tool IDs, values are the localized labels.
     */
    public function topNavUser(): array
    {
        if (isset($this->topNavUser)) {
            return $this->topNavUser;
        }

        $toolsMessages = [
            'AdminScore' => 'tool-adminscore',
            'AutoEdits' => 'tool-autoedits',
            'CategoryEdits' => 'tool-categoryedits',
            'EditCounter' => 'tool-editcounter',
            'EditSummary' => 'tool-editsummary',
            'GlobalContribs' => 'tool-globalcontribs',
            'Pages' => 'tool-pages',
            'EditCounterRightsChangesIndex' => 'rights-changes',
            'SimpleEditCounter' => 'tool-simpleeditcounter',
            'TopEdits' => 'tool-topedits',
        ];

        $this->topNavUser = $this->sortEntries($toolsMessages);
        return $this->topNavUser;
    }

    /**
     * Sorted list of links for the Page dropdown.
     * @return string[] Keys are tool IDs, values are the localized labels.
     */
    public function topNavPage(): array
    {
        if (isset($this->topNavPage)) {
            return $this->topNavPage;
        }

        $toolsMessages = [
            'Authorship' => 'tool-authorship',
            'PageInfo' => 'tool-pageinfo',
            'Blame' => 'tool-blame',
        ];

        $this->topNavPage = $this->sortEntries($toolsMessages);
        return $this->topNavPage;
    }

    /**
     * Sorted list of links for the Project dropdown.
     * @return string[] Keys are tool IDs, values are the localized labels.
     */
    public function topNavProject(): array
    {
        if (isset($this->topNavProject)) {
            return $this->topNavProject;
        }

        $toolsMessages = [
            'AdminStats' => 'tool-adminstats',
            'PatrollerStats' => 'tool-patrollerstats',
            'StewardStats' => 'tool-stewardstats',
        ];

        $this->topNavProject = $this->sortEntries($toolsMessages, 'AdminStats');

        // This one should go last.
        if ($this->toolEnabled('LargestPages')) {
            $this->topNavProject['LargestPages'] = $this->i18n->msg('tool-largestpages');
        }

        return $this->topNavProject;
    }

    /**
     * Sort the given entries, localizing the labels.
     * @param array $entries
     * @param string|null $toolCheck Only make sure this tool is enabled (not individual tools passed in).
     * @return array
     */
    private function sortEntries(array $entries, ?string $toolCheck = null): array
    {
        $toolMessages = [];

        foreach ($entries as $tool => $key) {
            if ($this->toolEnabled($toolCheck ?? $tool)) {
                $toolMessages[$tool] = $this->i18n->msg($key);
            }
        }

        asort($toolMessages);
        return $toolMessages;
    }
}
