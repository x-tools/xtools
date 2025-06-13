<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Model\Edit;
use App\Model\Page;
use App\Model\Project;
use App\Model\User;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use PDO;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Wikimedia\IPUtils;

/**
 * TopEditsRepository is responsible for retrieving data from the database
 * about the top-edited pages of a user. It doesn't do any post-processing
 * of that information.
 * @codeCoverageIgnore
 */
class TopEditsRepository extends UserRepository
{
    protected EditRepository $editRepo;
    protected UserRepository $userRepo;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param CacheItemPoolInterface $cache
     * @param Client $guzzle
     * @param LoggerInterface $logger
     * @param ParameterBagInterface $parameterBag
     * @param bool $isWMF
     * @param int $queryTimeout
     * @param ProjectRepository $projectRepo
     * @param EditRepository $editRepo
     * @param UserRepository $userRepo
     * @param SessionInterface $session
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
        EditRepository $editRepo,
        UserRepository $userRepo,
        RequestStack $requestStack
    ) {
        $this->editRepo = $editRepo;
        $this->userRepo = $userRepo;
        parent::__construct(
            $managerRegistry,
            $cache,
            $guzzle,
            $logger,
            $parameterBag,
            $isWMF,
            $queryTimeout,
            $projectRepo,
            $requestStack
        );
    }

    /**
     * Factory to instantiate a new Edit for the given revision.
     * @param Page $page
     * @param array $revision
     * @return Edit
     */
    public function getEdit(Page $page, array $revision): Edit
    {
        return new Edit($this->editRepo, $this->userRepo, $page, $revision);
    }

    /**
     * Get the top edits by a user in a single namespace.
     * @param Project $project
     * @param User $user
     * @param int $namespace Namespace ID.
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @param int $limit Number of edits to fetch.
     * @param int $pagination Which page of results to return.
     * @return string[] namespace, page_title, redirect, count (number of edits), assessment (page assessment).
     */
    public function getTopEditsNamespace(
        Project $project,
        User $user,
        int $namespace = 0,
        $start = false,
        $end = false,
        int $limit = 1000,
        int $pagination = 0
    ): array {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'topedits_ns');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revDateConditions = $this->getDateConditions($start, $end);
        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');

        $hasPageAssessments = $this->isWMF && $project->hasPageAssessments($namespace);
        $paTable = $project->getTableName('page_assessments');
        $paSelect = $hasPageAssessments
            ?  ", (
                    SELECT pa_class
                    FROM $paTable
                    WHERE pa_page_id = page_id
                    AND pa_class != 'Unknown'
                    LIMIT 1
                ) AS pa_class"
            : '';

        $ipcJoin = '';
        $whereClause = 'rev_actor = :actorId';
        $params = [];
        if ($user->isIpRange()) {
            $ipcTable = $project->getTableName('ip_changes');
            $ipcJoin = "JOIN $ipcTable ON rev_id = ipc_rev_id";
            $whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
        }

        $offset = $pagination * $limit;
        $sql = "SELECT page_namespace AS `namespace`, page_title,
                    page_is_redirect AS `redirect`, COUNT(page_title) AS `count`
                    $paSelect
                FROM $pageTable
                JOIN $revisionTable ON page_id = rev_page
                $ipcJoin
                WHERE $whereClause
                AND page_namespace = :namespace
                $revDateConditions
                GROUP BY page_namespace, page_title
                ORDER BY count DESC
                LIMIT $limit
                OFFSET $offset";

        $resultQuery = $this->executeQuery($sql, $project, $user, $namespace, $params);
        $result = $resultQuery->fetchAllAssociative();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Count the number of edits in the given namespace.
     * @param Project $project
     * @param User $user
     * @param int|string $namespace
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @return mixed
     */
    public function countEditsNamespace(Project $project, User $user, $namespace, $start = false, $end = false)
    {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'topedits_count_ns');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revDateConditions = $this->getDateConditions($start, $end);
        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');

        $ipcJoin = '';
        $whereClause = 'rev_actor = :actorId';
        $params = [];
        if ($user->isIpRange()) {
            $ipcTable = $project->getTableName('ip_changes');
            $ipcJoin = "JOIN $ipcTable ON rev_id = ipc_rev_id";
            $whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
        }

        $sql = "SELECT COUNT(DISTINCT page_id)
                FROM $pageTable
                JOIN $revisionTable ON page_id = rev_page
                $ipcJoin
                WHERE $whereClause
                AND page_namespace = :namespace
                $revDateConditions";

        $resultQuery = $this->executeQuery($sql, $project, $user, $namespace, $params);

        // Cache and return.
        return $this->setCache($cacheKey, $resultQuery->fetchOne());
    }

    /**
     * Get the top edits by a user across all namespaces.
     * @param Project $project
     * @param User $user
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @param int $limit Number of edits to fetch.
     * @return string[] namespace, page_title, redirect, count (number of edits), assessment (page assessment).
     */
    public function getTopEditsAllNamespaces(
        Project $project,
        User $user,
        $start = false,
        $end = false,
        int $limit = 10
    ): array {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'topedits_all');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revDateConditions = $this->getDateConditions($start, $end);
        $pageTable = $this->getTableName($project->getDatabaseName(), 'page');
        $revisionTable = $this->getTableName($project->getDatabaseName(), 'revision');
        $hasPageAssessments = $this->isWMF && $project->hasPageAssessments();
        $pageAssessmentsTable = $this->getTableName($project->getDatabaseName(), 'page_assessments');
        $paSelect = $hasPageAssessments
            ?  ", (
                    SELECT pa_class
                    FROM $pageAssessmentsTable
                    WHERE pa_page_id = e.page_id
                    LIMIT 1
                ) AS pa_class"
            : '';

        $ipcJoin = '';
        $whereClause = 'rev_actor = :actorId';
        $params = [];
        if ($user->isIpRange()) {
            $ipcTable = $project->getTableName('ip_changes');
            $ipcJoin = "JOIN $ipcTable ON rev_id = ipc_rev_id";
            $whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
        }

        $sql = "SELECT c.page_namespace AS `namespace`, e.page_title,
                    c.page_is_redirect AS `redirect`, c.count $paSelect
                FROM
                (
                    SELECT b.page_namespace, b.page_is_redirect, b.rev_page, b.count
                    FROM
                    (
                        SELECT page_namespace, page_is_redirect, rev_page, count(rev_page) AS count
                        FROM $revisionTable
                        $ipcJoin
                        JOIN $pageTable ON page_id = rev_page
                        WHERE $whereClause
                        $revDateConditions
                        GROUP BY page_namespace, rev_page
                    ) AS b
                    JOIN (SELECT @ns := NULL, @rn := 0) AS vars
                    ORDER BY b.page_namespace ASC, b.count DESC
                ) AS c
                JOIN $pageTable e ON e.page_id = c.rev_page";
        $resultQuery = $this->executeQuery($sql, $project, $user, 'all', $params);
        $result = $resultQuery->fetchAllAssociative();
        $namespaceCounts = [];
        $filteredResult = [];
        foreach ($result as $object) {
            if (isset($namespaceCounts[$object['namespace']])) {
                $namespaceCounts[$object['namespace']] += 1;
            } else {
                $namespaceCounts[$object['namespace']] = 1;
            }
            if ($namespaceCounts[$object['namespace']] <= $limit) {
                $filteredResult[] = $object;
            }
        }

        // Cache and return.
        return $this->setCache($cacheKey, $filteredResult);
    }

    /**
     * Get the top edits by a user to a single page.
     * @param Page $page
     * @param User $user
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @return string[][] Each row with keys 'id', 'timestamp', 'minor', 'length',
     *   'length_change', 'reverted', 'user_id', 'username', 'comment', 'parent_comment'
     */
    public function getTopEditsPage(Page $page, User $user, $start = false, $end = false): array
    {
        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'topedits_page');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $results = $this->queryTopEditsPage($page, $user, $start, $end, true);

        // Now we need to get the most recent revision, since the childrevs stuff excludes it.
        $lastRev = $this->queryTopEditsPage($page, $user, $start, $end, false);
        if (empty($results) || $lastRev[0]['id'] !== $results[0]['id']) {
            $results = array_merge($lastRev, $results);
        }

        // Cache and return.
        return $this->setCache($cacheKey, $results);
    }

    /**
     * The actual query to get the top edits by the user to the page.
     * Because of the way the main query works, we aren't given the most recent revision,
     * so we have to call this twice, once with $childRevs set to true and once with false.
     * @param Page $page
     * @param User $user
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @param boolean $childRevs Whether to include child revisions.
     * @return array Each row with keys 'id', 'timestamp', 'minor', 'length',
     *   'length_change', 'reverted', 'user_id', 'username', 'comment', 'parent_comment'
     */
    private function queryTopEditsPage(
        Page $page,
        User $user,
        $start = false,
        $end = false,
        bool $childRevs = false
    ): array {
        $project = $page->getProject();
        $revDateConditions = $this->getDateConditions($start, $end, false, 'revs.');
        $revTable = $project->getTableName('revision');
        $commentTable = $project->getTableName('comment');
        $tagTable = $project->getTableName('change_tag');
        $tagDefTable = $project->getTableName('change_tag_def');

        if ($childRevs) {
            $childSelect = ", (
                    CASE WHEN
                        childrevs.rev_sha1 = parentrevs.rev_sha1
                        OR (
                            SELECT 1
                            FROM $tagTable
                            WHERE ct_rev_id = revs.rev_id
                            AND ct_tag_id = (
                                SELECT ctd_id
                                FROM $tagDefTable
                                WHERE ctd_name = 'mw-reverted'
                            )
                        )
                    THEN 1
                    ELSE 0
                    END
                ) AS `reverted`,
                childcomments.comment_text AS `parent_comment`";
            $childJoin = "LEFT JOIN $revTable AS childrevs ON (revs.rev_id = childrevs.rev_parent_id)
                LEFT OUTER JOIN $commentTable AS childcomments
                ON (childrevs.rev_comment_id = childcomments.comment_id)";
            $childWhere = 'AND childrevs.rev_page = :pageid';
            $childLimit = '';
        } else {
            $childSelect = ', "" AS parent_comment, 0 AS reverted';
            $childJoin = '';
            $childWhere = '';
            $childLimit = 'LIMIT 1';
        }

        $userId = $this->getProjectsConnection($project)->quote($user->getId($page->getProject()), PDO::PARAM_STR);
        $username = $this->getProjectsConnection($project)->quote($user->getUsername(), PDO::PARAM_STR);

        // IP range handling.
        $ipcJoin = '';
        $whereClause = 'revs.rev_actor = :actorId';
        $params = ['pageid' => $page->getId()];
        if ($user->isIpRange()) {
            $ipcTable = $project->getTableName('ip_changes');
            $ipcJoin = "JOIN $ipcTable ON revs.rev_id = ipc_rev_id";
            $whereClause = 'ipc_hex BETWEEN :startIp AND :endIp';
            [$params['startIp'], $params['endIp']] = IPUtils::parseRange($user->getUsername());
        }

        $sql = "SELECT * FROM (
                    SELECT
                        revs.rev_id AS id,
                        revs.rev_timestamp AS timestamp,
                        revs.rev_minor_edit AS minor,
                        revs.rev_len AS length,
                        (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                        $userId AS user_id,
                        $username AS username,
                        comments.comment_text AS `comment`
                        $childSelect
                    FROM $revTable AS revs
                    LEFT JOIN $revTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                    $ipcJoin
                    LEFT OUTER JOIN $commentTable AS comments ON (revs.rev_comment_id = comments.comment_id)
                    $childJoin
                    WHERE $whereClause
                    $revDateConditions
                    AND revs.rev_page = :pageid
                    $childWhere
                ) a
                ORDER BY timestamp DESC
                $childLimit";

        $resultQuery = $this->executeQuery($sql, $project, $user, null, $params);
        return $resultQuery->fetchAllAssociative();
    }
}
