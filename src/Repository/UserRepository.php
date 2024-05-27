<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Model\Project;
use App\Model\User;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Wikimedia\IPUtils;

/**
 * This class provides data for the User class.
 * @codeCoverageIgnore
 */
class UserRepository extends Repository
{
    protected ProjectRepository $projectRepo;
    protected RequestStack $requestStack;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param CacheItemPoolInterface $cache
     * @param Client $guzzle
     * @param LoggerInterface $logger
     * @param ParameterBagInterface $parameterBag
     * @param bool $isWMF
     * @param int $queryTimeout
     * @param ProjectRepository $projectRepo
     * @param RequestStack $requestStack
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        CacheItemPoolInterface $cache,
        Client $guzzle,
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
        bool $isWMF,
        int $queryTimeout,
        ProjectRepository $projectRepo,
        RequestStack $requestStack
    ) {
        $this->projectRepo = $projectRepo;
        $this->requestStack = $requestStack;
        parent::__construct($managerRegistry, $cache, $guzzle, $logger, $parameterBag, $isWMF, $queryTimeout);
    }

    /**
     * Get the user's ID and registration date.
     * @param string $databaseName The database to query.
     * @param string $username The username to find.
     * @return array|false With keys 'userId' and regDate'. false if user not found.
     */
    public function getIdAndRegistration(string $databaseName, string $username)
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_id_reg');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $userTable = $this->getTableName($databaseName, 'user');
        $sql = "SELECT user_id AS userId, user_registration AS regDate
                FROM $userTable
                WHERE user_name = :username
                LIMIT 1";
        $resultQuery = $this->executeProjectsQuery($databaseName, $sql, ['username' => $username]);

        // Cache and return.
        return $this->setCache($cacheKey, $resultQuery->fetchAssociative());
    }

    /**
     * Get the user's actor ID.
     * @param string $databaseName
     * @param string $username
     * @return int|null
     */
    public function getActorId(string $databaseName, string $username): ?int
    {
        if (IPUtils::isValidRange($username)) {
            return null;
        }

        $cacheKey = $this->getCacheKey(func_get_args(), 'user_actor_id');
        if ($this->cache->hasItem($cacheKey)) {
            return (int)$this->cache->getItem($cacheKey)->get();
        }

        $actorTable = $this->getTableName($databaseName, 'actor');

        $sql = "SELECT actor_id
                FROM $actorTable
                WHERE actor_name = :username
                LIMIT 1";
        $resultQuery = $this->executeProjectsQuery($databaseName, $sql, ['username' => $username]);

        // Cache and return.
        return (int)$this->setCache($cacheKey, $resultQuery->fetchOne());
    }

    /**
     * Get the user's (system) edit count.
     * @param string $databaseName The database to query.
     * @param string $username The username to find.
     * @return int As returned by the database.
     */
    public function getEditCount(string $databaseName, string $username): int
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_edit_count');
        if ($this->cache->hasItem($cacheKey)) {
            return (int)$this->cache->getItem($cacheKey)->get();
        }

        $userTable = $this->getTableName($databaseName, 'user');
        $sql = "SELECT user_editcount FROM $userTable WHERE user_name = :username LIMIT 1";
        $resultQuery = $this->executeProjectsQuery($databaseName, $sql, ['username' => $username]);

        return (int)$this->setCache($cacheKey, $resultQuery->fetchColumn());
    }

    /**
     * Search the ipblocks table to see if the user is currently blocked and return the expiry if they are.
     * @param string $databaseName The database to query.
     * @param string $username The username of the user to search for.
     * @return bool|string Expiry of active block or false
     */
    public function getBlockExpiry(string $databaseName, string $username)
    {
        $ipblocksTable = $this->getTableName($databaseName, 'ipblocks', 'ipindex');
        $sql = "SELECT ipb_expiry
                FROM $ipblocksTable
                WHERE ipb_address = :username
                LIMIT 1";
        $resultQuery = $this->executeProjectsQuery($databaseName, $sql, ['username' => $username]);
        return $resultQuery->fetchOne();
    }

    /**
     * Get edit count within given timeframe and namespace.
     * @param Project $project
     * @param User $user
     * @param int|string $namespace Namespace ID or 'all' for all namespaces
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @return int
     */
    public function countEdits(Project $project, User $user, $namespace = 'all', $start = false, $end = false): int
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_editcount');
        if ($this->cache->hasItem($cacheKey)) {
            return (int)$this->cache->getItem($cacheKey)->get();
        }

        $revDateConditions = $this->getDateConditions($start, $end);
        [$pageJoin, $condNamespace] = $this->getPageAndNamespaceSql($project, $namespace);
        $revisionTable = $project->getTableName('revision');
        $params = [];

        if ($user->isAnon()) {
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
            $ipcTable = $project->getTableName('ip_changes');
            $sql = "SELECT COUNT(ipc_rev_id)
                    FROM $ipcTable
                    JOIN $revisionTable ON ipc_rev_id = rev_id
                    $pageJoin
                    WHERE ipc_hex BETWEEN :startIp AND :endIp
                    $condNamespace
                    $revDateConditions";
        } else {
            $sql = "SELECT COUNT(rev_id)
                FROM $revisionTable
                $pageJoin
                WHERE rev_actor = :actorId
                $condNamespace
                $revDateConditions";
        }

        $resultQuery = $this->executeQuery($sql, $project, $user, $namespace, $params);
        $result = (int)$resultQuery->fetchOne();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get information about the currently-logged in user.
     * @return array|object|null null if not logged in.
     */
    public function getXtoolsUserInfo()
    {
        return $this->requestStack->getSession()->get('logged_in_user');
    }

    /**
     * Number of edits which if exceeded, will require the user to log in.
     * @return int
     */
    public function numEditsRequiringLogin(): int
    {
        return (int)$this->parameterBag->get('app.num_edits_requiring_login');
    }

    /**
     * Maximum number of edits to process, based on configuration.
     * @return int
     */
    public function maxEdits(): int
    {
        return (int)$this->parameterBag->get('app.max_user_edits');
    }

    /**
     * Get SQL clauses for joining on `page` and restricting to a namespace.
     * @param Project $project
     * @param int|string $namespace Namespace ID or 'all' for all namespaces.
     * @return array [page join clause, page namespace clause]
     */
    protected function getPageAndNamespaceSql(Project $project, $namespace): array
    {
        if ('all' === $namespace) {
            return [null, null];
        }

        $pageTable = $project->getTableName('page');
        $pageJoin = "LEFT JOIN $pageTable ON rev_page = page_id";
        $condNamespace = 'AND page_namespace = :namespace';

        return [$pageJoin, $condNamespace];
    }

    /**
     * Get SQL fragments for filtering by user.
     * Used in self::getPagesCreatedInnerSql().
     * @param bool $dateFiltering Whether the query you're working with has date filtering.
     *   If false, a clause to check timestamp > 1 is added to force use of the timestamp index.
     * @return string[] Keys 'whereRev' and 'whereArc'.
     */
    public function getUserConditions(bool $dateFiltering = false): array
    {
        return [
            'whereRev' => " rev_actor = :actorId ".($dateFiltering ? '' : "AND rev_timestamp > 1 "),
            'whereArc' => " ar_actor = :actorId ".($dateFiltering ? '' : "AND ar_timestamp > 1 "),
        ];
    }

    /**
     * Prepare the given SQL, bind the given parameters, and execute the Doctrine Statement.
     * @param string $sql
     * @param Project $project
     * @param User $user
     * @param int|string|null $namespace Namespace ID, or 'all'/null for all namespaces.
     * @param array $extraParams Will get merged in the params array used for binding values.
     * @return ResultStatement
     */
    protected function executeQuery(
        string $sql,
        Project $project,
        User $user,
        $namespace = 'all',
        array $extraParams = []
    ): ResultStatement {
        $params = ['actorId' => $user->getActorId($project)];

        if ('all' !== $namespace) {
            $params['namespace'] = $namespace;
        }

        return $this->executeProjectsQuery($project, $sql, array_merge($params, $extraParams));
    }

    /**
     * Check if a user exists globally.
     * @param User $user
     * @return bool
     */
    public function existsGlobally(User $user): bool
    {
        if ($user->isAnon()) {
            return true;
        }

        return (bool)$this->executeProjectsQuery(
            'centralauth',
            'SELECT 1 FROM centralauth_p.globaluser WHERE gu_name = :username',
            ['username' => $user->getUsername()]
        )->fetchFirstColumn();
    }

    /**
     * Get a user's local user rights on the given Project.
     * @param Project $project
     * @param User $user
     * @return string[]
     */
    public function getUserRights(Project $project, User $user): array
    {
        if ($user->isAnon()) {
            return [];
        }

        $cacheKey = $this->getCacheKey(func_get_args(), 'user_rights');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $userGroupsTable = $project->getTableName('user_groups');
        $userTable = $project->getTableName('user');

        $sql = "SELECT ug_group
                FROM $userGroupsTable
                JOIN $userTable ON user_id = ug_user
                WHERE user_name = :username
                AND (ug_expiry IS NULL OR ug_expiry > CURRENT_TIMESTAMP)";

        $ret = $this->executeProjectsQuery($project, $sql, [
            'username' => $user->getUsername(),
        ])->fetchFirstColumn();

        // Cache and return.
        return $this->setCache($cacheKey, $ret);
    }

    /**
     * Get a user's global group membership (starting at XTools' default project if none is
     * provided). This requires the CentralAuth extension to be installed.
     * @link https://www.mediawiki.org/wiki/Extension:CentralAuth
     * @param string $username The username.
     * @param Project|null $project The project to query.
     * @return string[]
     */
    public function getGlobalUserRights(string $username, ?Project $project = null): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_global_groups');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Get the default project if not provided.
        if (!$project instanceof Project) {
            $project = $this->projectRepo->getDefaultProject();
        }

        $params = [
            'meta' => 'globaluserinfo',
            'guiuser' => $username,
            'guiprop' => 'groups',
        ];

        $res = $this->executeApiRequest($project, $params);
        $result = [];
        if (isset($res['batchcomplete']) && isset($res['query']['globaluserinfo']['groups'])) {
            $result = $res['query']['globaluserinfo']['groups'];
        }

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }
}
