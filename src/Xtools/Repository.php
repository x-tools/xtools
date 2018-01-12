<?php
/**
 * This file contains only the Repository class.
 */

namespace Xtools;

use Doctrine\DBAL\Connection;
use Mediawiki\Api\MediawikiApi;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Stopwatch\Stopwatch;
use GuzzleHttp\Promise\Promise;
use DateInterval;

/**
 * A repository is responsible for retrieving data from wherever it lives (databases, APIs,
 * filesystems, etc.)
 */
abstract class Repository
{

    /** @var Container The application's DI container. */
    protected $container;

    /** @var Connection The database connection to the meta database. */
    private $metaConnection;

    /** @var Connection The database connection to the projects' databases. */
    private $projectsConnection;

    /** @var Connection The database connection to other tools' databases.  */
    private $toolsConnection;

    /** @var GuzzleHttp\Client $apiConnection Connection to XTools API. */
    private $apiConnection;

    /** @var CacheItemPoolInterface The cache. */
    protected $cache;

    /** @var LoggerInterface The log. */
    protected $log;

    /** @var Stopwatch The stopwatch for time profiling. */
    protected $stopwatch;

    /**
     * Create a new Repository with nothing but a null-logger.
     */
    public function __construct()
    {
        $this->log = new NullLogger();
    }

    /**
     * Set the DI container.
     * @param Container $container
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
        $this->cache = $container->get('cache.app');
        $this->log = $container->get('logger');
        $this->stopwatch = $container->get('debug.stopwatch');
    }

    /**
     * Get the NullLogger instance.
     * @return NullLogger
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Get the database connection for the 'meta' database.
     * @return Connection
     * @codeCoverageIgnore
     */
    protected function getMetaConnection()
    {
        if (!$this->metaConnection instanceof Connection) {
            $this->metaConnection = $this->container
                ->get('doctrine')
                ->getManager('meta')
                ->getConnection();
        }
        return $this->metaConnection;
    }

    /**
     * Get the database connection for the 'projects' database.
     * @return Connection
     * @codeCoverageIgnore
     */
    protected function getProjectsConnection()
    {
        if (!$this->projectsConnection instanceof Connection) {
            $this->projectsConnection = $this->container
                ->get('doctrine')
                ->getManager('replicas')
                ->getConnection();
        }
        return $this->projectsConnection;
    }

    /**
     * Get the database connection for the 'tools' database
     * (the one that other tools store data in).
     * @return Connection
     * @codeCoverageIgnore
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
     *
     * @param Project $project
     * @return MediawikiApi
     */
    public function getMediawikiApi(Project $project)
    {
        $apiPath = $this->container->getParameter('api_path');
        if ($apiPath) {
            $api = MediawikiApi::newFromApiEndpoint($project->getUrl().$apiPath);
        } else {
            $api = MediawikiApi::newFromPage($project->getUrl());
        }
        return $api;
    }

    /**
     * Is XTools connecting to MMF Labs?
     * @return boolean
     * @codeCoverageIgnore
     */
    public function isLabs()
    {
        return (bool)$this->container->getParameter('app.is_labs');
    }

    /**
     * Make a request to the XTools API, optionally doing so asynchronously via Guzzle.
     * @param string $endpoint Relative path to endpoint with relevant query parameters.
     * @param bool $async Set to true to asynchronously query and return a promise.
     * @return GuzzleHttp\Psr7\Response|GuzzleHttp\Promise\Promise
     */
    public function queryXToolsApi($endpoint, $async = false)
    {
        if (!$this->apiConnection) {
            $this->apiConnection = $this->container->get('guzzle.client.xtools');
        }

        $key = $this->container->getParameter('secret');

        // Remove trailing slash if present.
        $basePath = trim($this->container->getParameter('app.base_path'), '/');

        $endpoint = "$basePath/api/$endpoint/$key";

        if ($async) {
            return $this->apiConnection->getAsync($endpoint);
        } else {
            return $this->apiConnection->get($endpoint);
        }
    }

    /**
     * Normalize and quote a table name for use in SQL.
     *
     * @param string $databaseName
     * @param string $tableName
     * @param string|null $tableExtension Optional table extension, which will only get used if we're on labs.
     * @return string Fully-qualified and quoted table name.
     */
    public function getTableName($databaseName, $tableName, $tableExtension = null)
    {
        $mapped = false;

        // This is a workaround for a one-to-many mapping
        // as required by Labs. We combine $tableName with
        // $tableExtension in order to generate the new table name
        if ($this->isLabs() && $tableExtension !== null) {
            $mapped = true;
            $tableName = $tableName . '_' . $tableExtension;
        } elseif ($this->container->hasParameter("app.table.$tableName")) {
            // Use the table specified in the table mapping configuration, if present.
            $mapped = true;
            $tableName = $this->container->getParameter("app.table.$tableName");
        }

        // For 'revision' and 'logging' tables (actually views) on Labs, use the indexed versions
        // (that have some rows hidden, e.g. for revdeleted users).
        // This is a safeguard in case table mapping isn't properly set up.
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

    /**
     * Get a unique cache key for the given list of arguments. Assuming each argument of
     * your function should be accounted for, you can pass in them all with func_get_args:
     *   $this->getCacheKey(func_get_args(), 'unique key for function');
     * Arugments that are a model should implement their own getCacheKey() that returns
     * a unique identifier for an instance of that model. See User::getCacheKey() for example.
     * @param array|mixed $args Array of arguments or a single argument.
     * @param string $key Unique key for this function. If omitted the function name itself
     *   is used, which is determined using `debug_backtrace`.
     * @return string
     */
    public function getCacheKey($args, $key = null)
    {
        if ($key === null) {
            $key = debug_backtrace()[1]['function'];
        }

        if (!is_array($args)) {
            $args = [$args];
        }

        // Start with base key.
        $cacheKey = $key;

        // Loop through and determine what values to use based on type of object.
        foreach ($args as $arg) {
            // Zero is an acceptable value.
            if ($arg === '' || $arg === null) {
                continue;
            }

            $cacheKey .= $this->getCacheKeyFromArg($arg);
        }

        return $cacheKey;
    }

    /**
     * Get a cache-friendly string given an argument.
     * @param  mixed $arg
     * @return string
     */
    private function getCacheKeyFromArg($arg)
    {
        if (method_exists($arg, 'getCacheKey')) {
            return '.'.$arg->getCacheKey();
        } elseif (is_array($arg)) {
            // Assumed to be an array of objects that can be parsed into a string.
            return '.'.join('', $arg);
        } else {
            // Assumed to be a string, number or boolean.
            return '.'.md5($arg);
        }
    }

    /**
     * Set the cache with given options.
     * @param string $cacheKey
     * @param mixed  $value
     * @param string $duration Valid DateInterval string.
     */
    public function setCache($cacheKey, $value, $duration = 'PT10M')
    {
        $cacheItem = $this->cache
            ->getItem($cacheKey)
            ->set($value)
            ->expiresAfter(new DateInterval($duration));
        $this->cache->save($cacheItem);
    }

    /**
     * Creates WHERE conditions with date range to be put in query.
     *
     * @param false|int $start
     * @param false|int $end
     * @param string $tableAlias Alias of table FOLLOWED BY DOT.
     * @param string $field
     * @return string
     */
    public function createDatesConditions($start, $end, $tableAlias = '', $field = 'rev_timestamp')
    {
        $datesConditions = '';
        if (false !== $start) {
            // Convert to YYYYMMDDHHMMSS. *who in the world thought of having time in BLOB of this format ;-;*
            $start = date('Ymd', $start) . '000000';
            $datesConditions .= " AND {$tableAlias}{$field} > '$start'";
        }
        if (false !== $end) {
            $end = date('Ymd', $end) . '000000';
            $datesConditions .= " AND {$tableAlias}{$field} < '$end'";
        }

        return $datesConditions;
    }
}
