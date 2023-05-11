<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Exception\BadGatewayException;
use App\Model\Project;
use DateInterval;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * A repository is responsible for retrieving data from wherever it lives (databases, APIs, filesystems, etc.)
 */
abstract class Repository
{
    protected CacheItemPoolInterface $cache;
    protected Client $guzzle;
    protected LoggerInterface $logger;
    protected ManagerRegistry $managerRegistry;
    protected ParameterBagInterface $parameterBag;

    /** @var Connection The database connection to the meta database. */
    private Connection $metaConnection;

    /** @var Connection The database connection to other tools' databases.  */
    private Connection $toolsConnection;

    /** @var bool Whether this is configured as a WMF installation. */
    protected bool $isWMF;

    /** @var int */
    protected int $queryTimeout;

    /** @var string Prefix URL for where the dblists live. Will be followed by i.e. 's1.dblist' */
    public const DBLISTS_URL = 'https://noc.wikimedia.org/conf/dblists/';

    /**
     * Create a new Repository.
     * @param ManagerRegistry $managerRegistry
     * @param Client $guzzle
     * @param LoggerInterface $logger
     * @param ParameterBagInterface $parameterBag
     * @param bool $isWMF
     * @param int $queryTimeout
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        CacheItemPoolInterface $cache,
        Client $guzzle,
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
        bool $isWMF,
        int $queryTimeout
    ) {
        $this->managerRegistry = $managerRegistry;
        $this->cache = $cache;
        $this->guzzle = $guzzle;
        $this->logger = $logger;
        $this->parameterBag = $parameterBag;
        $this->isWMF = $isWMF;
        $this->queryTimeout = $queryTimeout;
    }

    /***************
     * CONNECTIONS *
     ***************/

    /**
     * Get the database connection for the 'meta' database.
     * @return Connection
     * @codeCoverageIgnore
     */
    protected function getMetaConnection(): Connection
    {
        if (!isset($this->metaConnection)) {
            $this->metaConnection = $this->getProjectsConnection('meta');
        }
        return $this->metaConnection;
    }

    /**
     * Get a database connection for the given database.
     * @param Project|string $project Project instance, database name (i.e. 'enwiki'), or slice (i.e. 's1').
     * @return Connection
     * @codeCoverageIgnore
     */
    protected function getProjectsConnection($project): Connection
    {
        if (is_string($project)) {
            if (1 === preg_match('/^s\d+$/', $project)) {
                $slice = $project;
            } else {
                // Assume database name. Remove _p if given.
                $db = str_replace('_p', '', $project);
                $slice = $this->getDbList()[$db];
            }
        } else {
            $slice = $this->getDbList()[$project->getDatabaseName()];
        }

        return $this->managerRegistry->getConnection('toolforge_'.$slice);
    }

    /**
     * Get the database connection for the 'tools' database (the one that other tools store data in).
     * @return Connection
     * @codeCoverageIgnore
     */
    protected function getToolsConnection(): Connection
    {
        if (!isset($this->toolsConnection)) {
            $this->toolsConnection = $this->managerRegistry->getConnection('toolsdb');
        }
        return $this->toolsConnection;
    }

    /**
     * Fetch and concatenate all the dblists into one array.
     * Based on ToolforgeBundle https://github.com/wikimedia/ToolforgeBundle/blob/master/Service/ReplicasClient.php
     * License: GPL 3.0 or later
     * @return string[] Keys are database names (i.e. 'enwiki'), values are the slices (i.e. 's1').
     * @codeCoverageIgnore
     */
    protected function getDbList(): array
    {
        $cacheKey = 'dblists';
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $dbList = [];
        $exists = true;
        $i = 0;

        while ($exists) {
            $i += 1;
            $response = $this->guzzle->request('GET', self::DBLISTS_URL."s$i.dblist", ['http_errors' => false]);
            $exists = in_array(
                $response->getStatusCode(),
                [Response::HTTP_OK, Response::HTTP_NOT_MODIFIED]
            ) && $i < 50; // Safeguard

            if (!$exists) {
                break;
            }

            $lines = explode("\n", $response->getBody()->getContents());
            foreach ($lines as $line) {
                $line = trim($line);
                if (1 !== preg_match('/^#/', $line) && '' !== $line) {
                    // Skip comments and blank lines.
                    $dbList[$line] = "s$i";
                }
            }
        }

        // Manually add the meta and centralauth databases.
        $dbList['meta'] = 's7';
        $dbList['centralauth'] = 's7';

        // Cache for one week.
        return $this->setCache($cacheKey, $dbList, 'P1W');
    }

    /*****************
     * QUERY HELPERS *
     *****************/

    /**
     * Make a request to the MediaWiki API.
     * @param Project $project
     * @param array $params
     * @return array
     * @throws BadGatewayException
     */
    public function executeApiRequest(Project $project, array $params): array
    {
        try {
            return json_decode($this->guzzle->request('GET', $project->getApiUrl(), [
                'query' => array_merge([
                    'action' => 'query',
                    'format' => 'json',
                ], $params),
            ])->getBody()->getContents(), true);
        } catch (ServerException|ConnectException $e) {
            throw new BadGatewayException('api-error-wikimedia', ['Wikimedia'], $e);
        }
    }

    /**
     * Normalize and quote a table name for use in SQL.
     * @param string $databaseName
     * @param string $tableName
     * @param string|null $tableExtension Optional table extension, which will only get used if we're on labs.
     *   If null, table extensions are added as defined in table_map.yml. If a blank string, no extension is added.
     * @return string Fully-qualified and quoted table name.
     */
    public function getTableName(string $databaseName, string $tableName, ?string $tableExtension = null): string
    {
        $mapped = false;

        // This is a workaround for a one-to-many mapping
        // as required by Labs. We combine $tableName with
        // $tableExtension in order to generate the new table name
        if ($this->isWMF && null !== $tableExtension) {
            $mapped = true;
            $tableName .=('' === $tableExtension ? '' : '_'.$tableExtension);
        } elseif ($this->parameterBag->has("app.table.$tableName")) {
            // Use the table specified in the table mapping configuration, if present.
            $mapped = true;
            $tableName = $this->parameterBag->get("app.table.$tableName");
        }

        // For 'revision' and 'logging' tables (actually views) on Labs, use the indexed versions
        // (that have some rows hidden, e.g. for revdeleted users).
        // This is a safeguard in case table mapping isn't properly set up.
        $isLoggingOrRevision = in_array($tableName, ['revision', 'logging', 'archive']);
        if (!$mapped && $isLoggingOrRevision && $this->isWMF) {
            $tableName .="_userindex";
        }

        // Figure out database name.
        // Use class variable for the database name if not set via function parameter.
        if ($this->isWMF && '_p' != substr($databaseName, -2)) {
            // Append '_p' if this is labs.
            $databaseName .= '_p';
        }

        return "`$databaseName`.`$tableName`";
    }

    /**
     * Get a unique cache key for the given list of arguments. Assuming each argument of
     * your function should be accounted for, you can pass in them all with func_get_args:
     *   $this->getCacheKey(func_get_args(), 'unique key for function');
     * Arguments that are a model should implement their own getCacheKey() that returns
     * a unique identifier for an instance of that model. See User::getCacheKey() for example.
     * @param array|mixed $args Array of arguments or a single argument.
     * @param string|null $key Unique key for this function. If omitted the function name itself
     *   is used, which is determined using `debug_backtrace`.
     * @return string
     */
    public function getCacheKey($args, ?string $key = null): string
    {
        if (null === $key) {
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
            if ('' === $arg || null === $arg) {
                continue;
            }

            $cacheKey .= $this->getCacheKeyFromArg($arg);
        }

        // Remove reserved characters.
        return preg_replace('/[{}()\/@:"]/', '', $cacheKey);
    }

    /**
     * Get a cache-friendly string given an argument.
     * @param mixed $arg
     * @return string
     */
    private function getCacheKeyFromArg($arg): string
    {
        if (is_object($arg) && method_exists($arg, 'getCacheKey')) {
            return '.'.$arg->getCacheKey();
        } elseif (is_array($arg)) {
            // Assumed to be an array of objects that can be parsed into a string.
            return '.'.md5(implode('', $arg));
        } else {
            // Assumed to be a string, number or boolean.
            return '.'.md5((string)$arg);
        }
    }

    /**
     * Set the cache with given options.
     * @param string $cacheKey
     * @param mixed $value
     * @param string $duration Valid DateInterval string.
     * @return mixed The given $value.
     */
    public function setCache(string $cacheKey, $value, string $duration = 'PT20M')
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
     * @param false|int $start Unix timestamp.
     * @param false|int $end Unix timestamp.
     * @param false|int $offset Unix timestamp. Used for pagination, will end up replacing $end.
     * @param string $tableAlias Alias of table FOLLOWED BY DOT.
     * @param string $field
     * @return string
     */
    public function getDateConditions(
        $start,
        $end,
        $offset = false,
        string $tableAlias = '',
        string $field = 'rev_timestamp'
    ) : string {
        $datesConditions = '';

        if (is_int($start)) {
            // Convert to YYYYMMDDHHMMSS.
            $start = date('Ymd', $start).'000000';
            $datesConditions .= " AND $tableAlias{$field} >= '$start'";
        }

        // When we're given an $offset, it basically replaces $end, except it's also a full timestamp.
        if (is_int($offset)) {
            $offset = date('YmdHis', $offset);
            $datesConditions .= " AND $tableAlias{$field} <= '$offset'";
        } elseif (is_int($end)) {
            $end = date('Ymd', $end) . '235959';
            $datesConditions .= " AND $tableAlias{$field} <= '$end'";
        }

        return $datesConditions;
    }

    /**
     * Execute a query using the projects connection, handling certain Exceptions.
     * @param Project|string $project Project instance, database name (i.e. 'enwiki'), or slice (i.e. 's1').
     * @param string $sql
     * @param array $params Parameters to bound to the prepared query.
     * @param int|null $timeout Maximum statement time in seconds. null will use the
     *   default specified by the APP_QUERY_TIMEOUT env variable.
     * @return ResultStatement
     * @throws DriverException
     * @codeCoverageIgnore
     */
    public function executeProjectsQuery(
        $project,
        string $sql,
        array $params = [],
        ?int $timeout = null
    ): ResultStatement {
        try {
            $timeout = $timeout ?? $this->queryTimeout;
            $sql = "SET STATEMENT max_statement_time = $timeout FOR\n".$sql;

            return $this->getProjectsConnection($project)->executeQuery($sql, $params);
        } catch (DriverException $e) {
            $this->handleDriverError($e, $timeout);
        }
    }

    /**
     * Execute a query using the projects connection, handling certain Exceptions.
     * @param QueryBuilder $qb
     * @param int|null $timeout Maximum statement time in seconds. null will use the
     *   default specified by the APP_QUERY_TIMEOUT env variable.
     * @return ResultStatement
     * @throws HttpException
     * @throws DriverException
     * @codeCoverageIgnore
     */
    public function executeQueryBuilder(QueryBuilder $qb, ?int $timeout = null): ResultStatement
    {
        try {
            $timeout = $timeout ?? $this->queryTimeout;
            $sql = "SET STATEMENT max_statement_time = $timeout FOR\n".$qb->getSQL();
            return $qb->getConnection()->executeQuery($sql, $qb->getParameters(), $qb->getParameterTypes());
        } catch (DriverException $e) {
            $this->handleDriverError($e, $timeout);
        }
    }

    /**
     * Special handling of some DriverExceptions, otherwise original Exception is thrown.
     * @param DriverException $e
     * @param int|null $timeout Timeout value, if applicable. This is passed to the i18n message.
     * @throws HttpException
     * @throws DriverException
     * @codeCoverageIgnore
     */
    private function handleDriverError(DriverException $e, ?int $timeout): void
    {
        // If no value was passed for the $timeout, it must be the default.
        if (null === $timeout) {
            $timeout = $this->queryTimeout;
        }

        if (1226 === $e->getErrorCode()) {
            throw new ServiceUnavailableHttpException(30, 'error-service-overload', null, 503);
        } elseif (in_array($e->getErrorCode(), [1969, 2006, 2013])) {
            // FIXME: Attempt to reestablish connection on 2006 error (MySQL server has gone away).
            throw new HttpException(504, 'error-query-timeout', null, [], $timeout);
        } else {
            throw $e;
        }
    }
}
