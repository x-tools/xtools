<?php
/**
 * This file contains only the Edit class.
 */

namespace Xtools;

use Xtools\User;
use Symfony\Component\DependencyInjection\Container;
use DateTime;

/**
 * An Edit is a single edit to a page on one project.
 */
class Edit extends Model
{

    /** @var Page the page associated with this edit */
    protected $page;

    /** @var int ID of the revision */
    protected $id;

    /** @var DateTime Timestamp of the revision */
    protected $timestamp;

    /** @var bool Whether or not this edit was a minor edit */
    protected $minor;

    /** @var int|string|null Length of the page as of this edit, in bytes */
    protected $length;

    /** @var int|string|null The diff size of this edit */
    protected $lengthChange;

    /** @var User - User object of who made the edit */
    protected $user;

    /** @var string The edit summary */
    protected $comment;

    /**
     * Edit constructor.
     * @param Page $page
     * @param string[] $attrs Attributes, as retrieved by PageRepository::getRevisions()
     */
    public function __construct(Page $page, $attrs)
    {
        $this->page = $page;

        // Copy over supported attributes
        $this->id = (int) $attrs['id'];

        // Allow DateTime or string (latter assumed to be of format YmdHis)
        if ($attrs['timestamp'] instanceof DateTime) {
            $this->timestamp = $attrs['timestamp'];
        } else {
            $this->timestamp = DateTime::createFromFormat('YmdHis', $attrs['timestamp']);
        }

        $this->minor = $attrs['minor'] === '1';

        // NOTE: Do not type cast into an integer. Null values are
        //   our indication that the revision was revision-deleted.
        $this->length = $attrs['length'];
        $this->lengthChange = $attrs['length_change'];

        $this->user = new User($attrs['username']);
        $this->comment = $attrs['comment'];
    }

    /**
     * Unique identifier for this Edit, to be used in cache keys.
     * @see Repository::getCacheKey()
     * @return string
     */
    public function getCacheKey()
    {
        return $this->id;
    }

    /**
     * Get the page to which this edit belongs.
     * @return Page
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * ID of the edit.
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the edit's timestamp.
     * @return DateTime
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Year the revision was made.
     * @return string
     */
    public function getYear()
    {
        return $this->timestamp->format('Y');
    }

    /**
     * Get the numeric representation of the month the revision was made, with leading zeros.
     * @return string
     */
    public function getMonth()
    {
        return $this->timestamp->format('m');
    }

    /**
     * Whether or not this edit was a minor edit.
     * @return bool
     */
    public function getMinor()
    {
        return $this->minor;
    }

    /**
     * Alias of getMinor()
     * @return bool Whether or not this edit was a minor edit
     */
    public function isMinor()
    {
        return $this->getMinor();
    }

    /**
     * Length of the page as of this edit, in bytes.
     * @see Edit::getSize() Edit::getSize() for the size <em>change</em>.
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * The diff size of this edit.
     * @return int Signed length change in bytes.
     */
    public function getSize()
    {
        return $this->lengthChange;
    }

    /**
     * Alias of getSize()
     * @return int The diff size of this edit
     */
    public function getLengthChange()
    {
        return $this->getSize();
    }

    /**
     * Get the user who made the edit.
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Get the edit summary.
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Get the edit summary (alias of Edit::getComment()).
     * @return string
     */
    public function getSummary()
    {
        return $this->getComment();
    }

    /**
     * Get edit summary as 'wikified' HTML markup
     * @param bool $useUnnormalizedPageTitle Use the unnormalized page title to avoid
     *   an API call. This should be used only if you fetched the page title via other
     *   means (SQL query), and is not from user input alone.
     * @return string Safe HTML
     */
    public function getWikifiedComment($useUnnormalizedPageTitle = false)
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
     * @param Page $page
     * @param bool $useUnnormalizedPageTitle Use the unnormalized page title to avoid
     *   an API call. This should be used only if you fetched the page title via other
     *   means (SQL query), and is not from user input alone.
     * @static
     * @return string
     */
    public static function wikifyString(
        $summary,
        Project $project,
        Page $page = null,
        $useUnnormalizedPageTitle = false
    ) {
        $summary = htmlspecialchars($summary, ENT_NOQUOTES);
        $sectionMatch = null;
        $isSection = preg_match_all("/^\/\* (.*?) \*\//", $summary, $sectionMatch);

        if ($isSection && isset($page)) {
            $pageUrl = $project->getUrl(false) . str_replace(
                '$1',
                $page->getTitle($useUnnormalizedPageTitle),
                $project->getArticlePath()
            );
            $sectionTitle = $sectionMatch[1][0];

            // Must have underscores for the link to properly go to the section.
            $sectionTitleLink = htmlspecialchars(str_replace(' ', '_', $sectionTitle));

            $sectionWikitext = "<a target='_blank' href='$pageUrl#$sectionTitleLink'>&rarr;</a>" .
                "<em class='text-muted'>" . htmlspecialchars($sectionTitle) . ":</em> ";
            $summary = str_replace($sectionMatch[0][0], $sectionWikitext, $summary);
        }

        $linkMatch = null;

        while (preg_match_all("/\[\[:?(.*?)\]\]/", $summary, $linkMatch)) {
            $wikiLinkParts = explode('|', $linkMatch[1][0]);
            $wikiLinkPath = htmlspecialchars($wikiLinkParts[0]);
            $wikiLinkText = htmlspecialchars(
                isset($wikiLinkParts[1]) ? $wikiLinkParts[1] : $wikiLinkPath
            );

            // Use normalized page title (underscored, capitalized).
            $pageUrl = $project->getUrl(false) . str_replace(
                '$1',
                ucfirst(str_replace(' ', '_', $wikiLinkPath)),
                $project->getArticlePath()
            );

            $link = "<a target='_blank' href='$pageUrl'>$wikiLinkText</a>";
            $summary = str_replace($linkMatch[0][0], $link, $summary);
        }

        return $summary;
    }

    /**
     * Get edit summary as 'wikified' HTML markup (alias of Edit::getWikifiedSummary()).
     * @return string
     */
    public function getWikifiedSummary()
    {
        return $this->getWikifiedComment();
    }

    /**
     * Get the project this edit was made on
     * @return Project
     */
    public function getProject()
    {
        return $this->getPage()->getProject();
    }

    /**
     * Get the full URL to the diff of the edit
     * @return string
     */
    public function getDiffUrl()
    {
        $project = $this->getProject();
        $path = str_replace('$1', 'Special:Diff/' . $this->id, $project->getArticlePath());
        return rtrim($project->getUrl(), '/') . $path;
    }

    /**
     * Get the full permanent URL to the page at the time of the edit
     * @return string
     */
    public function getPermaUrl()
    {
        $project = $this->getProject();
        $path = str_replace('$1', 'Special:PermaLink/' . $this->id, $project->getArticlePath());
        return rtrim($project->getUrl(), '/') . $path;
    }

    /**
     * Was the edit a revert, based on the edit summary?
     * @param Container $container The DI container.
     * @return bool
     */
    public function isRevert(Container $container)
    {
        $automatedEditsHelper = $container->get('app.automated_edits_helper');
        return $automatedEditsHelper->isRevert($this->comment, $this->getProject()->getDomain());
    }

    /**
     * Get the name of the tool that was used to make this edit.
     * @param Container $container The DI container.
     * @return string|false The name of the tool that was used to make the edit
     */
    public function getTool(Container $container)
    {
        $automatedEditsHelper = $container->get('app.automated_edits_helper');
        return $automatedEditsHelper->getTool($this->comment, $this->getProject()->getDomain());
    }

    /**
     * Was the edit (semi-)automated, based on the edit summary?
     * @param  Container $container [description]
     * @return bool
     */
    public function isAutomated(Container $container)
    {
        return (bool) $this->getTool($container);
    }

    /**
     * Was the edit made by a logged out user?
     * @return bool
     */
    public function isAnon()
    {
        return $this->getUser()->isAnon();
    }
}
