<?php
/**
 * This file contains only the Edit class.
 */

namespace Xtools;

use Xtools\User;
use AppBundle\Helper\AutomatedEditsHelper;
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

    /** @var int Length of the page as of this edit, in bytes */
    protected $length;

    /** @var int The diff size of this edit */
    protected $length_change;

    /** @var User - User object of who made the edit */
    protected $user;

    /** @var string The edit summary */
    protected $comment;

    /**
     * Edit constructor.
     * @param Page $page
     * @param string[] $attrs Attributes, as retrieved by PagesRepository->getRevisions()
     */
    public function __construct(Page $page, $attrs)
    {
        $this->page = $page;

        // Copy over supported attributes
        $this->id = (int) $attrs['id'];
        $this->timestamp = DateTime::createFromFormat('YmdHis', $attrs['timestamp']);
        $this->minor = $attrs['minor'] === '1';

        // NOTE: Do not type cast into an integer. Null values are
        //   our indication that the revision was revision-deleted.
        $this->length = $attrs['length'];
        $this->length_change = $attrs['length_change'];

        $this->user = new User($attrs['username']);
        $this->comment = $attrs['comment'];
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
        return $this->length_change;
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
     * @return bool
     */
    public function isRevert()
    {
        $automatedEditsHelper = $this->container->get('app.automated_edits_helper');
        return $automatedEditsHelper->isRevert($this->comment);
    }

    /**
     * Was the edit (semi-)automated, based on the edit summary?
     * @return string|false The name of the tool that was used to make the edit
     */
    public function isAutomated()
    {
        $automatedEditsHelper = $this->container->get('app.automated_edits_helper');
        return $automatedEditsHelper->getTool($this->comment);
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
