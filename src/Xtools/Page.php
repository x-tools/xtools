<?php
/**
 * This file contains only the Page class.
 */

namespace Xtools;

use DateTime;

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
    public function __construct(Project $project, $pageName)
    {
        $this->project = $project;
        $this->unnormalizedPageName = $pageName;
    }

    /**
     * Unique identifier for this Page, to be used in cache keys.
     * Use of md5 ensures the cache key does not contain reserved characters.
     * @see Repository::getCacheKey()
     * @return string
     * @codeCoverageIgnore
     */
    public function getCacheKey()
    {
        return md5($this->getId());
    }

    /**
     * Get basic information about this page from the repository.
     * @return \string[]
     */
    protected function getPageInfo()
    {
        if (empty($this->pageInfo)) {
            $this->pageInfo = $this->getRepository()
                    ->getPageInfo($this->project, $this->unnormalizedPageName);
        }
        return $this->pageInfo;
    }

    /**
     * Get the page's title.
     * @param bool $useUnnormalized Use the unnormalized page title to avoid an
     *    API call. This should be used only if you fetched the page title via
     *    other means (SQL query), and is not from user input alone.
     * @return string
     */
    public function getTitle($useUnnormalized = false)
    {
        if ($useUnnormalized) {
            return $this->unnormalizedPageName;
        }
        $info = $this->getPageInfo();
        return isset($info['title']) ? $info['title'] : $this->unnormalizedPageName;
    }

    /**
     * Get the page's title without the namespace.
     * @return string
     */
    public function getTitleWithoutNamespace()
    {
        $info = $this->getPageInfo();
        $title = isset($info['title']) ? $info['title'] : $this->unnormalizedPageName;
        $nsName = $this->getNamespaceName();
        return str_replace($nsName . ':', '', $title);
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
     * Get the name of the namespace of this page.
     * @return string
     */
    public function getNamespaceName()
    {
        $info = $this->getPageInfo();
        return isset($info['ns'])
            ? $this->getProject()->getNamespaces()[$info['ns']]
            : null;
    }

    /**
     * Get the number of page watchers.
     * @return int
     */
    public function getWatchers()
    {
        $info = $this->getPageInfo();
        return isset($info['watchers']) ? $info['watchers'] : null;
    }

    /**
     * Get the HTML content of the body of the page.
     * @param DateTime|int $target If a DateTime object, the
     *   revision at that time will be returned. If an integer, it is
     *   assumed to be the actual revision ID.
     * @return string
     */
    public function getHTMLContent($target = null)
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
    public function exists()
    {
        $info = $this->getPageInfo();
        return !isset($info['missing']) && !isset($info['invalid']);
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
     * Get the language code for this page.
     * If not set, the language code for the project is returned.
     * @return string
     */
    public function getLang()
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
     * @param false|int $start
     * @param false|int $end
     * @return int
     */
    public function getNumRevisions(User $user = null, $start = false, $end = false)
    {
        // If a user is given, we will not cache the result via instance variable.
        if ($user !== null) {
            return (int) $this->getRepository()->getNumRevisions($this, $user, $start, $end);
        }

        // Return cached value, if present.
        if ($this->numRevisions !== null) {
            return $this->numRevisions;
        }

        // Otherwise, return the count of all revisions if already present.
        if ($this->revisions !== null) {
            $this->numRevisions = count($this->revisions);
        } else {
            // Otherwise do a COUNT in the event fetching all revisions is not desired.
            $this->numRevisions = (int) $this->getRepository()->getNumRevisions($this, null, $start, $end);
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
    public function getRevisions(User $user = null, $start = false, $end = false)
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
    public function getWikitext()
    {
        $content = $this->getRepository()->getPagesWikitext(
            $this->getProject(),
            [ $this->getTitle() ]
        );

        return isset($content[$this->getTitle()])
            ? $content[$this->getTitle()]
            : null;
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
     * @return Doctrine\DBAL\Driver\PDOStatement
     */
    public function getRevisionsStmt(
        User $user = null,
        $limit = null,
        $numRevisions = null,
        $start = false,
        $end = false
    ) {
        // If we have a limit, we need to know the total number of revisions so that PageRepo
        // will properly set the OFFSET. See PageRepository::getRevisionsStmt() for more info.
        if (isset($limit) && $numRevisions === null) {
            $numRevisions = $this->getNumRevisions($user, $start, $end);
        }
        return $this->getRepository()->getRevisionsStmt($this, $user, $limit, $numRevisions, $start, $end);
    }

    /**
     * Get various basic info used in the API, including the
     *   number of revisions, unique authors, initial author
     *   and edit count of the initial author.
     * This is combined into one query for better performance.
     * Caching is intentionally disabled, because using the gadget,
     *   this will get hit for a different page constantly, where
     *   the likelihood of cache benefiting us is slim.
     * @return string[]
     */
    public function getBasicEditingInfo()
    {
        return $this->getRepository()->getBasicEditingInfo($this);
    }

    /**
     * Get assessments of this page
     * @see https://www.mediawiki.org/wiki/Extension:PageAssessments
     * @return string[]|false `false` if unsupported, or array in the format of:
     *         [
     *             'assessment' => 'C', // overall assessment
     *             'wikiprojects' => [
     *                 'Biography' => [
     *                     'assessment' => 'C',
     *                     'badge' => 'url',
     *                 ],
     *                 ...
     *             ],
     *             'wikiproject_prefix' => 'Wikipedia:WikiProject_',
     *         ]
     */
    public function getAssessments()
    {
        if (!$this->project->hasPageAssessments() || $this->getNamespace() !== 0) {
            return false;
        }

        $projectDomain = $this->project->getDomain();
        $config = $this->project->getRepository()->getAssessmentsConfig($projectDomain);
        $data = $this->getRepository()->getAssessments($this->project, [$this->getId()]);

        // Set the default decorations for the overall quality assessment
        // This will be replaced with the first valid class defined for any WikiProject
        $overallQuality = $config['class']['Unknown'];
        $overallQuality['value'] = '???';
        $overallQuality['badge'] = $this->project->getAssessmentBadgeURL($overallQuality['badge']);

        $decoratedAssessments = [];

        foreach ($data as $assessment) {
            $classValue = $assessment['class'];

            // Use ??? as the presented value when the class is unknown or is not defined in the config
            if ($classValue === 'Unknown' || $classValue === '' || !isset($config['class'][$classValue])) {
                $classAttrs = $config['class']['Unknown'];
                $assessment['class']['value'] = '???';
                $assessment['class']['category'] = $classAttrs['category'];
                $assessment['class']['color'] = $classAttrs['color'];
                $assessment['class']['badge'] = "https://upload.wikimedia.org/wikipedia/commons/"
                    . $classAttrs['badge'];
            } else {
                $classAttrs = $config['class'][$classValue];
                $assessment['class'] = [
                    'value' => $classValue,
                    'color' => $classAttrs['color'],
                    'category' => $classAttrs['category'],
                ];

                // add full URL to badge icon
                if ($classAttrs['badge'] !== '') {
                    $assessment['class']['badge'] = $this->project->getAssessmentBadgeURL($classValue);
                }
            }

            if ($overallQuality['value'] === '???') {
                $overallQuality = $assessment['class'];
                $overallQuality['category'] = $classAttrs['category'];
            }

            $importanceValue = $assessment['importance'];
            $importanceUnknown = $importanceValue === 'Unknown' || $importanceValue === '';

            if ($importanceUnknown || !isset($config['importance'][$importanceValue])) {
                $importanceAttrs = $config['importance']['Unknown'];
                $assessment['importance'] = $importanceAttrs;
                $assessment['importance']['value'] = '???';
                $assessment['importance']['category'] = $importanceAttrs['category'];
            } else {
                $importanceAttrs = $config['importance'][$importanceValue];
                $assessment['importance'] = [
                    'value' => $importanceValue,
                    'color' => $importanceAttrs['color'],
                    'weight' => $importanceAttrs['weight'], // numerical weight for sorting purposes
                    'category' => $importanceAttrs['category'],
                ];
            }

            $decoratedAssessments[$assessment['wikiproject']] = $assessment;
        }

        return [
            'assessment' => $overallQuality,
            'wikiprojects' => $decoratedAssessments,
            'wikiproject_prefix' => $config['wikiproject_prefix']
        ];
    }

    /**
     * Get CheckWiki errors for this page
     * @return string[] See getErrors() for format
     */
    public function getCheckWikiErrors()
    {
        return $this->getRepository()->getCheckWikiErrors($this);
    }

    /**
     * Get Wikidata errors for this page
     * @return string[] See getErrors() for format
     */
    public function getWikidataErrors()
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
    public function getErrors()
    {
        // Includes label and description
        $wikidataErrors = $this->getWikidataErrors();

        $checkWikiErrors = $this->getCheckWikiErrors();

        return array_merge($wikidataErrors, $checkWikiErrors);
    }

    /**
     * Get all wikidata items for the page, not just languages of sister projects
     * @return int Number of records.
     */
    public function getWikidataItems()
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
    public function countWikidataItems()
    {
        if (is_array($this->wikidataItems)) {
            $this->numWikidataItems = count($this->wikidataItems);
        } elseif ($this->numWikidataItems === null) {
            $this->numWikidataItems = $this->getRepository()->countWikidataItems($this);
        }
        return $this->numWikidataItems;
    }

    /**
     * Get number of in and outgoing links and redirects to this page.
     * @return string[] Counts with the keys 'links_ext_count', 'links_out_count',
     *                  'links_in_count' and 'redirects_count'
     */
    public function countLinksAndRedirects()
    {
        return $this->getRepository()->countLinksAndRedirects($this);
    }

    /**
     * Get the sum of pageviews for the given page and timeframe.
     * @param string|DateTime $start In the format YYYYMMDD
     * @param string|DateTime $end In the format YYYYMMDD
     * @return int
     */
    public function getPageviews($start, $end)
    {
        try {
            $pageviews = $this->getRepository()->getPageviews($this, $start, $end);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // 404 means zero pageviews
            return 0;
        }

        return array_sum(array_map(function ($item) {
            return (int) $item['views'];
        }, $pageviews['items']));
    }

    /**
     * Get the sum of pageviews over the last N days
     * @param int $days Default 30
     * @return int Number of pageviews
     */
    public function getLastPageviews($days = 30)
    {
        $start = date('Ymd', strtotime("-$days days"));
        $end = date('Ymd');
        return $this->getPageviews($start, $end);
    }

    /**
     * Is the page the project's Main Page?
     * @return bool
     */
    public function isMainPage()
    {
        return $this->getProject()->getMainPage() === $this->getTitle();
    }
}
