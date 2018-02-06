<?php
/**
 * This file contains only the Project class.
 */

namespace Xtools;

use Mediawiki\Api\MediawikiApi;
use Symfony\Component\VarDumper\VarDumper;

/**
 * A Project is a single wiki that XTools is querying.
 */
class Project extends Model
{

    /** @var string The project name as supplied by the user. */
    protected $nameUnnormalized;

    /** @var string[] Basic metadata about the project */
    protected $metadata;

    /** @var string[] Project's 'dbName', 'url' and 'lang'. */
    protected $basicInfo;

    /**
     * Whether the user being queried for in this session
     *   has opted in to restricted statistics
     * @var bool
     */
    protected $userOptedIn;

    /**
     * Create a new Project.
     * @param string $nameOrUrl The project's database name or URL.
     */
    public function __construct($nameOrUrl)
    {
        $this->nameUnnormalized = $nameOrUrl;
    }

    /**
     * Unique identifier this Project, to be used in cache keys.
     * @see Repository::getCacheKey()
     * @return string
     */
    public function getCacheKey()
    {
        return $this->getDatabaseName();
    }

    /**
     * Get 'dbName', 'url' and 'lang' of the project, the relevant basic info
     *   we can get from the meta database. This is all you need to make
     *   database queries. More comprehensive metadata can be fetched with
     *   getMetadata() at the expense of an API call, which may be cached.
     * @return string[]
     */
    protected function getBasicInfo()
    {
        if (empty($this->basicInfo)) {
            $this->basicInfo = $this->getRepository()->getOne($this->nameUnnormalized);
        }
        return $this->basicInfo;
    }

    /**
     * Get full metadata about the project. See ProjectRepository::getMetadata
     *   for more information.
     * @return string[]
     */
    protected function getMetadata()
    {
        if (empty($this->metadata)) {
            $url = $this->getBasicInfo()['url'];
            $this->metadata = $this->getRepository()->getMetadata($url);
        }
        return $this->metadata;
    }

    /**
     * Does this project exist?
     * @return bool
     */
    public function exists()
    {
        return !empty($this->getDomain());
    }

    /**
     * The unique domain name of this project, without protocol or path components.
     * This should be used as the canonical project identifier.
     *
     * @return string
     */
    public function getDomain()
    {
        $url = isset($this->getBasicInfo()['url']) ? $this->getBasicInfo()['url'] : '';
        return parse_url($url, PHP_URL_HOST);
    }

    /**
     * The name of the database for this project.
     *
     * @return string
     */
    public function getDatabaseName()
    {
        return isset($this->getBasicInfo()['dbName']) ? $this->getBasicInfo()['dbName'] : '';
    }

    /**
     * The language for this project.
     *
     * @return string
     */
    public function getLang()
    {
        return isset($this->getBasicInfo()['lang']) ? $this->getBasicInfo()['lang'] : '';
    }

    /**
     * The project URL is the fully-qualified domain name, with protocol and trailing slash.
     *
     * @param bool $withTrailingSlash Whether to append a slash.
     * @return string
     */
    public function getUrl($withTrailingSlash = true)
    {
        return rtrim($this->getBasicInfo()['url'], '/') . ($withTrailingSlash ? '/' : '');
    }

    /**
     * Get a MediawikiApi object for this Project.
     *
     * @return MediawikiApi
     */
    public function getApi()
    {
        return $this->getRepository()->getMediawikiApi($this);
    }

    /**
     * The base URL path of this project (that page titles are appended to).
     * For some wikis the title (apparently) may not be at the end.
     * Replace $1 with the article name.
     *
     * @link https://www.mediawiki.org/wiki/Manual:$wgArticlePath
     *
     * @return string
     */
    public function getArticlePath()
    {
        $metadata = $this->getMetadata();
        return isset($metadata['general']['articlePath'])
            ? $metadata['general']['articlePath']
            : '/wiki/$1';
    }

    /**
     * The URL path of the directory that contains index.php, with no trailing slash.
     * Defaults to '/w' which is the same as the normal WMF set-up.
     *
     * @link https://www.mediawiki.org/wiki/Manual:$wgScriptPath
     *
     * @return string
     */
    public function getScriptPath()
    {
        $metadata = $this->getMetadata();
        return isset($metadata['general']['scriptPath'])
            ? $metadata['general']['scriptPath']
            : '/w';
    }

    /**
     * The URL path to index.php
     * Defaults to '/w/index.php' which is the same as the normal WMF set-up.
     *
     * @return string
     */
    public function getScript()
    {
        $metadata = $this->getMetadata();
        return isset($metadata['general']['script'])
            ? $metadata['general']['script']
            : $this->getScriptPath() . '/index.php';
    }

    /**
     * The full URL to api.php
     *
     * @return string
     */
    public function getApiUrl()
    {
        return rtrim($this->getUrl(), '/') . $this->getRepository()->getApiPath();
    }

    /**
     * Get this project's title, the human-language full title of the wiki (e.g. "English
     * Wikipedia" or
     */
    public function getTitle()
    {
        $metadata = $this->getMetadata();
        return $metadata['general']['wikiName'].' ('.$this->getDomain().')';
    }

    /**
     * Get an array of this project's namespaces and their IDs.
     *
     * @return string[] Keys are IDs, values are names.
     */
    public function getNamespaces()
    {
        $metadata = $this->getMetadata();
        return $metadata['namespaces'];
    }

    /**
     * Get the title of the Main Page.
     * @return string
     */
    public function getMainPage()
    {
        $metadata = $this->getMetadata();
        return isset($metadata['general']['mainpage'])
            ? $metadata['general']['mainpage']
            : '';
    }

    /**
     * Get a list of users who are in one of the given user groups.
     * @param string[] User groups to search for.
     * @return string[] User groups keyed by user name.
     */
    public function getUsersInGroups($groups)
    {
        $users = [];
        $usersAndGroups = $this->getRepository()->getUsersInGroups($this, $groups);
        foreach ($usersAndGroups as $userAndGroup) {
            $username = $userAndGroup['user_name'];
            if (isset($users[$username])) {
                array_push($users[$username], $userAndGroup['ug_group']);
            } else {
                $users[$username] = [$userAndGroup['ug_group']];
            }
        }
        return $users;
    }

    /**
     * Get the name of the page on this project that the user must create in order to opt in for
     * restricted statistics display.
     * @param User $user
     * @return string
     */
    public function userOptInPage(User $user)
    {
        $localPageName = 'User:' . $user->getUsername() . '/EditCounterOptIn.js';
        return $localPageName;
    }

    /**
     * Has a user opted in to having their restricted statistics displayed to anyone?
     * @param User $user
     * @return bool
     */
    public function userHasOptedIn(User $user)
    {
        // 1. First check to see if the whole project has opted in.
        if (!$this->userOptedIn) {
            $optedInProjects = $this->getRepository()->optedIn();
            $this->userOptedIn = in_array($this->getDatabaseName(), $optedInProjects);
        }
        if ($this->userOptedIn) {
            return true;
        }

        // 2. Then see if the currently-logged-in user is requesting their own statistics.
        if ($user->isCurrentlyLoggedIn()) {
            return true;
        }

        // 3. Then see if the user has opted in on this project.
        $userNsId = 2;
        // Remove namespace since we're querying the database and supplying a namespace ID.
        $optInPage = preg_replace('/^User:/', '', $this->userOptInPage($user));
        $localExists = $this->getRepository()->pageHasContent($this, $userNsId, $optInPage);
        if ($localExists) {
            return true;
        }

        // 4. Lastly, see if they've opted in globally on the default project or Meta.
        $globalPageName = $user->getUsername() . '/EditCounterGlobalOptIn.js';
        $globalProject = $this->getRepository()->getGlobalProject();
        if ($globalProject instanceof Project) {
            $globalExists = $globalProject->getRepository()
                ->pageHasContent($globalProject, $userNsId, $globalPageName);
            if ($globalExists) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this project support page assessments?
     * @return bool
     */
    public function hasPageAssessments()
    {
        return (bool) $this->getRepository()->getAssessmentsConfig($this->getDomain());
    }

    /**
     * Get the image URL of the badge for the given page assessment
     * @param string $class Valid classification for project, such as 'Start', 'GA', etc.
     * @param bool $filenameOnly Get only the filename, not the URL.
     * @return string URL to image
     */
    public function getAssessmentBadgeURL($class, $filenameOnly = false)
    {
        $config = $this->getRepository()->getAssessmentsConfig($this->getDomain());

        if (isset($config['class'][$class])) {
            $url = "https://upload.wikimedia.org/wikipedia/commons/" . $config['class'][$class]['badge'];
        } elseif (isset($config['class']['Unknown'])) {
            $url = "https://upload.wikimedia.org/wikipedia/commons/" . $config['class']['Unknown']['badge'];
        } else {
            $url = "";
        }

        if ($filenameOnly) {
            $parts = explode('/', $url);
            return end($parts);
        }

        return $url;
    }

    /**
     * Normalize and quote a table name for use in SQL.
     * @param string $tableName
     * @param string|null $tableExtension Optional table extension, which will only get used if we're on Labs.
     * @return string Fully-qualified and quoted table name.
     */
    public function getTableName($tableName, $tableExtension = null)
    {
        return $this->getRepository()->getTableName($this->getDatabaseName(), $tableName, $tableExtension);
    }
}
