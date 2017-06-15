<?php
/**
 * This file contains only the ProjectRepository class.
 */

namespace Xtools;

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use Symfony\Component\DependencyInjection\Container;

/**
 * This class provides data to the Project class.
 */
class ProjectRepository extends Repository
{

    /** @var array Project metadata. */
    protected $metadata;

    /** @var string[] Metadata if XTools is in single-wiki mode. */
    protected $singleMetadata;

    /** @var string[][] Metadata of all projects, populated by self::getAll(). */
    protected $projectsMetadata;

    /**
     * Convenience method to get a new Project object based on a given identification string.
     * @param string $projectIdent The domain name, database name, or URL of a project.
     * @param Container $container Symfony's container.
     * @return Project
     */
    public static function getProject($projectIdent, Container $container)
    {
        $project = new Project($projectIdent);
        $projectRepo = new ProjectRepository();
        $projectRepo->setContainer($container);
        if ($container->getParameter('app.single_wiki')) {
            $projectRepo->setSingleMetadata([
                'url' => $container->getParameter('wiki_url'),
                'dbname' => $container->getParameter('database_replica_name'),
            ]);
        }
        $project->setRepository($projectRepo);
        return $project;
    }

    /**
     * Get the XTools default project.
     * @param Container $container
     * @return Project
     */
    public static function getDefaultProject(Container $container)
    {
        $defaultProjectName = $container->getParameter('default_project');
        return self::getProject($defaultProjectName, $container);
    }

    /**
     * Get the global 'meta' project, which is either Meta (if this is Labs) or the default project.
     * @return Project
     */
    public function getGlobalProject()
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
     * @param $metadata
     * @throws \Exception
     */
    public function setSingleMetadata($metadata)
    {
        if (!array_key_exists('url', $metadata) || !array_key_exists('dbname', $metadata)) {
            $error = "Single-wiki metadata should contain 'url' and 'dbname' keys.";
            throw new \Exception($error);
        }
        $this->singleMetadata = array_intersect_key($metadata, ['url' => '', 'dbname' => '']);
    }

    /**
     * Get metadata about all projects.
     * @return string[] Each item has 'dbname' and 'url' keys.
     */
    public function getAll()
    {
        // Single wiki mode?
        if ($this->singleMetadata) {
            return [$this->getOne('')];
        }
        // Maybe we've already fetched it.
        if (is_array($this->projectsMetadata)) {
            return $this->projectsMetadata;
        }
        $wikiQuery = $this->getMetaConnection()->createQueryBuilder();
        $wikiQuery->select(['dbname', 'url'])->from('wiki');
        $projects = $wikiQuery->execute()->fetchAll();
        $this->projectsMetadata = [];
        foreach ($projects as $project) {
            $this->projectsMetadata[$project['url']] = $project;
            $this->projectsMetadata[$project['dbname']] = $project;
        }
        return $this->projectsMetadata;
    }

    /**
     * Get metadata about one project.
     * @param string $project A project URL, domain name, or database name.
     * @return string[] With 'dbname' and 'url' keys.
     */
    public function getOne($project)
    {
        // For single-wiki setups, every project is the same.
        if ($this->singleMetadata) {
            return $this->singleMetadata;
        }

        // Maybe we've already fetched it (if we're requesting via a domain).
        if (isset($this->projectsMetadata[$project])) {
            return $this->projectsMetadata[$project];
        }
        if (isset($this->projectsMetadata['https://'.$project])) {
            return $this->projectsMetadata['https://'.$project];
        }

        // For muli-wiki setups, first check the cache.
        $cacheKey = "project.$project";
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Otherwise, fetch the project's metadata from the meta.wiki table.
        $wikiQuery = $this->getMetaConnection()->createQueryBuilder();
        $wikiQuery->select(['dbname', 'url'])
            ->from('wiki')
            ->where($wikiQuery->expr()->eq('dbname', ':project'))
            // The meta database will have the project's URL stored as https://en.wikipedia.org
            // so we need to query for it accordingly, trying different variations the user
            // might have inputted.
            ->orwhere($wikiQuery->expr()->like('url', ':projectUrl'))
            ->orwhere($wikiQuery->expr()
                ->like('url', ':projectUrl2'))
            ->setParameter('project', $project)
            ->setParameter('projectUrl', "https://$project")
            ->setParameter('projectUrl2', "https://$project.org");
        $wikiStatement = $wikiQuery->execute();

        // Fetch and cache the wiki data.
        $projectMetadata = $wikiStatement->fetch();
        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set($projectMetadata)
            ->expiresAfter(new \DateInterval('PT1H'));
        $this->cache->save($cacheItem);

        return $projectMetadata;
    }

    /**
     * Get metadata about a project.
     *
     * @param string $projectUrl The project's URL.
     * @return array With 'general' and 'namespaces' keys: the former contains 'wikiName',
     * 'wikiId', 'url', 'lang', 'articlePath', 'scriptPath', 'script', 'timezone', and
     * 'timezoneOffset'; the latter contains all namespace names, keyed by their IDs.
     */
    public function getMetadata($projectUrl)
    {
        if ($this->metadata) {
            return $this->metadata;
        }

        $api = MediawikiApi::newFromPage($projectUrl);

        $params = ['meta' => 'siteinfo', 'siprop' => 'general|namespaces'];
        $query = new SimpleRequest('query', $params);

        $this->metadata = [
            'general' => [],
            'namespaces' => [],
        ];

        $res = $api->getRequest($query);

        if (isset($res['query']['general'])) {
            $info = $res['query']['general'];
            $this->metadata['general'] = [
                'wikiName' => $info['sitename'],
                'wikiId' => $info['wikiid'],
                'url' => $info['server'],
                'lang' => $info['lang'],
                'articlePath' => $info['articlepath'],
                'scriptPath' => $info['scriptpath'],
                'script' => $info['script'],
                'timezone' => $info['timezone'],
                'timeOffset' => $info['timeoffset'],
            ];

//            if ($this->container->getParameter('app.is_labs') &&
//                substr($result['general']['dbName'], -2) != '_p'
//            ) {
//                $result['general']['dbName'] .= '_p';
//            }
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

                // FIXME: Figure out a way to i18n-ize this
                if ($name === '') {
                    $name = 'Article';
                }

                $this->metadata['namespaces'][$namespace['id']] = $name;
            }
        }

        return $this->metadata;
    }

    /**
     * Get a list of projects that have opted in to having all their users' restricted statistics
     * available to anyone.
     *
     * @return string[]
     */
    public function optedIn()
    {
        $optedIn = $this->container->getParameter('opted_in');
        return $optedIn;
    }

    /**
     * The path to api.php
     *
     * @return string
     */
    public function getApiPath()
    {
        return $this->container->getParameter('api_path');
    }

    /**
     * Get a page from the given Project.
     * @param Project $project The project.
     * @param string $pageName The name of the page.
     * @return Page
     */
    public function getPage(Project $project, $pageName)
    {
        $pageRepo = new PagesRepository();
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
    public function pageHasContent(Project $project, $namespaceId, $pageTitle)
    {
        $conn = $this->getProjectsConnection();
        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $query = "SELECT page_id "
             . " FROM $pageTable "
             . " WHERE page_namespace = :ns AND page_title = :title AND page_len > 0 "
             . " LIMIT 1";
        $params = [
            'ns' => $namespaceId,
            'title' => $pageTitle,
        ];
        $pages = $conn->executeQuery($query, $params)->fetchAll();
        return count($pages) > 0;
    }
}
