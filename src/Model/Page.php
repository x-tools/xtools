<?php

declare(strict_types = 1);

namespace App\Model;

use App\Exception\BadGatewayException;
use App\Repository\PageRepository;
use DateTime;
use Doctrine\DBAL\Driver\ResultStatement;
use GuzzleHttp\Exception\ClientException;

/**
 * A Page is a single wiki page in one project.
 */
class Page extends Model
{
    /** @var string The page name as provided at instantiation. */
    protected string $unnormalizedPageName;

    /** @var string[]|null Metadata about this page. */
    protected ?array $pageInfo;

    /** @var string[] Revision history of this page. */
    protected array $revisions;

    /** @var int Number of revisions for this page. */
    protected int $numRevisions;

    /** @var string[] List of Wikidata sitelinks for this page. */
    protected array $wikidataItems;

    /** @var int Number of Wikidata sitelinks for this page. */
    protected int $numWikidataItems;

    /** @var int Length of the page in bytes. */
    protected int $length;

    /**
     * Page constructor.
     * @param PageRepository $repository
     * @param Project $project
     * @param string $pageName
     */
    public function __construct(PageRepository $repository, Project $project, string $pageName)
    {
        $this->repository = $repository;
        $this->project = $project;
        $this->unnormalizedPageName = $pageName;
    }

    /**
     * Get a Page instance given a database row (either from or JOINed on the page table).
     * @param PageRepository $repository
     * @param Project $project
     * @param array $row Must contain 'page_title' and 'namespace'. May contain 'length'.
     * @return static
     */
    public static function newFromRow(PageRepository $repository, Project $project, array $row): self
    {
        $pageTitle = $row['page_title'];

        if (0 === (int)$row['namespace']) {
            $fullPageTitle = $pageTitle;
        } else {
            $namespaces = $project->getNamespaces();
            $fullPageTitle = $namespaces[$row['namespace']].":$pageTitle";
        }

        $page = new self($repository, $project, $fullPageTitle);
        $page->pageInfo['ns'] = $row['namespace'];
        if (isset($row['length'])) {
            $page->length = (int)$row['length'];
        }

        return $page;
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
        if (!isset($this->pageInfo)) {
            $this->pageInfo = $this->repository->getPageInfo($this->project, $this->unnormalizedPageName);
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
        return $nsName
            ? str_replace($nsName . ':', '', $title)
            : $title;
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
        if (isset($this->length)) {
            return $this->length;
        }
        $info = $this->getPageInfo();
        $this->length = isset($info['length']) ? (int)$info['length'] : null;
        return $this->length;
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
        if (isset($this->pageInfo['ns']) && is_numeric($this->pageInfo['ns'])) {
            return (int)$this->pageInfo['ns'];
        }
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
     * @param DateTime|int|null $target If a DateTime object, the
     *   revision at that time will be returned. If an integer, it is
     *   assumed to be the actual revision ID. If null, use the last revision.
     * @return string
     */
    public function getHTMLContent($target = null): string
    {
        if (is_a($target, 'DateTime')) {
            $target = $this->repository->getRevisionIdAtDate($this, $target);
        }
        return $this->repository->getHTMLContent($this, $target);
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
        return $info['pagelanguage'] ?? $this->getProject()->getLang();
    }

    /**
     * Get the Wikidata ID of this page.
     * @return string|null Null if none exists.
     */
    public function getWikidataId(): ?string
    {
        $info = $this->getPageInfo();
        return $info['pageprops']['wikibase_item'] ?? null;
    }

    /**
     * Get the number of revisions the page has.
     * @param ?User $user Optionally limit to those of this user.
     * @param false|int $start
     * @param false|int $end
     * @return int
     */
    public function getNumRevisions(?User $user = null, $start = false, $end = false): int
    {
        // If a user is given, we will not cache the result via instance variable.
        if (null !== $user) {
            return $this->repository->getNumRevisions($this, $user, $start, $end);
        }

        // Return cached value, if present.
        if (isset($this->numRevisions)) {
            return $this->numRevisions;
        }

        // Otherwise, return the count of all revisions if already present.
        if (isset($this->revisions)) {
            $this->numRevisions = count($this->revisions);
        } else {
            // Otherwise do a COUNT in the event fetching all revisions is not desired.
            $this->numRevisions = $this->repository->getNumRevisions($this, null, $start, $end);
        }

        return $this->numRevisions;
    }

    /**
     * Get all edits made to this page.
     * @param User|null $user Specify to get only revisions by the given user.
     * @param false|int $start
     * @param false|int $end
     * @param int|null $limit
     * @param int|null $numRevisions
     * @return array
     */
    public function getRevisions(
        ?User $user = null,
        $start = false,
        $end = false,
        ?int $limit = null,
        ?int $numRevisions = null
    ): array {
        if (isset($this->revisions)) {
            return $this->revisions;
        }

        $this->revisions = $this->repository->getRevisions($this, $user, $start, $end, $limit);

        return $this->revisions;
    }

    /**
     * Get the full page wikitext.
     * @return string|null Null if nothing was found.
     */
    public function getWikitext(): ?string
    {
        $content = $this->repository->getPagesWikitext(
            $this->getProject(),
            [ $this->getTitle() ]
        );

        return $content[$this->getTitle()] ?? null;
    }

    /**
     * Get the statement for a single revision, so that you can iterate row by row.
     * @see PageRepository::getRevisionsStmt()
     * @param User|null $user Specify to get only revisions by the given user.
     * @param ?int $limit Max number of revisions to process.
     * @param false|int $start
     * @param false|int $end
     * @return ResultStatement
     * Just returns a Repo result.
     * @codeCoverageIgnore
     */
    public function getRevisionsStmt(
        ?User $user = null,
        ?int $limit = null,
        $start = false,
        $end = false
    ): ResultStatement {
        return $this->repository->getRevisionsStmt($this, $user, $limit, $start, $end);
    }

    /**
     * Get the revision ID that immediately precedes the given date.
     * @param DateTime $date
     * @return int|null Null if none found.
     * Just returns a Repo result.
     * @codeCoverageIgnore
     */
    public function getRevisionIdAtDate(DateTime $date): ?int
    {
        return $this->repository->getRevisionIdAtDate($this, $date);
    }

    /**
     * Get CheckWiki errors for this page
     * @return string[] See getErrors() for format
     */
    public function getCheckWikiErrors(): array
    {
        return $this->repository->getCheckWikiErrors($this);
    }

    /**
     * Get CheckWiki errors, if present
     * @return string[][] List of errors in the format:
     *    [[
     *         'prio' => int,
     *         'name' => string,
     *         'notice' => string (HTML),
     *         'explanation' => string (HTML)
     *     ], ... ]
     */
    public function getErrors(): array
    {
        return $this->getCheckWikiErrors();
    }

    /**
     * Get all wikidata items for the page, not just languages of sister projects
     * @return string[]
     */
    public function getWikidataItems(): array
    {
        if (!isset($this->wikidataItems)) {
            $this->wikidataItems = $this->repository->getWikidataItems($this);
        }
        return $this->wikidataItems;
    }

    /**
     * Count wikidata items for the page, not just languages of sister projects
     * @return int Number of records.
     */
    public function countWikidataItems(): int
    {
        if (isset($this->wikidataItems)) {
            $this->numWikidataItems = count($this->wikidataItems);
        } elseif (!isset($this->numWikidataItems)) {
            $this->numWikidataItems = $this->repository->countWikidataItems($this);
        }
        return $this->numWikidataItems;
    }

    /**
     * Get number of in and outgoing links and redirects to this page.
     * @return string[] Counts with keys 'links_ext_count', 'links_out_count', 'links_in_count' and 'redirects_count'.
     */
    public function countLinksAndRedirects(): array
    {
        return $this->repository->countLinksAndRedirects($this);
    }

    /**
     * Get the sum of pageviews for the given page and timeframe.
     * @param string|DateTime $start In the format YYYYMMDD
     * @param string|DateTime $end In the format YYYYMMDD
     * @return int|null Total pageviews or null if data is unavailable.
     */
    public function getPageviews($start, $end): ?int
    {
        try {
            $pageviews = $this->repository->getPageviews($this, $start, $end);
        } catch (ClientException $e) {
            // 404 means zero pageviews
            return 0;
        } catch (BadGatewayException $e) {
            // Upstream error, so return null so the view can customize messaging.
            return null;
        }

        return array_sum(array_map(function ($item) {
            return (int)$item['views'];
        }, $pageviews['items']));
    }

    /**
     * Get the sum of pageviews over the last N days
     * @param int $days Default PageInfoApi::PAGEVIEWS_OFFSET
     * @return int|null Number of pageviews or null if data is unavailable.
     *@see PageInfoApi::PAGEVIEWS_OFFSET
     */
    public function getLatestPageviews(int $days = PageInfoApi::PAGEVIEWS_OFFSET): ?int
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
