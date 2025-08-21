<?php

declare(strict_types = 1);

namespace App\Model;

/**
 * A Project is a single wiki that XTools is querying.
 */
class Project extends Model
{
    protected PageAssessments $pageAssessments;

    /** @var string The project name as supplied by the user. */
    protected string $nameUnnormalized;

    /** @var string[]|null Basic metadata about the project */
    protected ?array $metadata;

    /** @var string[]|null Project's 'dbName', 'url' and 'lang'. */
    protected ?array $basicInfo;

    /**
     * Whether the user being queried for in this session has opted in to restricted statistics.
     * @var bool
     */
    protected bool $userOptedIn;

    /**
     * Create a new Project.
     * @param string $nameOrUrl The project's database name or URL.
     */
    public function __construct(string $nameOrUrl)
    {
        $this->nameUnnormalized = $nameOrUrl;
    }

    /**
     * Get the associated PageAssessments model.
     * @return PageAssessments
     * @codeCoverageIgnore
     */
    public function getPageAssessments(): PageAssessments
    {
        return $this->pageAssessments;
    }

    /**
     * @param PageAssessments $pageAssessments
     * @return Project
     * @codeCoverageIgnore
     */
    public function setPageAssessments(PageAssessments $pageAssessments): Project
    {
        $this->pageAssessments = $pageAssessments;
        return $this;
    }

    /**
     * Whether or not this project supports page assessments, or if they exist for the given namespace.
     * @param int|string|null $nsId Namespace ID, null if checking if project has page assessments for any namespace.
     * @return bool
     * @codeCoverageIgnore
     */
    public function hasPageAssessments($nsId = null): bool
    {
        if (null !== $nsId && (int)$nsId > 0) {
            return $this->pageAssessments->isSupportedNamespace((int)$nsId);
        } else {
            return $this->pageAssessments->isEnabled();
        }
    }

    /**
     * Whether or not this namespace is the Page namespace (of ProofreadPage).
     * Or true if it is 'all'.
     * @param int|string $namespace Namespace ID, or 'all'.
     * @return bool
     */
    public function isPrpPage($namespace): bool
    {
        return $this->hasProofreadPage() &&
            (
                !is_numeric($namespace) ||
                'Page' === $this->getCanonicalNamespace($namespace)
            );
    }

    /**
     * Get the list of the names of each ProofreadPage
     * quality level. Keys are 0, 1, 2, 3, and 4.
     * @return string[]
     * Just returns a Repository result.
     * @codeCoverageIgnore
     */
    public function getPrpQualityNames(): array
    {
        return $this->repository->getPrpQualityNames($this);
    }

    /**
     * Unique identifier this Project, to be used in cache keys.
     * @see Repository::getCacheKey()
     * @return string
     */
    public function getCacheKey(): string
    {
        return $this->getDatabaseName();
    }

    /**
     * Get 'dbName', 'url' and 'lang' of the project, the relevant basic info we can get from the meta database.
     * This is all you need to make database queries. More comprehensive metadata can be fetched with getMetadata()
     * at the expense of an API call, which may be cached.
     * @return string[]|null null if not found.
     */
    protected function getBasicInfo(): ?array
    {
        if (!isset($this->basicInfo)) {
            $this->basicInfo = $this->repository->getOne($this->nameUnnormalized);
        }
        return $this->basicInfo;
    }

    /**
     * Get full metadata about the project. See ProjectRepository::getMetadata() for more information.
     * @return array|null null if project not found.
     */
    protected function getMetadata(): ?array
    {
        if (!isset($this->metadata)) {
            $info = $this->getBasicInfo();
            if (!isset($info['url'])) {
                // Project is probably not replicated.
                return null;
            }
            $url = $this->getBasicInfo()['url'];
            $this->metadata = $this->getRepository()->getMetadata($url);
        }
        return $this->metadata;
    }

    /**
     * Does this project exist?
     * @return bool
     */
    public function exists(): bool
    {
        return !empty($this->getDomain());
    }

    /**
     * The unique domain name of this project, without protocol or path components.
     * This should be used as the canonical project identifier.
     * @return string|null null if nonexistent.
     */
    public function getDomain(): ?string
    {
        $url = $this->getBasicInfo()['url'] ?? '';
        return parse_url($url, PHP_URL_HOST);
    }

    /**
     * The name of the database for this project.
     * @return string
     */
    public function getDatabaseName(): string
    {
        return $this->getBasicInfo()['dbName'] ?? '';
    }

    /**
     * The language for this project.
     * @return string
     */
    public function getLang(): string
    {
        return $this->getBasicInfo()['lang'] ?? '';
    }

    /**
     * The project URL is the fully-qualified domain name, with protocol and trailing slash.
     * @param bool $withTrailingSlash Whether to append a slash.
     * @return string
     */
    public function getUrl(bool $withTrailingSlash = true): string
    {
        return rtrim($this->getBasicInfo()['url'], '/') . ($withTrailingSlash ? '/' : '');
    }

    /**
     * @param Page|string $page Full page title including namespace, or a Page object.
     * @param bool $useUnnormalizedPageTitle Use the unnormalized page title to avoid
     *    an API call. This should be used only if you fetched the page title via other
     *    means (SQL query), and is not from user input alone. Only applicable if $page
     *    is a Page object.
     * @return string
     */
    public function getUrlForPage($page, bool $useUnnormalizedPageTitle = false): string
    {
        if ($page instanceof Page) {
            $page = $page->getTitle($useUnnormalizedPageTitle);
        }
        return str_replace('$1', $page, $this->getUrl(false) . $this->getArticlePath());
    }

    /**
     * The base URL path of this project (that page titles are appended to).
     * For some wikis the title (apparently) may not be at the end.
     * Replace $1 with the article name.
     * @link https://www.mediawiki.org/wiki/Manual:$wgArticlePath
     * @return string
     */
    public function getArticlePath(): string
    {
        $metadata = $this->getMetadata();
        return $metadata['general']['articlePath'] ?? '/wiki/$1';
    }

    /**
     * The URL path of the directory that contains index.php, with no trailing slash.
     * Defaults to '/w' which is the same as the normal WMF set-up.
     * @link https://www.mediawiki.org/wiki/Manual:$wgScriptPath
     * @return string
     */
    public function getScriptPath(): string
    {
        $metadata = $this->getMetadata();
        return $metadata['general']['scriptPath'] ?? '/w';
    }

    /**
     * The URL path to index.php
     * Defaults to '/w/index.php' which is the same as the normal WMF set-up.
     * @return string
     */
    public function getScript(): string
    {
        $metadata = $this->getMetadata();
        return $metadata['general']['script'] ?? $this->getScriptPath() . '/index.php';
    }

    /**
     * The full URL to api.php.
     * @return string
     */
    public function getApiUrl(): string
    {
        return rtrim($this->getUrl(), '/') . $this->getRepository()->getApiPath();
    }

    /**
     * Get the project's title, the human-language full title of the wiki (e.g. "English Wikipedia (en.wikipedia.org)").
     */
    public function getTitle(): string
    {
        $metadata = $this->getMetadata();
        return $metadata['general']['wikiName'].' ('.$this->getDomain().')';
    }

    /**
     * Get an array of this project's namespaces and their IDs.
     * @return string[] Keys are IDs, values are names.
     */
    public function getNamespaces(): array
    {
        $metadata = $this->getMetadata();
        return $metadata['namespaces'];
    }

    /**
     * Get the canonical namespace name for a namespace ID.
     * Or '' if the namespace does not exist.
     * @param int $namespace
     * @return string
     */
    public function getCanonicalNamespace($namespace): string
    {
        $canonicalNamespaces = $this->getMetadata()['canonical_namespaces'];
        if (array_key_exists($namespace, $canonicalNamespaces)) {
            return $canonicalNamespaces[$namespace];
        } else {
            return '';
        }
    }

    /**
     * Get the title of the Main Page.
     * @return string
     */
    public function getMainPage(): string
    {
        $metadata = $this->getMetadata();
        return $metadata['general']['mainpage'] ?? '';
    }

    /**
     * List of extensions that are installed on the wiki.
     * @return string[]
     */
    public function getInstalledExtensions(): array
    {
        // Quick cache, valid only for the same request.
        if (!isset($this->installedExtensions) ||
            !is_array($this->installedExtensions)
        ) {
            $this->installedExtensions = $this->getRepository()->getInstalledExtensions($this);
        }
        return $this->installedExtensions;
    }

    /**
     * Get if this Wiki has the PageTriage extension (for review counts)
     * @return bool Whether it does.
     */
    public function hasPageTriage() : bool
    {
        $extensions = $this->getInstalledExtensions();
        return in_array('PageTriage', $extensions);
    }

    /**
     * Get if this Wiki has the ProofreadPage extension.
     * @return bool
     */
    public function hasProofreadPage(): bool
    {
        $extensions = $this->getInstalledExtensions();
        return in_array('ProofreadPage', $extensions);
    }

    /**
     * Whether this wiki has the VisualEditor extension enabled.
     * @return bool
     */
    public function hasVisualEditor(): bool
    {
        $extensions = $this->getInstalledExtensions();
        return in_array('VisualEditor', $extensions);
    }

    /**
     * Whether the project has temporary accounts enabled.
     * @return bool
     */
    public function hasTempAccounts(): bool
    {
        $metadata = $this->getMetadata();
        return null !== $metadata['tempAccountPatterns'];
    }

    /**
     * Get the patterns that match temporary accounts.
     * @return string[]
     */
    public function getTempAccountPatterns(): array
    {
        $metadata = $this->getMetadata();
        return $metadata['tempAccountPatterns'] ?? [];
    }

    /**
     * Get a list of users who are in one of the given user groups.
     * @param string[] $groups User groups to search for.
     * @param string[] $globalGroups Global groups to search for.
     * @return string[] User groups keyed by user name.
     */
    public function getUsersInGroups(array $groups, array $globalGroups): array
    {
        $users = [];
        $usersAndGroups = $this->getRepository()->getUsersInGroups($this, $groups, $globalGroups);
        foreach ($usersAndGroups as $userAndGroup) {
            $username = $userAndGroup['user_name'];
            if (isset($users[$username])) {
                $users[$username][] = $userAndGroup['user_group'];
            } else {
                $users[$username] = [$userAndGroup['user_group']];
            }
        }
        return $users;
    }

    /**
     * Get the name of the page on this project that the user must create in order to opt in for restricted statistics.
     * @param User $user
     * @return string
     */
    public function userOptInPage(User $user): string
    {
        return 'User:' . $user->getUsername() . '/EditCounterOptIn.js';
    }

    /**
     * Has a user opted in to having their restricted statistics displayed to anyone?
     * @param User $user
     * @return bool
     */
    public function userHasOptedIn(User $user): bool
    {
        // 1. First check to see if the whole project has opted in.
        if (!isset($this->userOptedIn)) {
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
        $globalExists = $globalProject->getRepository()
            ->pageHasContent($globalProject, $userNsId, $globalPageName);
        if ($globalExists) {
            return true;
        }

        return false;
    }

    /**
     * Normalize and quote a table name for use in SQL.
     * @param string $tableName
     * @param string|null $tableExtension Optional table extension, which will only get used if we're on Labs.
     * @return string Fully-qualified and quoted table name.
     */
    public function getTableName(string $tableName, ?string $tableExtension = null): string
    {
        return $this->getRepository()->getTableName($this->getDatabaseName(), $tableName, $tableExtension);
    }
}
