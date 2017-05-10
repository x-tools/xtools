<?php

namespace Xtools;

use Doctrine\DBAL\Connection;
use Mediawiki\Api\MediawikiApi;
use Psr\Cache\CacheItemPoolInterface;

/**
 * A repository is responsible for retrieving data from wherever it lives (databases, APIs,
 * filesystems, etc.)
 */
abstract class Repository
{

    /** @var Connection */
    protected $metaConnection;

    /** @var Connection */
    protected $projectsConnection;

    /** @var Connection */
    protected $toolsConnection;

    /** @var CacheItemPoolInterface */
    private $cache;

    /**
     * Set the database connection for the 'meta' database.
     * @param Connection $connection
     */
    public function setMetaConnection(Connection $connection)
    {
        $this->metaConnection = $connection;
    }

    /**
     * Set the database connection for the 'projects' database.
     * @param Connection $connection
     */
    public function setProjectsConnection(Connection $connection)
    {
        $this->projectsConnection = $connection;
    }

    /**
     * Set the database connection for the 'tools' database.
     * @param Connection $connection
     */
    public function setToolsConnection(Connection $connection)
    {
        $this->toolsConnection = $connection;
    }

    /**
     * Get the API object for the given project.
     * @param Project $project
     * @return MediawikiApi
     */
    protected function getMediawikiApi(Project $project)
    {
        // @TODO use newFromApiEndpoint instead.
        $api = MediawikiApi::newFromPage($project->getUrl());
        return $api;
    }

    /**
     * Normalize and quote a table name.
     *
     * @param string $databaseName
     * @param string $tableName
     * @return string Fully-qualified and quoted table name.
     */
    public function getTableName($databaseName, $tableName)
    {
        // @TODO Import from LabsHelper.
        return "`$databaseName`.`$tableName`";
    }

    /**
     * Set the cache for this repository.
     *
     * @param CacheItemPoolInterface $pool The cache pool.
     */
    public function setCache(CacheItemPoolInterface $pool)
    {
        $this->cache = $pool;
    }
}
