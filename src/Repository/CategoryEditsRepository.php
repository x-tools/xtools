<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Helper\AutomatedEditsHelper;
use App\Model\Edit;
use App\Model\Project;
use App\Model\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Wikimedia\IPUtils;

/**
 * CategoryEditsRepository is responsible for retrieving data from the database
 * about the edits made by a user to pages in a set of given categories.
 * @codeCoverageIgnore
 */
class CategoryEditsRepository extends Repository
{
    protected AutomatedEditsHelper $autoEditsHelper;
    protected EditRepository $editRepo;
    protected PageRepository $pageRepo;
    protected UserRepository $userRepo;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param CacheItemPoolInterface $cache
     * @param Client $guzzle
     * @param LoggerInterface $logger
     * @param ParameterBagInterface $parameterBag
     * @param bool $isWMF
     * @param int $queryTimeout
     * @param AutomatedEditsHelper $autoEditsHelper
     * @param EditRepository $editRepo
     * @param PageRepository $pageRepo
     * @param UserRepository $userRepo
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        CacheItemPoolInterface $cache,
        Client $guzzle,
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
        bool $isWMF,
        int $queryTimeout,
        AutomatedEditsHelper $autoEditsHelper,
        EditRepository $editRepo,
        PageRepository $pageRepo,
        UserRepository $userRepo
    ) {
        $this->autoEditsHelper = $autoEditsHelper;
        $this->editRepo = $editRepo;
        $this->pageRepo = $pageRepo;
        $this->userRepo = $userRepo;
        parent::__construct($managerRegistry, $cache, $guzzle, $logger, $parameterBag, $isWMF, $queryTimeout);
    }

    /**
     * Get the number of edits this user made to the given categories.
     * @param Project $project
     * @param User $user
     * @param string[] $categories
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @return int Result of query, see below.
     */
    public function countCategoryEdits(
        Project $project,
        User $user,
        array $categories,
        $start = false,
        $end = false
    ): int {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_categoryeditcount');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revisionTable = $project->getTableName('revision');
        $categorylinksTable = $project->getTableName('categorylinks');
        $revDateConditions = $this->getDateConditions($start, $end, false, 'revs.');
        $whereClause = 'revs.rev_actor = ?';
        $ipcJoin = '';

        if ($user->isIpRange()) {
            $ipcTable = $project->getTableName('ip_changes');
            $ipcJoin = "JOIN $ipcTable ON ipc_rev_id = revs.rev_id";
            $whereClause = 'ipc_hex BETWEEN ? AND ?';
        }

        $sql = "SELECT COUNT(DISTINCT revs.rev_id)
                FROM $revisionTable revs
                $ipcJoin
                JOIN $categorylinksTable ON cl_from = rev_page
                WHERE $whereClause
                    AND cl_to IN (?)
                    $revDateConditions";
        $result = (int)$this->executeStmt($sql, $project, $user, $categories)->fetchOne();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Get number of edits within each individual category.
     * @param Project $project
     * @param User $user
     * @param array $categories
     * @param int|false $start
     * @param int|false $end
     * @return string[] With categories as keys, counts as values.
     */
    public function getCategoryCounts(
        Project $project,
        User $user,
        array $categories,
        $start = false,
        $end = false
    ): array {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_categorycounts');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revisionTable = $project->getTableName('revision');
        $categorylinksTable = $project->getTableName('categorylinks');
        $revDateConditions = $this->getDateConditions($start, $end, false, 'revs.');
        $whereClause = 'revs.rev_actor = ?';
        $ipcJoin = '';

        if ($user->isIpRange()) {
            $ipcTable = $project->getTableName('ip_changes');
            $ipcJoin = "JOIN $ipcTable ON ipc_rev_id = revs.rev_id";
            $whereClause = 'ipc_hex BETWEEN ? AND ?';
        }

        $sql = "SELECT cl_to AS cat, COUNT(rev_id) AS edit_count, COUNT(DISTINCT rev_page) AS page_count
                FROM $revisionTable revs
                $ipcJoin
                JOIN $categorylinksTable ON cl_from = rev_page
                WHERE $whereClause
                    AND cl_to IN (?)
                    $revDateConditions
                GROUP BY cl_to
                ORDER BY edit_count DESC";

        $counts = [];
        $stmt = $this->executeStmt($sql, $project, $user, $categories);
        while ($result = $stmt->fetchAssociative()) {
            $counts[$result['cat']] = [
                'editCount' => (int)$result['edit_count'],
                'pageCount' => (int)$result['page_count'],
            ];
        }

        // Cache and return.
        return $this->setCache($cacheKey, $counts);
    }

    /**
     * Get contributions made to the given categories.
     * @param Project $project
     * @param User $user
     * @param string[] $categories
     * @param int|false $start Start date as Unix timestamp.
     * @param int|false $end End date as Unix timestamp.
     * @param false|int $offset Unix timestamp. Used for pagination.
     * @return string[] Result of query, with columns 'page_title', 'page_namespace', 'rev_id', 'timestamp', 'minor',
     *   'length', 'length_change', 'comment'
     */
    public function getCategoryEdits(
        Project $project,
        User $user,
        array $categories,
        $start = false,
        $end = false,
        $offset = false
    ): array {
        $cacheKey = $this->getCacheKey(func_get_args(), 'user_categoryedits');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $pageTable = $project->getTableName('page');
        $revisionTable = $project->getTableName('revision');
        $commentTable = $project->getTableName('comment');
        $categorylinksTable = $project->getTableName('categorylinks');
        $revDateConditions = $this->getDateConditions($start, $end, $offset, 'revs.');
        $whereClause = 'revs.rev_actor = ?';
        $ipcJoin = '';

        if ($user->isIpRange()) {
            $ipcTable = $project->getTableName('ip_changes');
            $ipcJoin = "JOIN $ipcTable ON ipc_rev_id = revs.rev_id";
            $whereClause = 'ipc_hex BETWEEN ? AND ?';
        }

        $sql = "SELECT page_title, page_namespace, revs.rev_id AS rev_id, revs.rev_timestamp AS timestamp,
                    revs.rev_minor_edit AS minor, revs.rev_len AS length,
                    (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                    comment_text AS `comment`
                FROM $pageTable
                JOIN $revisionTable revs ON page_id = revs.rev_page
                $ipcJoin
                JOIN $categorylinksTable ON cl_from = rev_page
                LEFT JOIN $commentTable comment ON revs.rev_comment_id = comment_id
                LEFT JOIN $revisionTable parentrevs ON revs.rev_parent_id = parentrevs.rev_id
                WHERE $whereClause
                    AND cl_to IN (?)
                    $revDateConditions
                GROUP BY revs.rev_id
                ORDER BY revs.rev_timestamp DESC
                LIMIT 50";

        $result = $this->executeStmt($sql, $project, $user, $categories)->fetchAllAssociative();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }

    /**
     * Bind dates, username and categories then execute the query.
     * @param string $sql
     * @param Project $project
     * @param User $user
     * @param string[] $categories
     * @return ResultStatement
     */
    private function executeStmt(
        string $sql,
        Project $project,
        User $user,
        array $categories
    ): ResultStatement {
        if ($user->isIpRange()) {
            [$hexStart, $hexEnd] = IPUtils::parseRange($user->getUsername());
            $params = [
                $hexStart,
                $hexEnd,
                $categories,
            ];
            $types = [
                ParameterType::STRING,
                ParameterType::STRING,
                Connection::PARAM_STR_ARRAY,
            ];
        } else {
            $params = [
                $user->getActorId($project),
                $categories,
            ];
            $types = [
                ParameterType::STRING,
                Connection::PARAM_STR_ARRAY,
            ];
        }

        return $this->getProjectsConnection($project)
            ->executeQuery($sql, $params, $types);
    }

    /**
     * Get Edits given revision rows (JOINed on the page table).
     * @param Project $project
     * @param User $user
     * @param array $revs Each must contain 'page_title' and 'page_namespace'.
     * @return Edit[]
     */
    public function getEditsFromRevs(Project $project, User $user, array $revs): array
    {
        return Edit::getEditsFromRevs(
            $this->pageRepo,
            $this->editRepo,
            $this->userRepo,
            $project,
            $user,
            $revs
        );
    }
}
