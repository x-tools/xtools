<?php
/**
 * This file contains only the UserRepository class.
 */

namespace Xtools;

use DateInterval;
use Mediawiki\Api\SimpleRequest;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * This class provides data for the User class.
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
     * Get the user's ID.
     * @param string $databaseName The database to query.
     * @param string $username The username to find.
     * @return int
     */
    public function getId($databaseName, $username)
    {
        // Use md5 to ensure the key does not contain reserved characters.
        $cacheKey = 'user_id.'.$databaseName.'.'.md5($username);
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $userTable = $this->getTableName($databaseName, 'user');
        $sql = "SELECT user_id FROM $userTable WHERE user_name = :username LIMIT 1";
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();
        $userId = (int)$resultQuery->fetchColumn();

        // Cache for 10 minutes.
        $cacheItem = $this->cache
            ->getItem($cacheKey)
            ->set($userId)
            ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);
        return $userId;
    }

    /**
     * Get group names of the given user.
     * @param Project $project The project.
     * @param string $username The username.
     * @return string[]
     */
    public function getGroups(Project $project, $username)
    {
        // Use md5 to ensure the key does not contain reserved characters.
        $cacheKey = 'usergroups.'.$project->getDatabaseName().'.'.md5($username);
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $this->stopwatch->start($cacheKey, 'XTools');
        $api = $this->getMediawikiApi($project);
        $params = [ "list"=>"users", "ususers"=>$username, "usprop"=>"groups" ];
        $query = new SimpleRequest('query', $params);
        $result = [];
        $res = $api->getRequest($query);
        if (isset($res["batchcomplete"]) && isset($res["query"]["users"][0]["groups"])) {
            $result = $res["query"]["users"][0]["groups"];
        }

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($result)
            ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);
        $this->stopwatch->stop($cacheKey);

        return $result;
    }

    /**
     * Get a user's global group membership (starting at XTools' default project if none is
     * provided). This requires the CentralAuth extension to be installed.
     * @link https://www.mediawiki.org/wiki/Extension:CentralAuth
     * @param string $username The username.
     * @param Project $project The project to query.
     * @return string[]
     */
    public function getGlobalGroups($username, Project $project = null)
    {
        // Get the default project if not provided.
        if (!$project instanceof Project) {
            $project = ProjectRepository::getDefaultProject($this->container);
        }

        // Create the API query.
        $api = $this->getMediawikiApi($project);
        $params = [ "meta"=>"globaluserinfo", "guiuser"=>$username, "guiprop"=>"groups" ];
        $query = new SimpleRequest('query', $params);

        // Get the result.
        $res = $api->getRequest($query);
        $result = [];
        if (isset($res["batchcomplete"]) && isset($res["query"]["globaluserinfo"]["groups"])) {
            $result = $res["query"]["globaluserinfo"]["groups"];
        }
        return $result;
    }

    /**
     * Search the ipblocks table to see if the user is currently blocked
     *   and return the expiry if they are
     * @param $databaseName The database to query.
     * @param $userid The ID of the user to search for.
     * @return bool|string Expiry of active block or false
     */
    public function getBlockExpiry($databaseName, $userid)
    {
        $ipblocksTable = $this->getTableName($databaseName, 'ipblocks');
        $sql = "SELECT ipb_expiry
                FROM $ipblocksTable
                WHERE ipb_user = :userid
                LIMIT 1";
        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->bindParam('userid', $userid);
        $resultQuery->execute();
        return $resultQuery->fetchColumn();
    }

    /**
     * Get pages created by a user
     * @param Project $project
     * @param User $user
     * @param string|int $namespace Namespace ID or 'all'
     * @param string $redirects One of 'noredirects', 'onlyredirects' or blank for both
     * @return string[] Result of query, see below. Includes live and deleted pages.
     */
    public function getPagesCreated(Project $project, User $user, $namespace, $redirects)
    {
        $username = $user->getUsername();

        $cacheKey = 'pages.' . $project->getDatabaseName() . '.'
            . $user->getCacheKey() . '.' . $namespace . '.' . $redirects;
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $this->stopwatch->start($cacheKey, 'XTools');

        $dbName = $project->getDatabaseName();
        $projectRepo = $project->getRepository();

        $pageTable = $projectRepo->getTableName($dbName, 'page');
        $pageAssessmentsTable = $projectRepo->getTableName($dbName, 'page_assessments');
        $revisionTable = $projectRepo->getTableName($dbName, 'revision');
        $archiveTable = $projectRepo->getTableName($dbName, 'archive');
        $logTable = $projectRepo->getTableName($dbName, 'logging', 'userindex');

        $userId = $user->getId($project);

        $namespaceConditionArc = '';
        $namespaceConditionRev = '';

        if ($namespace != 'all') {
            $namespaceConditionRev = " AND page_namespace = '".intval($namespace)."' ";
            $namespaceConditionArc = " AND ar_namespace = '".intval($namespace)."' ";
        }

        $redirectCondition = '';

        if ($redirects == 'onlyredirects') {
            $redirectCondition = " AND page_is_redirect = '1' ";
        } elseif ($redirects == 'noredirects') {
            $redirectCondition = " AND page_is_redirect = '0' ";
        }

        if ($userId == 0) { // IP Editor or undefined username.
            $whereRev = " rev_user_text = '$username' AND rev_user = '0' ";
            $whereArc = " ar_user_text = '$username' AND ar_user = '0' ";
            $having = " rev_user_text = '$username' ";
        } else {
            $whereRev = " rev_user = '$userId' AND rev_timestamp > 1 ";
            $whereArc = " ar_user = '$userId' AND ar_timestamp > 1 ";
            $having = " rev_user = '$userId' ";
        }

        $hasPageAssessments = $this->isLabs() && $project->hasPageAssessments();
        $paSelects = $hasPageAssessments ? ', pa_class, pa_importance, pa_page_revision' : '';
        $paSelectsArchive = $hasPageAssessments ?
            ', NULL AS pa_class, NULL AS pa_page_id, NULL AS pa_page_revision'
            : '';
        $paJoin = $hasPageAssessments ? "LEFT JOIN $pageAssessmentsTable ON rev_page = pa_page_id" : '';

        $sql = "
            (SELECT DISTINCT page_namespace AS namespace, 'rev' AS type, page_title AS page_title,
                page_len, page_is_redirect, rev_timestamp AS rev_timestamp,
                rev_user, rev_user_text AS username, rev_len, rev_id $paSelects
            FROM $pageTable
            JOIN $revisionTable ON page_id = rev_page
            $paJoin
            WHERE $whereRev AND rev_parent_id = '0' $namespaceConditionRev $redirectCondition
            " . ($hasPageAssessments ? 'GROUP BY rev_page' : '') . "
            )

            UNION

            (SELECT a.ar_namespace AS namespace, 'arc' AS type, a.ar_title AS page_title,
                0 AS page_len, '0' AS page_is_redirect, MIN(a.ar_timestamp) AS rev_timestamp,
                a.ar_user AS rev_user, a.ar_user_text AS username, a.ar_len AS rev_len,
                a.ar_rev_id AS rev_id $paSelectsArchive
            FROM $archiveTable a
            JOIN
            (
                SELECT b.ar_namespace, b.ar_title
                FROM $archiveTable AS b
                LEFT JOIN $logTable ON log_namespace = b.ar_namespace AND log_title = b.ar_title
                    AND log_user = b.ar_user AND (log_action = 'move' OR log_action = 'move_redir')
                WHERE $whereArc AND b.ar_parent_id = '0' $namespaceConditionArc AND log_action IS NULL
            ) AS c ON c.ar_namespace= a.ar_namespace AND c.ar_title = a.ar_title
            GROUP BY a.ar_namespace, a.ar_title
            HAVING $having
            )
            ";

        $resultQuery = $this->getProjectsConnection()->prepare($sql);
        $resultQuery->execute();
        $result = $resultQuery->fetchAll();

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($result)
            ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);
        $this->stopwatch->stop($cacheKey);

        return $result;
    }

    /**
     * Get edit count within given timeframe and namespace
     * @param Project $project
     * @param User $user
     * @param int|string [$namespace] Namespace ID or 'all' for all namespaces
     * @param string [$start] Start date in a format accepted by strtotime()
     * @param string [$end] End date in a format accepted by strtotime()
     */
    public function countEdits(Project $project, User $user, $namespace = 'all', $start = '', $end = '')
    {
        $cacheKey = 'editcount.' . $project->getDatabaseName() . '.'
            . $user->getCacheKey() . '.' . $namespace;

        $condBegin = '';
        $condEnd = '';

        if (!empty($start)) {
            $cacheKey .= '.' . $start;

            // For the query
            $start = date('Ymd000000', strtotime($start));
            $condBegin = 'AND rev_timestamp >= :start ';
        }
        if (!empty($end)) {
            $cacheKey .= '.' . $end;

            // For the query
            $end = date('Ymd235959', strtotime($end));
            $condEnd = 'AND rev_timestamp <= :end ';
        }

        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $condNamespace = $namespace === 'all' ? '' : 'AND page_namespace = :namespace';
        $pageJoin = $namespace === 'all' ? '' : 'JOIN page ON page_id = rev_page';

        $sql = "SELECT COUNT(*)
                FROM $revisionTable
                $pageJoin
                WHERE rev_user_text = :username
                $condNamespace
                $condBegin
                $condEnd";

        $username = $user->getUsername();
        $conn = $this->getProjectsConnection();
        $resultQuery = $conn->prepare($sql);
        $resultQuery->bindParam('username', $username);
        if (!empty($start)) {
            $resultQuery->bindParam('start', $start);
        }
        if (!empty($end)) {
            $resultQuery->bindParam('end', $end);
        }
        if ($namespace !== 'all') {
            $resultQuery->bindParam('namespace', $namespace);
        }
        $resultQuery->execute();
        $result = $resultQuery->fetchColumn();

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($result)
            ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);

        return $result;
    }

    /**
     * Get the number of edits this user made using semi-automated tools.
     * @param Project $project
     * @param User $user
     * @param string|int [$namespace] Namespace ID or 'all'
     * @param string [$start] Start date in a format accepted by strtotime()
     * @param string [$end] End date in a format accepted by strtotime()
     * @return string[] Result of query, see below.
     */
    public function countAutomatedEdits(Project $project, User $user, $namespace = 'all', $start = '', $end = '')
    {
        $cacheKey = 'autoeditcount.' . $project->getDatabaseName() . '.'
            . $user->getCacheKey() . '.' . $namespace;

        $condBegin = '';
        $condEnd = '';

        if (!empty($start)) {
            $cacheKey .= '.' . $start;

            // For the query
            $start = date('Ymd000000', strtotime($start));
            $condBegin = 'AND rev_timestamp >= :start ';
        }
        if (!empty($end)) {
            $cacheKey .= '.' . $end;

            // For the query
            $end = date('Ymd235959', strtotime($end));
            $condEnd = 'AND rev_timestamp <= :end ';
        }

        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $this->stopwatch->start($cacheKey, 'XTools');

        $conn = $this->getProjectsConnection();
        $tools = $this->container->getParameter('automated_tools');
        $toolRegexes = array_map(function ($tool) {
            return $tool['regex'];
        }, $tools);
        $regex = $conn->quote(implode('|', $toolRegexes), \PDO::PARAM_STR);

        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $condNamespace = $namespace === 'all' ? '' : 'AND page_namespace = :namespace';
        $pageJoin = $namespace === 'all' ? '' : 'JOIN page ON page_id = rev_page';
        $sql = "SELECT COUNT(*)
                FROM $revisionTable
                $pageJoin
                WHERE rev_user_text = :username
                AND rev_comment REGEXP $regex
                $condNamespace
                $condBegin
                $condEnd";

        $username = $user->getUsername();
        $resultQuery = $conn->prepare($sql);
        $resultQuery->bindParam('username', $username);
        if (!empty($start)) {
            $resultQuery->bindParam('start', $start);
        }
        if (!empty($end)) {
            $resultQuery->bindParam('end', $end);
        }
        if ($namespace !== 'all') {
            $resultQuery->bindParam('namespace', $namespace);
        }
        $resultQuery->execute();
        $result = $resultQuery->fetchColumn();

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($result)
            ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);
        $this->stopwatch->stop($cacheKey);

        return $result;
    }

    /**
     * Get non-automated contributions for the given user.
     * @param Project $project
     * @param User $user
     * @param string|int [$namespace] Namespace ID or 'all'
     * @param string [$start] Start date in a format accepted by strtotime()
     * @param string [$end] End date in a format accepted by strtotime()
     * @param int [$offset] Used for pagination, offset results by N edits
     * @return string[] Result of query, with columns 'page_title',
     *   'page_namespace', 'rev_id', 'timestamp', 'minor',
     *   'length', 'length_change', 'comment'
     */
    public function getNonAutomatedEdits(
        Project $project,
        User $user,
        $namespace = 'all',
        $start = '',
        $end = '',
        $offset = 0
    ) {
        $cacheKey = 'nonautoedits.' . $project->getDatabaseName() . '.'
            . $user->getCacheKey() . '.' . $namespace . '.' . $offset;

        $condBegin = '';
        $condEnd = '';

        if (!empty($start)) {
            $cacheKey .= '.' . $start;

            // For the query
            $start = date('Ymd000000', strtotime($start));
            $condBegin = 'AND revs.rev_timestamp >= :start ';
        }
        if (!empty($end)) {
            $cacheKey .= '.' . $end;

            // For the query
            $end = date('Ymd235959', strtotime($end));
            $condEnd = 'AND revs.rev_timestamp <= :end ';
        }

        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $this->stopwatch->start($cacheKey, 'XTools');

        $conn = $this->getProjectsConnection();

        // Build regex for all semi-automated tools
        $tools = $this->container->getParameter('automated_tools');
        $toolRegexes = array_map(function ($tool) {
            return $tool['regex'];
        }, $tools);
        $regex = $conn->quote(implode('|', $toolRegexes), \PDO::PARAM_STR);

        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $condNamespace = $namespace === 'all' ? '' : 'AND page_namespace = :namespace';
        $sql = "SELECT
                    page_title,
                    page_namespace,
                    revs.rev_id AS rev_id,
                    revs.rev_timestamp AS timestamp,
                    revs.rev_minor_edit AS minor,
                    revs.rev_len AS length,
                    (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                    revs.rev_comment AS comment
                FROM $pageTable
                JOIN $revisionTable AS revs ON (page_id = revs.rev_page)
                LEFT JOIN $revisionTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                WHERE revs.rev_user_text = :username
                AND revs.rev_timestamp > 0
                AND revs.rev_comment NOT RLIKE $regex
                $condBegin
                $condEnd
                $condNamespace
                ORDER BY revs.rev_timestamp DESC
                LIMIT 50
                OFFSET $offset";

        $username = $user->getUsername();
        $resultQuery = $conn->prepare($sql);
        $resultQuery->bindParam('username', $username);
        if (!empty($start)) {
            $resultQuery->bindParam('start', $start);
        }
        if (!empty($end)) {
            $resultQuery->bindParam('end', $end);
        }
        if ($namespace !== 'all') {
            $resultQuery->bindParam('namespace', $namespace);
        }
        $resultQuery->execute();
        $result = $resultQuery->fetchAll();

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($result)
            ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);
        $this->stopwatch->stop($cacheKey);

        return $result;
    }

    /**
     * Get non-automated contributions for the given user.
     * @param Project $project
     * @param User $user
     * @param string|int [$namespace] Namespace ID or 'all'
     * @param string [$start] Start date in a format accepted by strtotime()
     * @param string [$end] End date in a format accepted by strtotime()
     * @return string[] Each tool that they used along with the count and link:
     *                  [
     *                      'Twinkle' => [
     *                          'count' => 50,
     *                          'link' => 'Wikipedia:Twinkle',
     *                      ],
     *                  ]
     */
    public function getAutomatedCounts(
        Project $project,
        User $user,
        $namespace = 'all',
        $start = '',
        $end = ''
    ) {
        $cacheKey = 'autotoolcounts.' . $project->getDatabaseName() . '.'
            . $user->getCacheKey() . '.' . $namespace;

        $condBegin = '';
        $condEnd = '';

        if (!empty($start)) {
            $cacheKey .= '.' . $start;

            // For the query
            $start = date('Ymd000000', strtotime($start));
            $condBegin = 'AND rev_timestamp >= :start ';
        }
        if (!empty($end)) {
            $cacheKey .= '.' . $end;

            // For the query
            $end = date('Ymd235959', strtotime($end));
            $condEnd = 'AND rev_timestamp <= :end ';
        }

        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }
        $this->stopwatch->start($cacheKey, 'XTools');

        $conn = $this->getProjectsConnection();

        // Load the semi-automated edit types.
        $tools = $this->container->getParameter('automated_tools');

        // Create a collection of queries that we're going to run.
        $queries = [];

        $revisionTable = $project->getRepository()->getTableName($project->getDatabaseName(), 'revision');
        $archiveTable = $project->getRepository()->getTableName($project->getDatabaseName(), 'archive');
        $pageTable = $project->getRepository()->getTableName($project->getDatabaseName(), 'page');

        $pageJoin = $namespace !== 'all' ? "JOIN $pageTable ON rev_page = page_id" : null;
        $condNamespace = $namespace !== 'all' ? "AND page_namespace = :namespace" : null;

        foreach ($tools as $toolname => $values) {
            $toolname = $conn->quote($toolname, \PDO::PARAM_STR);
            $regex = $conn->quote($values['regex'], \PDO::PARAM_STR);

            $queries[] .= "
                SELECT $toolname AS toolname, COUNT(*) AS count
                FROM $revisionTable
                $pageJoin
                WHERE rev_user_text = :username
                AND rev_comment REGEXP $regex
                $condNamespace
                $condBegin
                $condEnd";
        }

        // Create a big query and execute.
        $sql = implode(' UNION ', $queries);

        $resultQuery = $conn->prepare($sql);

        $username = $user->getUsername(); // use normalized user name
        $resultQuery->bindParam('username', $username);
        if (!empty($start)) {
            $startParam = date('Ymd000000', strtotime($start));
            $resultQuery->bindParam('start', $startParam);
        }
        if (!empty($end)) {
            $endParam = date('Ymd235959', strtotime($end));
            $resultQuery->bindParam('end', $endParam);
        }
        if ($namespace !== 'all') {
            $resultQuery->bindParam('namespace', $namespace);
        }

        $resultQuery->execute();

        // handling results
        $results = [];

        while ($row = $resultQuery->fetch()) {
            // Only track tools that they've used at least once
            $tool = $row['toolname'];
            if ($row['count'] > 0) {
                $results[$tool] = [
                    'link' => $tools[$tool]['link'],
                    'count' => $row['count'],
                ];
            }
        }

        // Sort the array by count
        uasort($results, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        // Cache for 10 minutes, and return.
        $cacheItem = $this->cache->getItem($cacheKey)
            ->set($results)
            ->expiresAfter(new DateInterval('PT10M'));
        $this->cache->save($cacheItem);
        $this->stopwatch->stop($cacheKey);

        return $results;
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
}
