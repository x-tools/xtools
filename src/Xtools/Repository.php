<?php
/**
 * This file contains only the Repository class.
 */

namespace Xtools;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Query\QueryBuilder;
use Mediawiki\Api\MediawikiApi;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
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
     * Is XTools connecting to MMF Labs?
     * @return boolean
     * @codeCoverageIgnore
     */
    public function isLabs()
    {
        return (bool)$this->container->getParameter('app.is_labs');
    }

    /**
     * Get various metadata about the current tool being used, which will
     * be used in logging for diagnosting any issues.
     * @return array
     *
     * There is no request stack in the tests.
     * @codeCoverageIgnore
     */
    protected function getCurrentRequestMetadata()
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $requestTime = microtime(true) - $request->server->get('REQUEST_TIME_FLOAT');

        return [
            'requestTime' => round($requestTime, 2),
            'path' => $request->getPathInfo(),
        ];
    }

    /***************
     * CONNECTIONS *
     ***************/

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
                ->getManager('toolsdb')
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

    /*****************
     * QUERY HELPERS *
     *****************/

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
            $tableName = $tableName.'_'.$tableExtension;
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

        // Remove reserved characters.
        return preg_replace('/[{}()\/\@\:"]/', '', $cacheKey);
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
     * @return mixed The given $value.
     */
    public function setCache($cacheKey, $value, $duration = 'PT10M')
    {
        $cacheItem = $this->cache
            ->getItem($cacheKey)
            ->set($value)
            ->expiresAfter(new DateInterval($duration));
        $this->cache->save($cacheItem);
        return $value;
    }

    /********************************
     * DATABASE INTERACTION HELPERS *
     ********************************/

    /**
     * Creates WHERE conditions with date range to be put in query.
     *
     * @param false|int $start
     * @param false|int $end
     * @param string $tableAlias Alias of table FOLLOWED BY DOT.
     * @param string $field
     * @return string
     */
    public function getDateConditions($start, $end, $tableAlias = '', $field = 'rev_timestamp')
    {
        $datesConditions = '';
        if (false !== $start) {
            // Convert to YYYYMMDDHHMMSS. *who in the world thought of having time in BLOB of this format ;-;*
            $start = date('Ymd', $start).'000000';
            $datesConditions .= " AND {$tableAlias}{$field} > '$start'";
        }
        if (false !== $end) {
            $end = date('Ymd', $end).'235959';
            $datesConditions .= " AND {$tableAlias}{$field} < '$end'";
        }

        return $datesConditions;
    }

    /**
     * Execute a query using the projects connection, handling certain Exceptions.
     * @param string $sql
     * @param array $params Parameters to bound to the prepared query.
     * @param int|null $timeout Maximum statement time in seconds. null will use the
     *   default specified by the app.query_timeout config parameter.
     * @return \Doctrine\DBAL\Driver\Statement
     * @codeCoverageIgnore
     */
    public function executeProjectsQuery($sql, $params = [], $timeout = null)
    {
        try {
            $timeout = $timeout === null
                ? $this->container->getParameter('app.query_timeout')
                : $timeout;
            $sql = "SET STATEMENT max_statement_time = $timeout FOR\n".$sql;
            return $this->getProjectsConnection()->executeQuery($sql, $params);
        } catch (DriverException $e) {
            return $this->handleDriverError($e, $timeout);
        }
    }

    /**
     * Execute a query using the projects connection, handling certain Exceptions.
     * @param QueryBuilder $qb
     * @param int $timeout Maximum statement time in seconds. null will use the
     *   default specified by the app.query_timeout config parameter.
     * @return \Doctrine\DBAL\Driver\Statement
     * @codeCoverageIgnore
     */
    public function executeQueryBuilder(QueryBuilder $qb, $timeout = null)
    {
        try {
            $timeout = $timeout === null
                ? $this->container->getParameter('app.query_timeout')
                : $timeout;
            $sql = "SET STATEMENT max_statement_time = $timeout FOR\n".$qb->getSQL();
            return $qb->getConnection()->executeQuery($sql, $qb->getParameters());
        } catch (DriverException $e) {
            return $this->handleDriverError($e, $timeout);
        }
    }

    /**
     * Special handling of some DriverExceptions, otherwise original Exception is thrown.
     * @param DriverException $e
     * @param int $timeout Timeout value, if applicable. This is passed to the i18n message.
     * @throws ServiceUnavailableHttpException
     * @throws DriverException
     * @codeCoverageIgnore
     */
    private function handleDriverError(DriverException $e, $timeout)
    {
        // If no value was passed for the $timeout, it must be the default.
        if ($timeout === null) {
            $timeout = $this->container->getParameter('app.query_timeout');
        }

        if ($e->getErrorCode() === 1226) {
            $this->logErrorData('MAX CONNECTIONS');
            throw new ServiceUnavailableHttpException(30, 'error-service-overload', null, 503);
        } elseif ($e->getErrorCode() === 1969 || $e->getErrorCode() === 2013) {
            $this->logErrorData('QUERY TIMEOUT');
            throw new HttpException(504, 'error-query-timeout', null, [], $timeout);
        } else {
            throw $e;
        }
    }

    /**
     * Log error containing the given error code, along with the request path and request time.
     * @param string $error
     */
    private function logErrorData($error)
    {
        $metadata = $this->getCurrentRequestMetadata();
        $this->log->error(
            '>>> '.$metadata['path'].' ('.$error.' after '.$metadata['requestTime'].')'
        );
    }
}
