<?php

namespace Xtools;

use Doctrine\DBAL\Connection;
use Mediawiki\Api\MediawikiApi;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;

/**
 * A repository is responsible for retrieving data from wherever it lives (databases, APIs,
 * filesystems, etc.)
 */
abstract class Repository
{

    /** @var Container */
    protected $container;

    /** @var Connection */
    private $metaConnection;

    /** @var Connection */
    private $projectsConnection;

    /** @var Connection */
    private $toolsConnection;

    /** @var CacheItemPoolInterface */
    protected $cache;

    /** @var LoggerInterface */
    protected $log;

    /**
     * @param Container $container
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
        $this->cache = $container->get('cache.app');
        $this->log = $container->get('logger');
    }

    /**
     * Get the database connection for the 'meta' database.
     * @return Connection
     */
    protected function getMetaConnection()
    {
        if (!$this->metaConnection instanceof Connection) {
            $this->metaConnection = $this->container
                ->get('doctrine')
                ->getManager("meta")
                ->getConnection();
        }
        return $this->metaConnection;
    }

    /**
     * Get the database connection for the 'projects' database.
     * @return Connection
     */
    protected function getProjectsConnection()
    {
        if (!$this->projectsConnection instanceof Connection) {
            $this->projectsConnection = $this->container
                ->get('doctrine')
                ->getManager("replicas")
                ->getConnection();
        }
        return $this->projectsConnection;
    }

    /**
     * Get the database connection for the 'tools' database
     * (the one that other tools store data in).
     * @return Connection
     */
    protected function getToolsConnection()
    {
        if (!$this->toolsConnection instanceof Connection) {
            $this->toolsConnection = $this->container
                ->get('doctrine')
                ->getManager("toolsdb")
                ->getConnection();
        }
        return $this->toolsConnection;
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
     * Is XTools connecting to MMF Labs?
     * @return boolean
     */
    public function isLabs()
    {
        return (bool)$this->container->getParameter('app.is_labs');
    }

    /**
     * Normalize and quote a table name for use in SQL.
     *
     * @param string $databaseName
     * @param string $tableName
     * @return string Fully-qualified and quoted table name.
     */
    public function getTableName($databaseName, $tableName)
    {
        // Use the table specified in the table mapping configuration, if present.
        $mapped = false;
        if ($this->container->hasParameter("app.table.$tableName")) {
            $mapped = true;
            $tableName = $this->container->getParameter("app.table.$tableName");
        }

        // For 'revision' and 'logging' tables (actually views) on Labs, use the indexed versions
        // (that have some rows hidden, e.g. for revdeleted users).
        $isLoggingOrRevision = in_array($tableName, ['revision', 'logging', 'archive']);
        if (!$mapped && $isLoggingOrRevision && $this->isLabs()) {
            $tableName = $tableName."_userindex";
        }

        // Figure out database name.
        // Use class variable for the database name if not set via function parameter.
        if ($this->isLabs() && substr($databaseName, -2) != '_p') {
            // Append '_p' if this is labs.
            $databaseName .= '_p';
        }

        return "`$databaseName`.`$tableName`";
    }
}
