<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Model\PageAssessments;
use App\Model\Project;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * This class provides data to the Project class.
 * @codeCoverageIgnore
 */
class ProjectRepository extends Repository
{
    protected PageAssessmentsRepository $assessmentsRepo;

    /** @var string[] Basic metadata if XTools is in single-wiki mode. */
    protected array $singleBasicInfo;

    /** @var string The cache key for the 'all project' metadata. */
    protected string $cacheKeyAllProjects = 'allprojects';

    /** @var string The configured default project. */
    protected string $defaultProject;

    /** @var bool Whether XTools is configured to run on a single wiki or not. */
    protected bool $singleWiki;

    /** @var array Projects that have opted into showing restricted stats to everyone. */
    protected array $optedIn;

    /** @var string The project's API path. */
    protected string $apiPath;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param CacheItemPoolInterface $cache
     * @param Client $guzzle
     * @param LoggerInterface $logger
     * @param ParameterBagInterface $parameterBag
     * @param bool $isWMF
     * @param int $queryTimeout
     * @param PageAssessmentsRepository $assessmentsRepo
     * @param string $defaultProject
     * @param bool $singleWiki
     * @param array $optedIn
     * @param string $apiPath
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        CacheItemPoolInterface $cache,
        Client $guzzle,
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
        bool $isWMF,
        int $queryTimeout,
        PageAssessmentsRepository $assessmentsRepo,
        string $defaultProject,
        bool $singleWiki,
        array $optedIn,
        string $apiPath
    ) {
        $this->assessmentsRepo = $assessmentsRepo;
        $this->defaultProject = $defaultProject;
        $this->singleWiki = $singleWiki;
        $this->optedIn = $optedIn;
        $this->apiPath = $apiPath;
        parent::__construct($managerRegistry, $cache, $guzzle, $logger, $parameterBag, $isWMF, $queryTimeout);
    }

    /**
     * Convenience method to get a new Project object based on a given identification string.
     * @param string $projectIdent The domain name, database name, or URL of a project.
     * @return Project
     */
    public function getProject(string $projectIdent): Project
    {
        $project = new Project($projectIdent);
        $project->setRepository($this);
        $project->setPageAssessments(new PageAssessments($this->assessmentsRepo, $project));

        if ($this->singleWiki) {
            $this->setSingleBasicInfo([
                'url' => $this->parameterBag->get('wiki_url'),
                'dbName' => '', // Just so this will pass in CI.
                // TODO: this will need to be restored for third party support; KEYWORD: isWMF
                // 'dbName' => $this->parameterBag->('database_replica_name'),
            ]);
        }

        return $project;
    }

    /**
     * Get the XTools default project.
     * @return Project
     */
    public function getDefaultProject(): Project
    {
        return $this->getProject($this->defaultProject);
    }

    /**
     * Get the global 'meta' project, which is either Meta (if this is Labs) or the default project.
     * @return Project
     */
    public function getGlobalProject(): Project
    {
        if ($this->isWMF) {
            return $this->getProject('metawiki');
        } else {
            return $this->getDefaultProject();
        }
    }

    /**
     * For single-wiki installations, you must manually set the wiki URL and database name
     * (because there's no meta.wiki database to query).
     * @param array $metadata
     * @throws Exception
     */
    public function setSingleBasicInfo(array $metadata): void
    {
        if (!array_key_exists('url', $metadata) || !array_key_exists('dbName', $metadata)) {
            $error = "Single-wiki metadata should contain 'url', 'dbName' and 'lang' keys.";
            throw new Exception($error);
        }
        $this->singleBasicInfo = array_intersect_key($metadata, [
            'url' => '',
            'dbName' => '',
            'lang' => '',
        ]);
    }

    /**
     * Get the 'dbName', 'url' and 'lang' of all projects.
     * @return string[][] Each item has 'dbName', 'url' and 'lang' keys.
     */
    public function getAll(): array
    {
        $this->logger->debug(__METHOD__." Getting all projects' metadata");
        // Single wiki mode?
        if (!empty($this->singleBasicInfo)) {
            return [$this->getOne('')];
        }

        // Maybe we've already fetched it.
        if ($this->cache->hasItem($this->cacheKeyAllProjects)) {
            return $this->cache->getItem($this->cacheKeyAllProjects)->get();
        }

        if ($this->parameterBag->has("database_meta_table")) {
            $table = $this->parameterBag->get('database_meta_name') . '.' .
                $this->parameterBag->get('database_meta_table');
        } else {
            $table = "meta_p.wiki";
        }

        // Otherwise, fetch all from the database.
        $sql = "SELECT dbname AS dbName, url, lang FROM $table";
        $projects = $this->executeProjectsQuery('meta', $sql)
            ->fetchAllAssociative();
        $projectsMetadata = [];
        foreach ($projects as $project) {
            $projectsMetadata[$project['dbName']] = $project;
        }

        // Cache for one day and return.
        return $this->setCache(
            $this->cacheKeyAllProjects,
            $projectsMetadata,
            'P1D'
        );
    }

    /**
     * Get the 'dbName', 'url' and 'lang' of a project. This is all you need to make database queries.
     * More comprehensive metadata can be fetched with getMetadata() at the expense of an API call.
     * @param string $project A project URL, domain name, or database name.
     * @return string[]|null With 'dbName', 'url' and 'lang' keys; or null if not found.
     */
    public function getOne(string $project): ?array
    {
        $this->logger->debug(__METHOD__." Getting metadata about $project");
        // For single-wiki setups, every project is the same.
        if (isset($this->singleBasicInfo)) {
            return $this->singleBasicInfo;
        }

        // Remove _p suffix.
        $project = rtrim($project, '_p');

        // For multi-wiki setups, first check the cache.
        // First the all-projects cache, then the individual one.
        if ($this->cache->hasItem($this->cacheKeyAllProjects)) {
            foreach ($this->cache->getItem($this->cacheKeyAllProjects)->get() as $projMetadata) {
                if ($projMetadata['dbName'] == "$project"
                    || $projMetadata['url'] == "$project"
                    || $projMetadata['url'] == "https://$project"
                    || $projMetadata['url'] == "https://$project.org"
                    || $projMetadata['url'] == "https://www.$project") {
                    $this->logger->debug(__METHOD__ . " Using cached data for $project");
                    return $projMetadata;
                }
            }
        }
        $cacheKey = $this->getCacheKey($project, 'project');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // TODO: make this configurable if XTools is to work on 3rd party wiki farms
        $table = "meta_p.wiki";

        // Otherwise, fetch the project's metadata from the meta.wiki table.
        $sql = "SELECT dbname AS dbName, url, lang
                FROM $table
                WHERE dbname = :project
                    OR url LIKE :projectUrl
                    OR url LIKE :projectUrl2
                    OR url LIKE :projectUrl3
                    OR url LIKE :projectUrl4";
        $basicInfo = $this->executeProjectsQuery('meta', $sql, [
            'project' => $project,
            'projectUrl' => "https://$project",
            'projectUrl2' => "https://$project.org",
            'projectUrl3' => "https://www.$project",
            'projectUrl4' => "https://www.$project.org",
        ])->fetchAssociative();
        $basicInfo = false === $basicInfo ? null : $basicInfo;

        // Cache for one hour and return.
        return $this->setCache($cacheKey, $basicInfo, 'PT1H');
    }

    /**
     * Get metadata about a project, including the 'dbName', 'url' and 'lang'
     *
     * @param string $projectUrl The project's URL.
     * @return array|null With 'dbName', 'url', 'lang', 'general' and 'namespaces' keys.
     *   'general' contains: 'wikiName', 'articlePath', 'scriptPath', 'script',
     *   'timezone', and 'timezoneOffset'; 'namespaces' contains all namespace
     *   names, keyed by their IDs. If this function returns null, the API call
     *   failed.
     */
    public function getMetadata(string $projectUrl): ?array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), "project_metadata");
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        try {
            $res = json_decode($this->guzzle->request('GET', $projectUrl.$this->getApiPath(), [
                'query' => [
                    'action' => 'query',
                    'meta' => 'siteinfo',
                    'siprop' => 'general|namespaces|autocreatetempuser',
                    'format' => 'json',
                    'formatversion' => '2',
                ],
            ])->getBody()->getContents(), true);
        } catch (Exception $e) {
            return null;
        }

        $metadata = [
            'general' => [],
            'namespaces' => [],
            'tempAccountPatterns' => $res['query']['autocreatetempuser']['matchPatterns'] ?? null,
        ];

        if (isset($res['query']['general'])) {
            $info = $res['query']['general'];

            $metadata['dbName'] = $info['wikiid'];
            $metadata['url'] = $info['server'];
            $metadata['lang'] = $info['lang'];

            $metadata['general'] = [
                'wikiName' => $info['sitename'],
                'articlePath' => $info['articlepath'],
                'scriptPath' => $info['scriptpath'],
                'script' => $info['script'],
                'timezone' => $info['timezone'],
                'timeOffset' => $info['timeoffset'],
                'mainpage' => $info['mainpage'],
            ];
        }

        $this->setNamespaces($res, $metadata);

        // Cache for one hour and return.
        return $this->setCache($cacheKey, $metadata, 'PT1H');
    }

    /**
     * Set the namespaces on the given $metadata.
     * @param array $res As produced by meta=siteinfo API.
     * @param array &$metadata The metadata array to modify.
     */
    private function setNamespaces(array $res, array &$metadata): void
    {
        if (!isset($res['query']['namespaces'])) {
            return;
        }

        foreach ($res['query']['namespaces'] as $namespace) {
            if ($namespace['id'] < 0) {
                continue;
            }

            if (isset($namespace['name'])) {
                $name = $namespace['name'];
            } elseif (isset($namespace['*'])) {
                $name = $namespace['*'];
            } else {
                continue;
            }

            $metadata['namespaces'][$namespace['id']] = $name;
        }
    }

    /**
     * Get a list of projects that have opted in to having all their users' restricted statistics available to anyone.
     * @return string[]
     */
    public function optedIn(): array
    {
        return $this->optedIn;
    }

    /**
     * The path to api.php.
     * @return string
     */
    public function getApiPath(): string
    {
        return $this->apiPath;
    }

    /**
     * Check to see if a page exists on this project and has some content.
     * @param Project $project The project.
     * @param int $namespaceId The page namespace ID.
     * @param string $pageTitle The page title, without namespace.
     * @return bool
     */
    public function pageHasContent(Project $project, int $namespaceId, string $pageTitle): bool
    {
        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $query = "SELECT page_id "
             . " FROM $pageTable "
             . " WHERE page_namespace = :ns AND page_title = :title AND page_len > 0 "
             . " LIMIT 1";
        $params = [
            'ns' => $namespaceId,
            'title' => str_replace(' ', '_', $pageTitle),
        ];
        $pages = $this->executeProjectsQuery($project, $query, $params)
            ->fetchAllAssociative();
        return count($pages) > 0;
    }

    /**
     * Get a list of the extensions installed on the wiki.
     * @param Project $project
     * @return string[]
     */
    public function getInstalledExtensions(Project $project): array
    {
        $res = json_decode($this->guzzle->request('GET', $project->getApiUrl(), ['query' => [
            'action' => 'query',
            'meta' => 'siteinfo',
            'siprop' => 'extensions',
            'format' => 'json',
        ]])->getBody()->getContents(), true);

        $extensions = $res['query']['extensions'] ?? [];
        return array_map(function ($extension) {
            return $extension['name'];
        }, $extensions);
    }

    /**
     * Get a list of users who are in one of the given user groups.
     * @param Project $project
     * @param string[] $groups List of user groups to look for.
     * @param string[] $globalGroups List of global groups to look for.
     * @return string[] with keys 'user_name' and 'ug_group'
     */
    public function getUsersInGroups(Project $project, array $groups = [], array $globalGroups = []): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'project_useringroups');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $userTable = $project->getTableName('user');
        $userGroupsTable = $project->getTableName('user_groups');

        $sql = "SELECT user_name, ug_group AS user_group
                FROM $userTable
                JOIN $userGroupsTable ON ug_user = user_id
                WHERE ug_group IN (?)
                GROUP BY user_name, ug_group";
        $users = $this->getProjectsConnection($project)
            ->executeQuery($sql, [$groups], [Connection::PARAM_STR_ARRAY])
            ->fetchAllAssociative();

        if (count($globalGroups) > 0 && $this->isWMF) {
            $sql = "SELECT gu_name AS user_name, gug_group AS user_group
                    FROM centralauth_p.global_user_groups
                    JOIN centralauth_p.globaluser ON gug_user = gu_id
                    WHERE gug_group IN (?)
                    GROUP BY user_name, user_group";
            $globalUsers = $this->getProjectsConnection('centralauth')
                ->executeQuery($sql, [$globalGroups], [Connection::PARAM_STR_ARRAY])
                ->fetchAllAssociative();

            $users = array_merge($users, $globalUsers);
        }

        // Cache for 12 hours and return.
        return $this->setCache($cacheKey, $users, 'PT12H');
    }
}
