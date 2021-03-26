<?php
/**
 * This file contains only the CategoryEditsRepository class.
 */

declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Helper\AutomatedEditsHelper;
use AppBundle\Model\Project;
use AppBundle\Model\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\ParameterType;

/**
 * CategoryEditsRepository is responsible for retrieving data from the database
 * about the edits made by a user to pages in a set of given categories.
 * @codeCoverageIgnore
 */
class CategoryEditsRepository extends Repository
{
    /** @var AutomatedEditsHelper Used for fetching the tool list and filtering it. */
    private $aeh;

    /**
     * Method to give the repository access to the AutomatedEditsHelper.
     */
    public function getHelper(): AutomatedEditsHelper
    {
        if (!isset($this->aeh)) {
            $this->aeh = $this->container->get('app.automated_edits_helper');
        }
        return $this->aeh;
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

        $sql = "SELECT COUNT(DISTINCT revs.rev_id)
                FROM $revisionTable revs
                JOIN $categorylinksTable ON cl_from = rev_page
                WHERE revs.rev_actor = ?
                    AND cl_to IN (?)
                    $revDateConditions";
        $result = (int)$this->executeStmt($sql, $project, $user, $categories)->fetchColumn();

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

        $sql = "SELECT cl_to AS cat, COUNT(rev_id) AS edit_count, COUNT(DISTINCT rev_page) AS page_count
                FROM $revisionTable revs
                JOIN $categorylinksTable ON cl_from = rev_page
                WHERE revs.rev_actor = ?
                    AND cl_to IN (?)
                    $revDateConditions
                GROUP BY cl_to
                ORDER BY edit_count DESC";

        $counts = [];
        $stmt = $this->executeStmt($sql, $project, $user, $categories);
        while ($result = $stmt->fetch()) {
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

        $sql = "SELECT page_title, page_namespace, revs.rev_id AS rev_id, revs.rev_timestamp AS timestamp,
                    revs.rev_minor_edit AS minor, revs.rev_len AS length,
                    (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                    comment_text AS `comment`
                FROM $pageTable
                JOIN $revisionTable revs ON page_id = revs.rev_page
                JOIN $categorylinksTable ON cl_from = rev_page
                LEFT JOIN $commentTable comment ON revs.rev_comment_id = comment_id
                LEFT JOIN $revisionTable parentrevs ON revs.rev_parent_id = parentrevs.rev_id
                WHERE revs.rev_actor = ?
                    AND cl_to IN (?)
                    $revDateConditions
                GROUP BY revs.rev_id
                ORDER BY revs.rev_timestamp DESC
                LIMIT 50";

        $result = $this->executeStmt($sql, $project, $user, $categories)->fetchAll();

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
        return $this->getProjectsConnection($project)
            ->executeQuery($sql, [
                $user->getActorId($project),
                $categories,
            ], [
                ParameterType::STRING,
                Connection::PARAM_STR_ARRAY,
            ]);
    }
}
