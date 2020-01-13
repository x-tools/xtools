<?php
/**
 * This file contains only the Page class.
 */

declare(strict_types = 1);

namespace AppBundle\Model;

use DateTime;

/**
 * A Page is a single wiki page in one project.
 */
class Page extends Model
{
    /** @var string The page name as provided at instantiation. */
    protected $unnormalizedPageName;

    /** @var string[] Metadata about this page. */
    protected $pageInfo;

    /** @var string[] Revision history of this page. */
    protected $revisions;

    /** @var int Number of revisions for this page. */
    protected $numRevisions;

    /** @var string[] List of Wikidata sitelinks for this page. */
    protected $wikidataItems;

    /** @var int Number of Wikidata sitelinks for this page. */
    protected $numWikidataItems;

    /**
     * Page constructor.
     * @param Project $project
     * @param string $pageName
     */
    public function __construct(Project $project, string $pageName)
    {
        $this->project = $project;
        $this->unnormalizedPageName = $pageName;
    }

    /**
     * Get a Page instance given a revision row (JOINed on the page table).
     * @param Project $project
     * @param array $rev Must contain 'page_title' and 'page_namespace'.
     * @return static
     */
    public static function newFromRev(Project $project, array $rev): self
    {
        $namespaces = $project->getNamespaces();
        $pageTitle = $rev['page_title'];

        if (0 === (int)$rev['page_namespace']) {
            $fullPageTitle = $pageTitle;
        } else {
            $fullPageTitle = $namespaces[$rev['page_namespace']].":$pageTitle";
        }

        return new self($project, $fullPageTitle);
    }

    /**
     * Unique identifier for this Page, to be used in cache keys.
     * Use of md5 ensures the cache key does not contain reserved characters.
     * @see Repository::getCacheKey()
     * @return string
     * @codeCoverageIgnore
     */
    public function getCacheKey(): string
    {
        return md5((string)$this->getId());
    }

    /**
     * Get basic information about this page from the repository.
     * @return array|null
     */
    protected function getPageInfo(): ?array
    {
        if (empty($this->pageInfo)) {
            $this->pageInfo = $this->getRepository()
                ->getPageInfo($this->project, $this->unnormalizedPageName);
        }
        return $this->pageInfo;
    }

    /**
     * Get the page's title.
     * @param bool $useUnnormalized Use the unnormalized page title to avoid an API call. This should be used only if
     *   you fetched the page title via other means (SQL query), and is not from user input alone.
     * @return string
     */
    public function getTitle(bool $useUnnormalized = false): string
    {
        if ($useUnnormalized) {
            return $this->unnormalizedPageName;
        }
        $info = $this->getPageInfo();
        return $info['title'] ?? $this->unnormalizedPageName;
    }

    /**
     * Get the page's title without the namespace.
     * @return string
     */
    public function getTitleWithoutNamespace(): string
    {
        $info = $this->getPageInfo();
        $title = $info['title'] ?? $this->unnormalizedPageName;
        $nsName = $this->getNamespaceName();
        return str_replace($nsName . ':', '', $title);
    }

    /**
     * Get this page's database ID.
     * @return int|null Null if nonexistent.
     */
    public function getId(): ?int
    {
        $info = $this->getPageInfo();
        return isset($info['pageid']) ? (int)$info['pageid'] : null;
    }

    /**
     * Get this page's length in bytes.
     * @return int|null Null if nonexistent.
     */
    public function getLength(): ?int
    {
        $info = $this->getPageInfo();
        return isset($info['length']) ? (int)$info['length'] : null;
    }

    /**
     * Get HTML for the stylized display of the title.
     * The text will be the same as Page::getTitle().
     * @return string
     */
    public function getDisplayTitle(): string
    {
        $info = $this->getPageInfo();
        if (isset($info['displaytitle'])) {
            return $info['displaytitle'];
        }
        return $this->getTitle();
    }

    /**
     * Get the full URL of this page.
     * @return string|null Null if nonexistent.
     */
    public function getUrl(): ?string
    {
        $info = $this->getPageInfo();
        return $info['fullurl'] ?? null;
    }

    /**
     * Get the numerical ID of the namespace of this page.
     * @return int|null Null if page doesn't exist.
     */
    public function getNamespace(): ?int
    {
        $info = $this->getPageInfo();
        return isset($info['ns']) ? (int)$info['ns'] : null;
    }

    /**
     * Get the name of the namespace of this page.
     * @return string|null Null if could not be determined.
     */
    public function getNamespaceName(): ?string
    {
        $info = $this->getPageInfo();
        return isset($info['ns'])
            ? ($this->getProject()->getNamespaces()[$info['ns']] ?? null)
            : null;
    }

    /**
     * Get the number of page watchers.
     * @return int|null Null if unknown.
     */
    public function getWatchers(): ?int
    {
        $info = $this->getPageInfo();
        return isset($info['watchers']) ? (int)$info['watchers'] : null;
    }

    /**
     * Get the HTML content of the body of the page.
     * @param DateTime|int $target If a DateTime object, the
     *   revision at that time will be returned. If an integer, it is
     *   assumed to be the actual revision ID.
     * @return string
     */
    public function getHTMLContent($target = null): string
    {
        if (is_a($target, 'DateTime')) {
            $target = $this->getRepository()->getRevisionIdAtDate($this, $target);
        }
        return $this->getRepository()->getHTMLContent($this, $target);
    }

    /**
     * Whether or not this page exists.
     * @return bool
     */
    public function exists(): bool
    {
        $info = $this->getPageInfo();
        return null !== $info && !isset($info['missing']) && !isset($info['invalid']) && !isset($info['interwiki']);
    }

    /**
     * Get the Project to which this page belongs.
     * @return Project
     */
    public function getProject(): Project
    {
        return $this->project;
    }

    /**
     * Get the language code for this page.
     * If not set, the language code for the project is returned.
     * @return string
     */
    public function getLang(): string
    {
        $info = $this->getPageInfo();
        if (isset($info['pagelanguage'])) {
            return $info['pagelanguage'];
        } else {
            return $this->getProject()->getLang();
        }
    }

    /**
     * Get the Wikidata ID of this page.
     * @return string|null Null if none exists.
     */
    public function getWikidataId(): ?string
    {
        $info = $this->getPageInfo();
        if (isset($info['pageprops']['wikibase_item'])) {
            return $info['pageprops']['wikibase_item'];
        } else {
            return null;
        }
    }

    /**
     * Get the number of revisions the page has.
     * @param User $user Optionally limit to those of this user.
     * @param false|int $start
     * @param false|int $end
     * @return int
     */
    public function getNumRevisions(?User $user = null, $start = false, $end = false): int
    {
        // If a user is given, we will not cache the result via instance variable.
        if (null !== $user) {
            return (int)$this->getRepository()->getNumRevisions($this, $user, $start, $end);
        }

        // Return cached value, if present.
        if (null !== $this->numRevisions) {
            return $this->numRevisions;
        }

        // Otherwise, return the count of all revisions if already present.
        if (null !== $this->revisions) {
            $this->numRevisions = count($this->revisions);
        } else {
            // Otherwise do a COUNT in the event fetching all revisions is not desired.
            $this->numRevisions = (int)$this->getRepository()->getNumRevisions($this, null, $start, $end);
        }

        return $this->numRevisions;
    }

    /**
     * Get all edits made to this page.
     * @param User|null $user Specify to get only revisions by the given user.
     * @param false|int $start
     * @param false|int $end
     * @return array
     */
    public function getRevisions(?User $user = null, $start = false, $end = false): array
    {
        if ($this->revisions) {
            return $this->revisions;
        }

        $this->revisions = $this->getRepository()->getRevisions($this, $user, $start, $end);

        return $this->revisions;
    }

    /**
     * Get the full page wikitext.
     * @return string|null Null if nothing was found.
     */
    public function getWikitext(): ?string
    {
        $content = $this->getRepository()->getPagesWikitext(
            $this->getProject(),
            [ $this->getTitle() ]
        );

        return $content[$this->getTitle()] ?? null;
    }

    /**
     * Get the statement for a single revision, so that you can iterate row by row.
     * @see PageRepository::getRevisionsStmt()
     * @param User|null $user Specify to get only revisions by the given user.
     * @param int $limit Max number of revisions to process.
     * @param int $numRevisions Number of revisions, if known. This is used solely to determine the
     *   OFFSET if we are given a $limit. If $limit is set and $numRevisions is not set, a
     *   separate query is ran to get the nuber of revisions.
     * @param false|int $start
     * @param false|int $end
     * @return \Doctrine\DBAL\Driver\PDOStatement
     */
    public function getRevisionsStmt(
        ?User $user = null,
        ?int $limit = null,
        ?int $numRevisions = null,
        $start = false,
        $end = false
    ): \Doctrine\DBAL\Driver\PDOStatement {
        // If we have a limit, we need to know the total number of revisions so that PageRepo
        // will properly set the OFFSET. See PageRepository::getRevisionsStmt() for more info.
        if (isset($limit) && null === $numRevisions) {
            $numRevisions = $this->getNumRevisions($user, $start, $end);
        }
        return $this->getRepository()->getRevisionsStmt($this, $user, $limit, $numRevisions, $start, $end);
    }

    /**
     * Get the revision ID that immediately precedes the given date.
     * @param DateTime $date
     * @return int|null Null if none found.
     */
    public function getRevisionIdAtDate(DateTime $date): ?int
    {
        return $this->getRepository()->getRevisionIdAtDate($this, $date);
    }

    /**
     * Get CheckWiki errors for this page
     * @return string[] See getErrors() for format
     */
    public function getCheckWikiErrors(): array
    {
        return $this->getRepository()->getCheckWikiErrors($this);
    }

    /**
     * Get Wikidata errors for this page
     * @return string[] See getErrors() for format
     */
    public function getWikidataErrors(): array
    {
        $errors = [];

        if (empty($this->getWikidataId())) {
            return [];
        }

        $wikidataInfo = $this->getRepository()->getWikidataInfo($this);

        $terms = array_map(function ($entry) {
            return $entry['term'];
        }, $wikidataInfo);

        $lang = $this->getLang();

        if (!in_array('label', $terms)) {
            $errors[] = [
                'prio' => 2,
                'name' => 'Wikidata',
                'notice' => "Label for language <em>$lang</em> is missing", // FIXME: i18n
                'explanation' => "See: <a target='_blank' " .
                    "href='//www.wikidata.org/wiki/Help:Label'>Help:Label</a>",
            ];
        }

        if (!in_array('description', $terms)) {
            $errors[] = [
                'prio' => 3,
                'name' => 'Wikidata',
                'notice' => "Description for language <em>$lang</em> is missing", // FIXME: i18n
                'explanation' => "See: <a target='_blank' " .
                    "href='//www.wikidata.org/wiki/Help:Description'>Help:Description</a>",
            ];
        }

        return $errors;
    }

    /**
     * Get Wikidata and CheckWiki errors, if present
     * @return string[] List of errors in the format:
     *    [[
     *         'prio' => int,
     *         'name' => string,
     *         'notice' => string (HTML),
     *         'explanation' => string (HTML)
     *     ], ... ]
     */
    public function getErrors(): array
    {
        // Includes label and description
        $wikidataErrors = $this->getWikidataErrors();

        $checkWikiErrors = $this->getCheckWikiErrors();

        return array_merge($wikidataErrors, $checkWikiErrors);
    }

    /**
     * Get all wikidata items for the page, not just languages of sister projects
     * @return string[]
     */
    public function getWikidataItems(): array
    {
        if (!is_array($this->wikidataItems)) {
            $this->wikidataItems = $this->getRepository()->getWikidataItems($this);
        }
        return $this->wikidataItems;
    }

    /**
     * Count wikidata items for the page, not just languages of sister projects
     * @return int Number of records.
     */
    public function countWikidataItems(): int
    {
        if (is_array($this->wikidataItems)) {
            $this->numWikidataItems = count($this->wikidataItems);
        } elseif (null === $this->numWikidataItems) {
            $this->numWikidataItems = (int)$this->getRepository()->countWikidataItems($this);
        }
        return $this->numWikidataItems;
    }

    /**
     * Get number of in and outgoing links and redirects to this page.
     * @return string[] Counts with keys 'links_ext_count', 'links_out_count', 'links_in_count' and 'redirects_count'.
     */
    public function countLinksAndRedirects(): array
    {
        return $this->getRepository()->countLinksAndRedirects($this);
    }

    /**
     * Get the sum of pageviews for the given page and timeframe.
     * @param string|DateTime $start In the format YYYYMMDD
     * @param string|DateTime $end In the format YYYYMMDD
     * @return int
     */
    public function getPageviews($start, $end): int
    {
        try {
            $pageviews = $this->getRepository()->getPageviews($this, $start, $end);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // 404 means zero pageviews
            return 0;
        }

        return array_sum(array_map(function ($item) {
            return (int)$item['views'];
        }, $pageviews['items']));
    }

    /**
     * Get the sum of pageviews over the last N days
     * @param int $days Default 30
     * @return int Number of pageviews
     */
    public function getLastPageviews(int $days = 30): int
    {
        $start = date('Ymd', strtotime("-$days days"));
        $end = date('Ymd');
        return $this->getPageviews($start, $end);
    }

    /**
     * Is the page the project's Main Page?
     * @return bool
     */
    public function isMainPage(): bool
    {
        return $this->getProject()->getMainPage() === $this->getTitle();
    }
}
