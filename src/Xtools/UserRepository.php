<?php
/**
 * This file contains only the UserRepository class.
 */

namespace Xtools;

use Mediawiki\Api\SimpleRequest;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * This class provides data for the User class.
 * @codeCoverageIgnore
 */
class UserRepository extends Repository
{
    /**
     * Convenience method to get a new User object.
     * @param string $username The username.
     * @param Container $container The DI container.
     * @return User
     */
    public static function getUser($username, Container $container)
    {
        $user = new User($username);
        $userRepo = new UserRepository();
        $userRepo->setContainer($container);
        $user->setRepository($userRepo);
        return $user;
    }

    /**
     * Get the user's ID and registration date.
     * @param string $databaseName The database to query.
     * @param string $username The username to find.
     * @return array [int ID, string|null registration date].
     */
    public function getIdAndRegistration($databaseName, $username)
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
        $resultQuery = $this->executeProjectsQuery($sql, ['username' => $username]);

        // Cache and return.
        return $this->setCache($cacheKey, $resultQuery->fetch());
    }

    /**
     * Get the user's registration date.
     * @param string $databaseName The database to query.
     * @param string $username The username to find.
     * @return string|null As returned by the database.
     */
    public function getRegistrationDate($databaseName, $username)
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_registration');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $userTable = $this->getTableName($databaseName, 'user');
        $sql = "SELECT user_registration FROM $userTable WHERE user_name = :username LIMIT 1";
        $resultQuery = $this->executeProjectsQuery($sql, ['username' => $username]);
        $registrationDate = $resultQuery->fetchColumn();

        // Cache and return.
        return $this->setCache($cacheKey, $registrationDate);
    }

    /**
     * Get the user's (system) edit count.
     * @param string $databaseName The database to query.
     * @param string $username The username to find.
     * @return int|null As returned by the database.
     */
    public function getEditCount($databaseName, $username)
    {
        // Quick cache of edit count, valid on for the same request.
        static $editCount = null;
        if ($editCount !== null) {
            return $editCount;
        }

        $userTable = $this->getTableName($databaseName, 'user');
        $sql = "SELECT user_editcount FROM $userTable WHERE user_name = :username LIMIT 1";
        $resultQuery = $this->executeProjectsQuery($sql, ['username' => $username]);

        $editCount = $resultQuery->fetchColumn();
        return $editCount;
    }

    /**
     * Search the ipblocks table to see if the user is currently blocked
     * and return the expiry if they are.
     * @param $databaseName The database to query.
     * @param $username The username of the user to search for.
     * @return bool|string Expiry of active block or false
     */
    public function getBlockExpiry($databaseName, $username)
    {
        $ipblocksTable = $this->getTableName($databaseName, 'ipblocks', 'ipindex');
        $sql = "SELECT ipb_expiry
                FROM $ipblocksTable
                WHERE ipb_address = :username
                LIMIT 1";
        $resultQuery = $this->executeProjectsQuery($sql, ['username' => $username]);
        return $resultQuery->fetchColumn();
    }

    /**
     * Get edit count within given timeframe and namespace.
     * @param Project $project
     * @param User $user
     * @param int|string $namespace Namespace ID or 'all' for all namespaces
     * @param string $start Start date in a format accepted by strtotime()
     * @param string $end End date in a format accepted by strtotime()
     * @return int
     */
    public function countEdits(Project $project, User $user, $namespace = 'all', $start = '', $end = '')
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_editcount');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        list($condBegin, $condEnd) = $this->getRevTimestampConditions($start, $end);
        list($pageJoin, $condNamespace) = $this->getPageAndNamespaceSql($project, $namespace);
        $revisionTable = $project->getTableName('revision');

        $sql = "SELECT COUNT(rev_id)
                FROM $revisionTable
                $pageJoin
                WHERE rev_user_text = :username
                $condNamespace
                $condBegin
                $condEnd";

        $resultQuery = $this->executeQuery($sql, $user, $namespace, $start, $end);
        $result = $resultQuery->fetchColumn();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get information about the currently-logged in user.
     * @return array
     */
    public function getXtoolsUserInfo()
    {
        /** @var Session $session */
        $session = $this->container->get('session');
        return $session->get('logged_in_user');
    }

    /**
     * Maximum number of edits to process, based on configuration.
     * @return int
     */
    public function maxEdits()
    {
        return $this->container->getParameter('app.max_user_edits');
    }

    /**
     * Get SQL clauses for joining on `page` and restricting to a namespace.
     * @param Project $project
     * @param int|string $namespace Namespace ID or 'all' for all namespaces.
     * @return array [page join clause, page namespace clause]
     */
    protected function getPageAndNamespaceSql(Project $project, $namespace)
    {
        if ($namespace === 'all') {
            return [null, null];
        }

        $pageTable = $project->getTableName('page');
        $pageJoin = $namespace !== 'all' ? "LEFT JOIN $pageTable ON rev_page = page_id" : null;
        $condNamespace = 'AND page_namespace = :namespace';

        return [$pageJoin, $condNamespace];
    }

    /**
     * Get SQL clauses for rev_timestamp, based on whether values for the given start and end parameters exist.
     * @param string $start
     * @param string $end
     * @param string $tableAlias Alias of table FOLLOWED BY DOT.
     * @todo FIXME: merge with Repository::getDateConditions
     * @return string[] Clauses for start and end timestamps.
     */
    protected function getRevTimestampConditions($start, $end, $tableAlias = '')
    {
        $condBegin = '';
        $condEnd = '';

        if (!empty($start)) {
            $condBegin = "AND {$tableAlias}rev_timestamp >= :start ";
        }
        if (!empty($end)) {
            $condEnd = "AND {$tableAlias}rev_timestamp <= :end ";
        }

        return [$condBegin, $condEnd];
    }

    /**
     * Prepare the given SQL, bind the given parameters, and execute the Doctrine Statement.
     * @param string $sql
     * @param User $user
     * @param string $namespace
     * @param string $start
     * @param string $end
     * @return \Doctrine\DBAL\Statement
     */
    protected function executeQuery($sql, User $user, $namespace = 'all', $start = '', $end = '')
    {
        $params = [
            'username' => $user->getUsername(),
        ];

        if (!empty($start)) {
            $params['start'] = date('Ymd000000', strtotime($start));
        }
        if (!empty($end)) {
            $params['end'] = date('Ymd235959', strtotime($end));
        }
        if ($namespace !== 'all') {
            $params['namespace'] = $namespace;
        }

        return $this->executeProjectsQuery($sql, $params);
    }

    /**
     * Get a user's local user rights on the given Project.
     * @param Project $project
     * @param User $user
     * @return string[]
     */
    public function getUserRights(Project $project, User $user)
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_rights');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $userGroupsTable = $project->getTableName('user_groups');
        $userTable = $project->getTableName('user');

        $sql = "SELECT ug_group
                FROM $userGroupsTable
                JOIN $userTable ON user_id = ug_user
                WHERE user_name = :username";

        $ret = $this->executeProjectsQuery($sql, [
            'username' => $user->getUsername(),
        ])->fetchAll(\PDO::FETCH_COLUMN);

        // Cache and return.
        return $this->setCache($cacheKey, $ret);
    }

    /**
     * Get a user's global group membership (starting at XTools' default project if none is
     * provided). This requires the CentralAuth extension to be installed.
     * @link https://www.mediawiki.org/wiki/Extension:CentralAuth
     * @param string $username The username.
     * @param Project $project The project to query.
     * @return string[]
     */
    public function getGlobalUserRights($username, Project $project = null)
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_global_groups');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Get the default project if not provided.
        if (!$project instanceof Project) {
            $project = ProjectRepository::getDefaultProject($this->container);
        }

        // Create the API query.
        $api = $this->getMediawikiApi($project);
        $params = [
            'meta' => 'globaluserinfo',
            'guiuser' => $username,
            'guiprop' => 'groups'
        ];
        $query = new SimpleRequest('query', $params);

        // Get the result.
        $res = $api->getRequest($query);
        $result = [];
        if (isset($res['batchcomplete']) && isset($res['query']['globaluserinfo']['groups'])) {
            $result = $res['query']['globaluserinfo']['groups'];
        }

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }
}
