<?php
/**
 * This file contains only the Page class.
 */

namespace Xtools;

/**
 * A Page is a single wiki page in one project.
 */
class Page extends Model
{

    /** @var Project The project that this page belongs to. */
    protected $project;

    /** @var string The page name as provided at instantiation. */
    protected $unnormalizedPageName;

    /** @var string[] Metadata about this page. */
    protected $pageInfo;

    /** @var string[] Revision history of this page */
    protected $revisions;

    /**
     * Page constructor.
     * @param Project $project
     * @param string $pageName
     */
    public function __construct(Project $project, $pageName)
    {
        $this->project = $project;
        $this->unnormalizedPageName = $pageName;
    }

    /**
     * Get basic information about this page from the repository.
     * @return \string[]
     */
    protected function getPageInfo()
    {
        if (!$this->pageInfo) {
            $this->pageInfo = $this->getRepository()
                    ->getPageInfo($this->project, $this->unnormalizedPageName);
        }
        return $this->pageInfo;
    }

    /**
     * Get the page's title.
     * @return string
     */
    public function getTitle()
    {
        $info = $this->getPageInfo();
        return isset($info['title']) ? $info['title'] : $this->unnormalizedPageName;
    }

    /**
     * Get this page's database ID.
     * @return int
     */
    public function getId()
    {
        $info = $this->getPageInfo();
        return isset($info['pageid']) ? $info['pageid'] : null;
    }

    /**
     * Get this page's length in bytes.
     * @return int
     */
    public function getLength()
    {
        $info = $this->getPageInfo();
        return isset($info['length']) ? $info['length'] : null;
    }

    /**
     * Get HTML for the stylized display of the title.
     * The text will be the same as Page::getTitle().
     * @return string
     */
    public function getDisplayTitle()
    {
        $info = $this->getPageInfo();
        if (isset($info['displaytitle'])) {
            return $info['displaytitle'];
        }
        return $this->getTitle();
    }

    /**
     * Get the full URL of this page.
     * @return string
     */
    public function getUrl()
    {
        $info = $this->getPageInfo();
        return isset($info['fullurl']) ? $info['fullurl'] : null;
    }

    /**
     * Get the numerical ID of the namespace of this page.
     * @return int
     */
    public function getNamespace()
    {
        $info = $this->getPageInfo();
        return isset($info['ns']) ? $info['ns'] : null;
    }

    /**
     * Get the number of page watchers.
     * @return int
     */
    public function getWatchers()
    {
        $info = $this->getPageInfo();
        return isset($info['ns']) ? $info['ns'] : null;
    }

    /**
     * Whether or not this page exists.
     * @return bool
     */
    public function exists()
    {
        $info = $this->getPageInfo();
        return !isset($info['missing']);
    }

    /**
     * Get the Project to which this page belongs.
     * @return Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * Get the Wikidata ID of this page.
     * @return string
     */
    public function getWikidataId()
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
     * @return int
     */
    public function getNumRevisions(User $user = null)
    {
        // Return the count of revisions if already present
        if ($this->revisions) {
            return count($this->revisions);
        }

        // Otherwise do a COUNT in the event fetching
        // all revisions is not desired
        return $this->getRepository()->getNumRevisions($this, $user);
    }

    /**
     * Get all edits made to this page.
     * @param User|null $user Specify to get only revisions by the given user.
     * @return array
     */
    public function getRevisions(User $user = null)
    {
        if ($this->revisions) {
            return $this->revisions;
        }

        $data = $this->getRepository()->getRevisions($this, $user);
        $totalAdded = 0;
        $totalRemoved = 0;
        $revisions = [];
        foreach ($data as $revision) {
            if ($revision['length_change'] > 0) {
                $totalAdded += $revision['length_change'];
            } else {
                $totalRemoved += $revision['length_change'];
            }
            $revisions[] = $revision;
        }
        $this->revisions = $revisions;

        return $revisions;
    }
}
