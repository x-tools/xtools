<?php

declare(strict_types = 1);

namespace App\Model;

use App\Repository\PagesRepository;
use DateTime;

/**
 * A Pages provides statistics about the pages created by a given User.
 */
class Pages extends Model
{
    private const RESULTS_LIMIT_SINGLE_NAMESPACE = 1000;
    private const RESULTS_LIMIT_ALL_NAMESPACES = 50;

    public const REDIR_NONE = 'noredirects';
    public const REDIR_ONLY = 'onlyredirects';
    public const REDIR_ALL = 'all';
    public const DEL_NONE = 'live';
    public const DEL_ONLY = 'deleted';
    public const DEL_ALL = 'all';

    /** @var string One of the self::REDIR_ constants of this class. */
    protected string $redirects;

    /** @var string One of the self::DEL_ constants of this class. */
    protected string $deleted;

    /** @var array The list of pages including various statistics, keyed by namespace. */
    protected array $pages;

    /** @var array Number of redirects/pages that were created/deleted, broken down by namespace. */
    protected array $countsByNamespace;

    /**
     * Pages constructor.
     * @param PagesRepository $repository
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'.
     * @param string $redirects One of the Pages::REDIR_ constants.
     * @param string $deleted One of the Pages::DEL_ constants.
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @param int|false $offset Unix timestamp. Used for pagination.
     */
    public function __construct(
        PagesRepository $repository,
        Project $project,
        User $user,
        $namespace = 0,
        string $redirects = self::REDIR_NONE,
        string $deleted = self::DEL_ALL,
        $start = false,
        $end = false,
        $offset = false
    ) {
        $this->repository = $repository;
        $this->project = $project;
        $this->user = $user;
        $this->namespace = 'all' === $namespace ? 'all' : (int)$namespace;
        $this->start = $start;
        $this->end = $end;
        $this->redirects = $redirects ?: self::REDIR_NONE;
        $this->deleted = $deleted ?: self::DEL_ALL;
        $this->offset = $offset;
    }

    /**
     * The redirects option associated with this Pages instance.
     * @return string
     */
    public function getRedirects(): string
    {
        return $this->redirects;
    }

    /**
     * The deleted pages option associated with this Page instance.
     * @return string
     */
    public function getDeleted(): string
    {
        return $this->deleted;
    }

    /**
     * Fetch and prepare the pages created by the user.
     * @param bool $all Whether to get *all* results. This should only be used for
     *     export options. HTTP and JSON should paginate.
     * @return array
     * @codeCoverageIgnore
     */
    public function prepareData(bool $all = false): array
    {
        $this->pages = [];

        foreach ($this->getNamespaces() as $ns) {
            $data = $this->fetchPagesCreated($ns, $all);
            $this->pages[$ns] = count($data) > 0
                ? $this->formatPages($data)[$ns]
                : [];
        }

        return $this->pages;
    }

    /**
     * The public function to get the list of all pages created by the user,
     * up to self::resultsPerPage(), across all namespaces.
     * @param bool $all Whether to get *all* results. This should only be used for
     *     export options. HTTP and JSON should paginate.
     * @return array
     */
    public function getResults(bool $all = false): array
    {
        if (!isset($this->pages)) {
            $this->prepareData($all);
        }
        return $this->pages;
    }

    /**
     * Return a ISO 8601 timestamp of the last result. This is used for pagination purposes.
     * @return string|null
     */
    public function getLastTimestamp(): ?string
    {
        if ($this->isMultiNamespace()) {
            // No pagination in multi-namespace view.
            return null;
        }

        $numResults = count($this->getResults()[$this->getNamespace()]);
        $timestamp = new DateTime($this->getResults()[$this->getNamespace()][$numResults - 1]['timestamp']);
        return $timestamp->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Get the total number of pages the user has created.
     * @return int
     */
    public function getNumPages(): int
    {
        $total = 0;
        foreach (array_values($this->getCounts()) as $values) {
            $total += $values['count'];
        }
        return $total;
    }

    /**
     * Get the total number of pages we're showing data for.
     * @return int
     */
    public function getNumResults(): int
    {
        $total = 0;
        foreach (array_values($this->getResults()) as $pages) {
            $total += count($pages);
        }
        return $total;
    }

    /**
     * Get the total number of pages that are currently deleted.
     * @return int
     */
    public function getNumDeleted(): int
    {
        $total = 0;
        foreach (array_values($this->getCounts()) as $values) {
            $total += $values['deleted'];
        }
        return $total;
    }

    /**
     * Get the total number of pages that are currently redirects.
     * @return int
     */
    public function getNumRedirects(): int
    {
        $total = 0;
        foreach (array_values($this->getCounts()) as $values) {
            $total += $values['redirects'];
        }
        return $total;
    }

    /**
     * Get the namespaces in which this user has created pages.
     * @return int[] The IDs.
     */
    public function getNamespaces(): array
    {
        return array_keys($this->getCounts());
    }

    /**
     * Number of namespaces being reported.
     * @return int
     */
    public function getNumNamespaces(): int
    {
        return count(array_keys($this->getCounts()));
    }

    /**
     * Are there more than one namespace in the results?
     * @return bool
     */
    public function isMultiNamespace(): bool
    {
        return $this->getNumNamespaces() > 1 || ('all' === $this->getNamespace() && 1 === $this->getNumNamespaces());
    }

    /**
     * Get the sum of all page sizes, across all specified namespaces.
     * @return int
     */
    public function getTotalPageSize(): int
    {
        return array_sum(array_column($this->getCounts(), 'total_length'));
    }

    /**
     * Get average size across all pages.
     * @return float
     */
    public function averagePageSize(): float
    {
        return $this->getTotalPageSize() / $this->getNumPages();
    }

    /**
     * Number of redirects/pages that were created/deleted, broken down by namespace.
     * @return array Namespace IDs as the keys, with values 'count', 'deleted' and 'redirects'.
     */
    public function getCounts(): array
    {
        if (isset($this->countsByNamespace)) {
            return $this->countsByNamespace;
        }

        $counts = [];

        foreach ($this->countPagesCreated() as $row) {
            $ns = (int)$row['namespace'];
            $count = (int)$row['count'];
            $totalLength = (int)$row['total_length'];
            $counts[$ns] = [
                'count' => $count,
                'total_length' => $totalLength,
                'avg_length' => round($count > 0 ? $totalLength / $count : 0, 1),
            ];
            if (self::DEL_NONE !== $this->deleted) {
                $counts[$ns]['deleted'] = (int)$row['deleted'];
            }
            if (self::REDIR_NONE !== $this->redirects) {
                $counts[$ns]['redirects'] = (int)$row['redirects'];
            }
            if ($this->project->isPrpPage($ns)) {
                foreach ([0, 1, 2, 3, 4] as $level) {
                    $counts[$ns]["prp_quality$level"] = (int)$row["prp_quality$level"];
                }
            }
        }

        $this->countsByNamespace = $counts;
        return $this->countsByNamespace;
    }

    /**
     * Get the number of pages the user created by assessment.
     * @return array Keys are the assessment class, values are the counts.
     */
    public function getAssessmentCounts(): array
    {
        if ($this->getNumPages() > $this->resultsPerPage()) {
            $counts = $this->repository->getAssessmentCounts(
                $this->project,
                $this->user,
                $this->namespace,
                $this->redirects
            );
        } else {
            $counts = [];
            foreach ($this->pages as $ns => $nsPages) {
                if ($this->project->hasPageAssessments($ns)) {
                    foreach ($nsPages as $page) {
                        $class = $page['assessment']['class'] ?: 'Unknown';
                        $counts[$class] ??= 0;
                        $counts[$class]++;
                    }
                }
            }
        }

        arsort($counts);

        return $counts;
    }

    /**
     * Get the number of pages the user created by WikiProject.
     * @return array Keys are the WikiProject name, values are the counts.
     */
    public function getWikiprojectCounts(): array
    {
        if ($this->getNumPages() > $this->resultsPerPage()) {
            $counts = $this->repository->getWikiprojectCounts(
                $this->project,
                $this->user,
                $this->namespace,
                $this->redirects,
                $this->start,
                $this->end,
            );
        } else {
            $counts_tmp = [];
            foreach ($this->pages as $nsPages) {
                foreach ($nsPages as $page) {
                    foreach ($page['assessment']['projects'] as $project) {
                        $counts_tmp[$project] ??= 0;
                        $counts_tmp[$project]++;
                    }
                }
            }
            arsort($counts_tmp);
            $counts_tmp = array_slice($counts_tmp, 0, 10);
            $counts = [];
            foreach ($counts_tmp as $project => $count) {
                $counts[] = [ "pap_project_title" => $project, "count" => $count ];
            }
        }

        return $counts;
    }

    /**
     * Number of results to show, depending on the namespace.
     * @param bool $all Whether to get *all* results. This should only be used for
     *     export options. HTTP and JSON should paginate.
     * @return int|false
     */
    public function resultsPerPage(bool $all = false)
    {
        if (true === $all) {
            return false;
        }
        if ('all' === $this->namespace) {
            return self::RESULTS_LIMIT_ALL_NAMESPACES;
        }
        return self::RESULTS_LIMIT_SINGLE_NAMESPACE;
    }

    /**
     * What columns to show in namespace totals table.
     * @return string[]
     */
    public function getSummaryColumns(): array
    {
        $order = ['namespace', 'pages', 'redirects', 'deleted', 'live', 'total-page-size', 'average-page-size'];

        $summaryColumns = ['namespace'];
        if (in_array($this->getDeleted(), [self::DEL_ALL, self::DEL_ONLY])) {
            $summaryColumns[] = 'deleted';
        }
        if (self::DEL_ALL === $this->getDeleted()) {
            $summaryColumns[] = 'live';
        }
        if (in_array($this->getRedirects(), [self::REDIR_ALL, self::REDIR_ONLY])) {
            $summaryColumns[] = 'redirects';
        }
        if (self::DEL_ONLY !== $this->getDeleted() && self::REDIR_ONLY !== $this->getRedirects()) {
            $summaryColumns[] = 'pages';
        }

        $summaryColumns[] = 'total-page-size';
        $summaryColumns[] = 'average-page-size';

        // Re-sort based on $order
        return array_values(array_filter($order, static function ($column) use ($summaryColumns) {
            return in_array($column, $summaryColumns);
        }));
    }

    /**
     * Get the deletion summary to be shown when hovering over the "Deleted" text in the UI.
     * @param int $namespace
     * @param string $pageTitle
     * @param string $offset
     * @return string|null null if no deletion summary is available.
     */
    public function getDeletionSummary(int $namespace, string $pageTitle, string $offset): ?string
    {
        $ret = $this->repository->getDeletionSummary($this->project, $namespace, $pageTitle, $offset);
        if (!$ret) {
            return null;
        }
        $timestampStr = (new DateTime($ret['log_timestamp']))->format('Y-m-d H:i');
        $summary = Edit::wikifyString($ret['comment_text'], $this->project, $this->page, true);
        $userpageUrl = $this->project->getUrlForPage("User:{$ret['actor_name']}");
        return "$timestampStr (<a target='_blank' href=\"$userpageUrl\">{$ret['actor_name']}</a>): <i>$summary</i>";
    }

    /**
     * Run the query to get pages created by the user with options.
     * This is ran independently for each namespace if $this->namespace is 'all'.
     * @param int $namespace Namespace ID.
     * @param bool $all Whether to get *all* results. This should only be used for
     *     export options. HTTP and JSON should paginate.
     * @return array
     */
    private function fetchPagesCreated(int $namespace, bool $all = false): array
    {
        return $this->repository->getPagesCreated(
            $this->project,
            $this->user,
            $namespace,
            $this->redirects,
            $this->deleted,
            $this->start,
            $this->end,
            $this->resultsPerPage($all),
            $this->offset
        );
    }

    /**
     * Run the query to get the number of pages created by the user with given options.
     * @return array
     */
    private function countPagesCreated(): array
    {
        return $this->repository->countPagesCreated(
            $this->project,
            $this->user,
            $this->namespace,
            $this->redirects,
            $this->deleted,
            $this->start,
            $this->end
        );
    }

    /**
     * Format the data, adding page titles, assessment badges,
     * and sorting by namespace and then timestamp.
     * @param array $pages As returned by self::fetchPagesCreated()
     * @return array
     */
    private function formatPages(array $pages): array
    {
        $results = [];

        foreach ($pages as $row) {
            $fullPageTitle = $row['namespace'] > 0
                ? $this->project->getNamespaces()[$row['namespace']].':'.$row['page_title']
                : $row['page_title'];
            $pageData = [
                'deleted' => 'arc' === $row['type'],
                'namespace' => $row['namespace'],
                'page_title' => $row['page_title'],
                'full_page_title' => $fullPageTitle,
                'redirect' => (bool)$row['redirect'] || (bool)$row['was_redirect'],
                'timestamp' => $row['timestamp'],
                'rev_id' => $row['rev_id'],
                'rev_length' => $row['rev_length'],
                'length' => $row['length'],
            ];

            if ($row['recreated']) {
                $pageData['recreated'] = (bool)$row['recreated'];
            } else {
                // This is always NULL for live pages, in which case 'recreated' doesn't apply.
                unset($pageData['recreated']);
            }

            if ($this->project->hasPageAssessments($pageData['namespace'])) {
                $attrs = $this->project
                    ->getPageAssessments()
                    ->getClassAttrs($row['pa_class'] ?: 'Unknown');
                $pageData['assessment'] = [
                    'class' => $row['pa_class'] ?: 'Unknown',
                    'badge' => $this->project
                        ->getPageAssessments()
                        ->getBadgeURL($row['pa_class'] ?: 'Unknown'),
                    'color' => $attrs['color'],
                    'category' => $attrs['category'],
                    'projects' => json_decode($row['pap_project_title'] ?? '[]'),
                ];
            }
            
            if (array_key_exists('prp_quality', $row)) {
                $pageData['prp_quality'] = (int)$row['prp_quality'];
            }

            $results[$row['namespace']][] = $pageData;
        }

        return $results;
    }
}
