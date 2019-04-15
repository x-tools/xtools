<?php
/**
 * This file contains only the ProjectRepository class.
 */

declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Page;
use AppBundle\Model\Project;
use GuzzleHttp\Client;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This class provides data to the Project class.
 * @codeCoverageIgnore
 */
class ProjectRepository extends Repository
{
    /** @var array Project's 'dbName', 'url' and 'lang'. */
    protected $basicInfo;

    /** @var string[] Basic metadata if XTools is in single-wiki mode. */
    protected $singleBasicInfo;

    /** @var array Full Project metadata, including $basicInfo. */
    protected $metadata;

    /** @var string The cache key for the 'all project' metadata. */
    protected $cacheKeyAllProjects = 'allprojects';

    /**
     * Convenience method to get a new Project object based on a given identification string.
     * @param string $projectIdent The domain name, database name, or URL of a project.
     * @param ContainerInterface $container Symfony's container.
     * @return Project
     */
    public static function getProject(string $projectIdent, ContainerInterface $container): Project
    {
        $project = new Project($projectIdent);
        $projectRepo = new ProjectRepository();
        $projectRepo->setContainer($container);

        // The associated PageAssessmentsRepository also needs the container.
        $paRepo = new PageAssessmentsRepository();
        $paRepo->setContainer($container);
        $project->getPageAssessments()->setRepository($paRepo);

        if ($container->getParameter('app.single_wiki')) {
            $projectRepo->setSingleBasicInfo([
                'url' => $container->getParameter('wiki_url'),
                'dbName' => $container->getParameter('database_replica_name'),
            ]);
        }
        $project->setRepository($projectRepo);

        return $project;
    }

    /**
     * Get the XTools default project.
     * @param ContainerInterface $container
     * @return Project
     */
    public static function getDefaultProject(ContainerInterface $container): Project
    {
        $defaultProjectName = $container->getParameter('default_project');
        return self::getProject($defaultProjectName, $container);
    }

    /**
     * Get the global 'meta' project, which is either Meta (if this is Labs) or the default project.
     * @return Project
     */
    public function getGlobalProject(): Project
    {
        if ($this->isLabs()) {
            return self::getProject('metawiki', $this->container);
        } else {
            return self::getDefaultProject($this->container);
        }
    }

    /**
     * For single-wiki installations, you must manually set the wiki URL and database name
     * (because there's no meta.wiki database to query).
     * @param array $metadata
     * @throws \Exception
     */
    public function setSingleBasicInfo(array $metadata): void
    {
        if (!array_key_exists('url', $metadata) || !array_key_exists('dbName', $metadata)) {
            $error = "Single-wiki metadata should contain 'url', 'dbName' and 'lang' keys.";
            throw new \Exception($error);
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
        $this->log->debug(__METHOD__." Getting all projects' metadata");
        // Single wiki mode?
        if (!empty($this->singleBasicInfo)) {
            return [$this->getOne('')];
        }

        // Maybe we've already fetched it.
        if ($this->cache->hasItem($this->cacheKeyAllProjects)) {
            return $this->cache->getItem($this->cacheKeyAllProjects)->get();
        }

        if ($this->container->hasParameter("database_meta_table")) {
            $table = $this->container->getParameter("database_meta_table");
        } else {
            $table = "wiki";
        }

        // Otherwise, fetch all from the database.
        $wikiQuery = $this->getMetaConnection()->createQueryBuilder();
        $wikiQuery->select(['dbname AS dbName', 'url', 'lang'])->from($table);
        $projects = $this->executeQueryBuilder($wikiQuery)->fetchAll();
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
     * @return string[]|bool With 'dbName', 'url' and 'lang' keys; or false if not found.
     */
    public function getOne(string $project)
    {
        $this->log->debug(__METHOD__." Getting metadata about $project");
        // For single-wiki setups, every project is the same.
        if (!empty($this->singleBasicInfo)) {
            return $this->singleBasicInfo;
        }

        // For multi-wiki setups, first check the cache.
        // First the all-projects cache, then the individual one.
        if ($this->cache->hasItem($this->cacheKeyAllProjects)) {
            foreach ($this->cache->getItem($this->cacheKeyAllProjects)->get() as $projMetadata) {
                if ($projMetadata['dbName'] == "$project"
                    || $projMetadata['url'] == "$project"
                    || $projMetadata['url'] == "https://$project"
                    || $projMetadata['url'] == "https://$project.org"
                    || $projMetadata['url'] == "https://www.$project") {
                    $this->log->debug(__METHOD__ . " Using cached data for $project");
                    return $projMetadata;
                }
            }
        }
        $cacheKey = $this->getCacheKey($project, 'project');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        if ($this->container->hasParameter("database_meta_table")) {
            $table = $this->container->getParameter("database_meta_table");
        } else {
            $table = "wiki";
        }

        // Otherwise, fetch the project's metadata from the meta.wiki table.
        $wikiQuery = $this->getMetaConnection()->createQueryBuilder();
        $wikiQuery->select(['dbname AS dbName', 'url', 'lang'])
            ->from($table)
            ->where($wikiQuery->expr()->eq('dbname', ':project'))
            // The meta database will have the project's URL stored as https://en.wikipedia.org
            // so we need to query for it accordingly, trying different variations the user
            // might have inputted.
            ->orWhere($wikiQuery->expr()->like('url', ':projectUrl'))
            ->orWhere($wikiQuery->expr()->like('url', ':projectUrl2'))
            ->orWhere($wikiQuery->expr()->like('url', ':projectUrl3'))
            ->orWhere($wikiQuery->expr()->like('url', ':projectUrl4'))
            ->setParameter('project', $project)
            ->setParameter('projectUrl', "https://$project")
            ->setParameter('projectUrl2', "https://$project.org")
            ->setParameter('projectUrl3', "https://www.$project")
            ->setParameter('projectUrl4', "https://www.$project.org");
        $wikiStatement = $this->executeQueryBuilder($wikiQuery);

        // Fetch and cache the wiki data.
        $basicInfo = $wikiStatement->fetch();

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
        // First try variable cache
        if (!empty($this->metadata)) {
            return $this->metadata;
        }

        // Redis cache
        $cacheKey = $this->getCacheKey(
            // Removed non-alphanumeric characters
            preg_replace("/[^A-Za-z0-9]/", '', $projectUrl),
            'project_metadata'
        );

        if ($this->cache->hasItem($cacheKey)) {
            $this->metadata = $this->cache->getItem($cacheKey)->get();
            return $this->metadata;
        }

        try {
            $api = MediawikiApi::newFromPage($projectUrl);
        } catch (\Exception $e) {
            return null;
        }

        $params = ['meta' => 'siteinfo', 'siprop' => 'general|namespaces'];
        $query = new SimpleRequest('query', $params);

        $this->metadata = [
            'general' => [],
            'namespaces' => [],
        ];

        // Even if general info could not be fetched,
        //   return dbName, url and lang if already known
        if (!empty($this->basicInfo)) {
            $this->metadata['dbName'] = $this->basicInfo['dbName'];
            $this->metadata['url'] = $this->basicInfo['url'];
            $this->metadata['lang'] = $this->basicInfo['lang'];
        }

        $res = $api->getRequest($query);

        if (isset($res['query']['general'])) {
            $info = $res['query']['general'];

            $this->metadata['dbName'] = $info['wikiid'];
            $this->metadata['url'] = $info['server'];
            $this->metadata['lang'] = $info['lang'];

            $this->metadata['general'] = [
                'wikiName' => $info['sitename'],
                'articlePath' => $info['articlepath'],
                'scriptPath' => $info['scriptpath'],
                'script' => $info['script'],
                'timezone' => $info['timezone'],
                'timeOffset' => $info['timeoffset'],
                'mainpage' => $info['mainpage'],
            ];
        }

        if (isset($res['query']['namespaces'])) {
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

                $this->metadata['namespaces'][$namespace['id']] = $name;
            }
        }

        // Cache for one hour and return.
        return $this->setCache($cacheKey, $this->metadata, 'PT1H');
    }

    /**
     * Get a list of projects that have opted in to having all their users' restricted statistics available to anyone.
     * @return string[]
     */
    public function optedIn(): array
    {
        $optedIn = $this->container->getParameter('opted_in');
        // In case there's just one given.
        if (!is_array($optedIn)) {
            $optedIn = [ $optedIn ];
        }
        return $optedIn;
    }

    /**
     * The path to api.php.
     * @return string
     */
    public function getApiPath(): string
    {
        return $this->container->getParameter('api_path');
    }

    /**
     * Get a page from the given Project.
     * @param Project $project The project.
     * @param string $pageName The name of the page.
     * @return Page
     */
    public function getPage(Project $project, string $pageName): Page
    {
        $pageRepo = new PageRepository();
        $pageRepo->setContainer($this->container);
        $page = new Page($project, $pageName);
        $page->setRepository($pageRepo);
        return $page;
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
        $pages = $this->executeProjectsQuery($query, $params)->fetchAll();
        return count($pages) > 0;
    }

    /**
     * Get a list of the extensions installed on the wiki.
     * @param Project $project
     * @return string[]
     */
    public function getInstalledExtensions(Project $project): array
    {
        $client = new Client();

        $res = json_decode($client->request('GET', $project->getApiUrl(), ['query' => [
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

        $rqb = $this->getProjectsConnection()->createQueryBuilder();
        $rqb->select(['user_name', 'ug_group AS user_group'])
            ->from($userTable)
            ->join($userTable, $userGroupsTable, 'user_groups', 'ug_user = user_id')
            ->where($rqb->expr()->in('ug_group', ':groups'))
            ->groupBy('user_name, user_group')
            ->setParameter(
                'groups',
                $groups,
                \Doctrine\DBAL\Connection::PARAM_STR_ARRAY
            );

        $users = $this->executeQueryBuilder($rqb)->fetchAll();

        if (count($globalGroups) > 0 && $this->isLabs()) {
            $rqb = $this->getProjectsConnection()->createQueryBuilder();
            $rqb->select(['gu_name AS user_name', 'gug_group AS user_group'])
                ->from('centralauth_p.global_user_groups', 'gug')
                ->join('gug', 'centralauth_p.globaluser', null, 'gug_user = gu_id')
                ->where($rqb->expr()->in('gug_group', ':globalGroups'))
                ->groupBy('user_name', 'user_group')
                ->setParameter(
                    'globalGroups',
                    $globalGroups,
                    \Doctrine\DBAL\Connection::PARAM_STR_ARRAY
                );
            $ret = $this->executeQueryBuilder($rqb)->fetchAll();
            $users = array_merge($users, $ret);
        }

        // Cache for 12 hours and return.
        return $this->setCache($cacheKey, $users, 'PT12H');
    }
}
