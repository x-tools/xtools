<?php

declare(strict_types = 1);

namespace App\Model;

use App\Repository\EditRepository;
use App\Repository\PageRepository;
use App\Repository\UserRepository;
use DateTime;
use TypeError;

/**
 * An Edit is a single edit to a page on one project.
 */
class Edit extends Model
{
    public const DELETED_TEXT = 1;
    public const DELETED_COMMENT = 2;
    public const DELETED_USER = 4;
    public const DELETED_RESTRICTED = 8;

    protected UserRepository $userRepo;

    /** @var int ID of the revision */
    protected int $id;

    /** @var DateTime Timestamp of the revision */
    protected DateTime $timestamp;

    /** @var bool Whether or not this edit was a minor edit */
    protected bool $minor;

    /** @var int|null Length of the page as of this edit, in bytes */
    protected ?int $length;

    /** @var int|null The diff size of this edit */
    protected ?int $lengthChange;

    /** @var string The edit summary */
    protected string $comment;

    /** @var string|null The SHA-1 of the wikitext as of the revision. */
    protected ?string $sha = null;

    /** @var bool|null Whether this edit was later reverted. */
    protected ?bool $reverted;

    /** @var int Deletion status of the revision. */
    protected int $deleted;

    /** @var string[] List of tags of the revision. */
    protected array $tags;

    /**
     * Edit constructor.
     * @param EditRepository $repository
     * @param UserRepository $userRepo
     * @param Page $page
     * @param string[] $attrs Attributes, as retrieved by PageRepository::getRevisions()
     */
    public function __construct(EditRepository $repository, UserRepository $userRepo, Page $page, array $attrs = [])
    {
        $this->repository = $repository;
        $this->userRepo = $userRepo;
        $this->page = $page;

        // Copy over supported attributes
        $this->id = isset($attrs['id']) ? (int)$attrs['id'] : (int)$attrs['rev_id'];

        // Allow DateTime or string (latter assumed to be of format YmdHis)
        if ($attrs['timestamp'] instanceof DateTime) {
            $this->timestamp = $attrs['timestamp'];
        } else {
            try {
                $this->timestamp = DateTime::createFromFormat('YmdHis', $attrs['timestamp']);
            } catch (TypeError $e) {
                // Some very old revisions may be missing a timestamp.
                $this->timestamp = new DateTime('1970-01-01T00:00:00Z');
            }
        }

        $this->deleted = (int)($attrs['rev_deleted'] ?? 0);

        if (($this->deleted & self::DELETED_USER) || ($this->deleted & self::DELETED_RESTRICTED)) {
            $this->user = null;
        } else {
            $this->user = $attrs['user'] ?? ($attrs['username'] ? new User($this->userRepo, $attrs['username']) : null);
        }

        $this->minor = 1 === (int)$attrs['minor'];
        $this->length = isset($attrs['length']) ? (int)$attrs['length'] : null;
        $this->lengthChange = isset($attrs['length_change']) ? (int)$attrs['length_change'] : null;
        $this->comment = $attrs['comment'] ?? '';

        // Had to be JSON to put multiple values in 1 column.
        $this->tags = json_decode($attrs['tags'] ?? '[]');

        if (isset($attrs['rev_sha1']) || isset($attrs['sha'])) {
            $this->sha = $attrs['rev_sha1'] ?? $attrs['sha'];
        }

        // This can be passed in to save as a property on the Edit instance.
        // Note that the Edit class knows nothing about it's value, and
        // is not capable of detecting whether the given edit was actually reverted.
        $this->reverted = isset($attrs['reverted']) ? (bool)$attrs['reverted'] : null;
    }

    /**
     * Get Edits given revision rows (JOINed on the page table).
     * @param PageRepository $pageRepo
     * @param EditRepository $editRepo
     * @param UserRepository $userRepo
     * @param Project $project
     * @param User $user
     * @param array $revs Each must contain 'page_title' and 'namespace'.
     * @return Edit[]
     */
    public static function getEditsFromRevs(
        PageRepository $pageRepo,
        EditRepository $editRepo,
        UserRepository $userRepo,
        Project $project,
        User $user,
        array $revs
    ): array {
        return array_map(function ($rev) use ($pageRepo, $editRepo, $userRepo, $project, $user) {
            /** Page object to be passed to the Edit constructor. */
            $page = Page::newFromRow($pageRepo, $project, $rev);
            $rev['user'] = $user;

            return new self($editRepo, $userRepo, $page, $rev);
        }, $revs);
    }

    /**
     * Unique identifier for this Edit, to be used in cache keys.
     * @see Repository::getCacheKey()
     * @return string
     */
    public function getCacheKey(): string
    {
        return (string)$this->id;
    }

    /**
     * ID of the edit.
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the edit's timestamp.
     * @return DateTime
     */
    public function getTimestamp(): DateTime
    {
        return $this->timestamp;
    }

    /**
     * Get the edit's timestamp as a UTC string, as with YYYY-MM-DDTHH:MM:SSZ
     * @return string
     */
    public function getUTCTimestamp(): string
    {
        return $this->getTimestamp()->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Year the revision was made.
     * @return string
     */
    public function getYear(): string
    {
        return $this->timestamp->format('Y');
    }

    /**
     * Get the numeric representation of the month the revision was made, with leading zeros.
     * @return string
     */
    public function getMonth(): string
    {
        return $this->timestamp->format('m');
    }

    /**
     * Whether or not this edit was a minor edit.
     * @return bool
     */
    public function getMinor(): bool
    {
        return $this->minor;
    }

    /**
     * Alias of getMinor()
     * @return bool Whether or not this edit was a minor edit
     */
    public function isMinor(): bool
    {
        return $this->getMinor();
    }

    /**
     * Length of the page as of this edit, in bytes.
     * @see Edit::getSize() Edit::getSize() for the size <em>change</em>.
     * @return int|null
     */
    public function getLength(): ?int
    {
        return $this->length;
    }

    /**
     * The diff size of this edit.
     * @return int|null Signed length change in bytes.
     */
    public function getSize(): ?int
    {
        return $this->lengthChange;
    }

    /**
     * Alias of getSize()
     * @return int|null The diff size of this edit
     */
    public function getLengthChange(): ?int
    {
        return $this->getSize();
    }

    /**
     * Get the user who made the edit.
     * @return User|null null can happen for instance if the username was suppressed.
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Get the edit summary.
     * @return string
     */
    public function getComment(): string
    {
        return (string)$this->comment;
    }

    /**
     * Get the edit summary (alias of Edit::getComment()).
     * @return string
     */
    public function getSummary(): string
    {
        return $this->getComment();
    }

    /**
     * Get the SHA-1 of the revision.
     * @return string|null
     */
    public function getSha(): ?string
    {
        return $this->sha;
    }

    /**
     * Was this edit reported as having been reverted?
     * The value for this is merely passed in from precomputed data.
     * @return bool|null
     */
    public function isReverted(): ?bool
    {
        return $this->reverted;
    }

    /**
     * Set the reverted property.
     * @param bool $reverted
     */
    public function setReverted(bool $reverted): void
    {
        $this->reverted = $reverted;
    }

    /**
     * Get deletion status of the revision.
     * @return int
     */
    public function getDeleted(): int
    {
        return $this->deleted;
    }

    /**
     * Was the username deleted from public view?
     * @return bool
     */
    public function deletedUser(): bool
    {
        return ($this->deleted & self::DELETED_USER) > 0;
    }

    /**
     * Was the edit summary deleted from public view?
     * @return bool
     */
    public function deletedSummary(): bool
    {
        return ($this->deleted & self::DELETED_COMMENT) > 0;
    }

    /**
     * Get edit summary as 'wikified' HTML markup
     * @param bool $useUnnormalizedPageTitle Use the unnormalized page title to avoid
     *   an API call. This should be used only if you fetched the page title via other
     *   means (SQL query), and is not from user input alone.
     * @return string Safe HTML
     */
    public function getWikifiedComment(bool $useUnnormalizedPageTitle = false): string
    {
        return self::wikifyString(
            $this->getSummary(),
            $this->getProject(),
            $this->page,
            $useUnnormalizedPageTitle
        );
    }

    /**
     * Public static method to wikify a summary, can be used on any arbitrary string.
     * Does NOT support section links unless you specify a page.
     * @param string $summary
     * @param Project $project
     * @param Page|null $page
     * @param bool $useUnnormalizedPageTitle Use the unnormalized page title to avoid
     *   an API call. This should be used only if you fetched the page title via other
     *   means (SQL query), and is not from user input alone.
     * @static
     * @return string
     */
    public static function wikifyString(
        string $summary,
        Project $project,
        ?Page $page = null,
        bool $useUnnormalizedPageTitle = false
    ): string {
        // The html_entity_decode makes & and &amp; display the same
        // But that is MW behaviour
        $summary = htmlspecialchars(html_entity_decode($summary), ENT_NOQUOTES);

        // First link raw URLs. Courtesy of https://stackoverflow.com/a/11641499/604142
        $summary = preg_replace(
            '%\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))%s',
            '<a target="_blank" href="$1">$1</a>',
            $summary
        );

        $sectionMatch = null;
        $isSection = preg_match_all("/^\/\* (.*?) \*\//", $summary, $sectionMatch);

        if ($isSection && isset($page)) {
            $pageUrl = $project->getUrlForPage($page->getTitle($useUnnormalizedPageTitle));
            $sectionTitle = $sectionMatch[1][0];

            // Must have underscores for the link to properly go to the section.
            // Have to decode twice; once for the entities added with htmlspecialchars;
            // And one for user entities (which are decoded in mw section ids).
            $sectionTitleLink = html_entity_decode(html_entity_decode(str_replace(' ', '_', $sectionTitle)));

            $sectionWikitext = "<a target='_blank' href='$pageUrl#$sectionTitleLink'>&rarr;</a>" .
                "<em class='text-muted'>" . $sectionTitle . ":</em> ";
            $summary = str_replace($sectionMatch[0][0], $sectionWikitext, $summary);
        }

        $linkMatch = null;

        while (preg_match_all("/\[\[:?([^\[\]]*?)]]/", $summary, $linkMatch)) {
            $wikiLinkParts = explode('|', $linkMatch[1][0]);
            $wikiLinkPath = htmlspecialchars($wikiLinkParts[0]);
            $wikiLinkText = htmlspecialchars(
                $wikiLinkParts[1] ?? $wikiLinkPath
            );

            // Use normalized page title (underscored, capitalized).
            $pageUrl = $project->getUrlForPage(ucfirst(str_replace(' ', '_', $wikiLinkPath)));

            $link = "<a target='_blank' href='$pageUrl'>$wikiLinkText</a>";
            $summary = str_replace($linkMatch[0][0], $link, $summary);
        }

        return $summary;
    }

    /**
     * Get edit summary as 'wikified' HTML markup (alias of Edit::getWikifiedComment()).
     * @return string
     */
    public function getWikifiedSummary(): string
    {
        return $this->getWikifiedComment();
    }

    /**
     * Get the project this edit was made on
     * @return Project
     */
    public function getProject(): Project
    {
        return $this->getPage()->getProject();
    }

    /**
     * Get the full URL to the diff of the edit
     * @return string
     */
    public function getDiffUrl(): string
    {
        return rtrim($this->getProject()->getUrlForPage('Special:Diff/' . $this->id), '/');
    }

    /**
     * Get the full permanent URL to the page at the time of the edit
     * @return string
     */
    public function getPermaUrl(): string
    {
        return rtrim($this->getProject()->getUrlForPage('Special:PermaLink/' . $this->id), '/');
    }

    /**
     * Was the edit a revert, based on the edit summary?
     * @return bool
     */
    public function isRevert(): bool
    {
        return $this->repository->getAutoEditsHelper()->isRevert($this->comment, $this->getProject());
    }

    /**
     * Get the name of the tool that was used to make this edit.
     * @return array|null The name of the tool(s) that was used to make the edit.
     */
    public function getTool(): ?array
    {
        return $this->repository->getAutoEditsHelper()->getTool($this->comment, $this->getProject(), $this->tags);
    }

    /**
     * Was the edit (semi-)automated, based on the edit summary?
     * @return bool
     */
    public function isAutomated(): bool
    {
        return (bool)$this->getTool();
    }

    /**
     * Was the edit made by a logged out user (IP or temporary account)?
     * @param Project $project
     * @return bool|null
     */
    public function isAnon(Project $project): ?bool
    {
        return $this->getUser() ? $this->getUser()->isAnon($project) : null;
    }

    /**
     * List of tag names for the edit.
     * Only filled in by PageInfo.
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Get HTML for the diff of this Edit.
     * @return string|null Raw HTML, must be wrapped in a <table> tag. Null if no comparison could be made.
     */
    public function getDiffHtml(): ?string
    {
        return $this->repository->getDiffHtml($this);
    }

    /**
     * Formats the data as an array for use in JSON APIs.
     * @param bool $includeProject
     * @return array
     * @internal This method assumes the Edit was constructed with data already filled in from a database query.
     */
    public function getForJson(bool $includeProject = false): array
    {
        $nsId = $this->getPage()->getNamespace();
        $pageTitle = $this->getPage()->getTitle(true);

        if ($nsId > 0) {
            $nsName = $this->getProject()->getNamespaces()[$nsId];
            $pageTitle = preg_replace("/^$nsName:/", '', $pageTitle);
        }

        $ret = [
            'page_title' => str_replace('_', ' ', $pageTitle),
            'namespace' => $nsId,
        ];
        if ($includeProject) {
            $ret += ['project' => $this->getProject()->getDomain()];
        }
        if ($this->getUser()) {
            $ret += ['username' => $this->getUser()->getUsername()];
        }
        $ret += [
            'rev_id' => $this->id,
            'timestamp' => $this->getUTCTimestamp(),
            'minor' => $this->minor,
            'length' => $this->length,
            'length_change' => $this->lengthChange,
            'comment' => $this->comment,
        ];
        if (null !== $this->reverted) {
            $ret['reverted'] = $this->reverted;
        }

        return $ret;
    }
}
