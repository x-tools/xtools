<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Model\Project;
use App\Model\User;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use PDO;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Wikimedia\IPUtils;

/**
 * A GlobalContribsRepository is responsible for retrieving information from the database for the GlobalContribs tool.
 * @codeCoverageIgnore
 */
class GlobalContribsRepository extends Repository
{
    protected ProjectRepository $projectRepo;

    /** @var Project CentralAuth project (meta.wikimedia for WMF installation). */
    protected Project $caProject;

    public function __construct(
        ManagerRegistry $managerRegistry,
        CacheItemPoolInterface $cache,
        Client $guzzle,
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
        bool $isWMF,
        int $queryTimeout,
        ProjectRepository $projectRepo,
        string $centralAuthProject
    ) {
        $this->caProject = new Project($centralAuthProject);
        $this->projectRepo = $projectRepo;
        $this->caProject->setRepository($this->projectRepo);
        parent::__construct($managerRegistry, $cache, $guzzle, $logger, $parameterBag, $isWMF, $queryTimeout);
    }

    /**
     * Get a user's edit count for each project.
     * @see GlobalContribsRepository::globalEditCountsFromCentralAuth()
     * @see GlobalContribsRepository::globalEditCountsFromDatabases()
     * @param User $user The user.
     * @return mixed[] Elements are arrays with 'project' (Project), and 'total' (int). Null if anon (too slow).
     */
    public function globalEditCounts(User $user): ?array
    {
        if ($user->isAnon()) {
            return null;
        }

        // Get the edit counts from CentralAuth or database.
        $editCounts = $this->globalEditCountsFromCentralAuth($user);

        // Pre-populate all projects' metadata, to prevent each project call from fetching it.
        $this->caProject->getRepository()->getAll();

        // Compile the output.
        $out = [];
        foreach ($editCounts as $editCount) {
            $project = new Project($editCount['dbName']);
            $project->setRepository($this->projectRepo);
            // Make sure the project exists (new projects may not yet be on db replicas).
            if ($project->exists()) {
                $out[] = [
                    'dbName' => $editCount['dbName'],
                    'total' => $editCount['total'],
                    'project' => $project,
                ];
            }
        }
        return $out;
    }

    /**
     * Get a user's total edit count on one or more project.
     * Requires the CentralAuth extension to be installed on the project.
     * @param User $user The user.
     * @return array|null Elements are arrays with 'dbName' (string), and 'total' (int). Null for logged out users.
     */
    protected function globalEditCountsFromCentralAuth(User $user): ?array
    {
        if (true === $user->isAnon()) {
            return null;
        }

        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'gc_globaleditcounts');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $params = [
            'meta' => 'globaluserinfo',
            'guiprop' => 'editcount|merged',
            'guiuser' => $user->getUsername(),
        ];
        $result = $this->executeApiRequest($this->caProject, $params);
        if (!isset($result['query']['globaluserinfo']['merged'])) {
            return [];
        }
        $out = [];
        foreach ($result['query']['globaluserinfo']['merged'] as $result) {
            $out[] = [
                'dbName' => $result['wiki'],
                'total' => $result['editcount'],
            ];
        }

        // Cache and return.
        return $this->setCache($cacheKey, $out);
    }

    /**
     * Loop through the given dbNames and create Project objects for each.
     * @param array $dbNames
     * @return Project[] Keyed by database name.
     */
    private function formatProjects(array $dbNames): array
    {
        $projects = [];

        foreach ($dbNames as $dbName) {
            $projects[$dbName] = $this->projectRepo->getProject($dbName);
        }

        return $projects;
    }

    /**
     * Get all Projects on which the user has made at least one edit.
     * @param User $user
     * @return Project[]
     */
    public function getProjectsWithEdits(User $user): array
    {
        if ($user->isAnon()) {
            $dbNames = array_keys($this->getDbNamesAndActorIds($user));
        } else {
            $dbNames = [];

            foreach ($this->globalEditCountsFromCentralAuth($user) as $projectMeta) {
                if ($projectMeta['total'] > 0) {
                    $dbNames[] = $projectMeta['dbName'];
                }
            }
        }

        return $this->formatProjects($dbNames);
    }

    /**
     * Get projects that the user has made at least one edit on, and the associated actor ID.
     * @param User $user
     * @param string[] $dbNames Loop over these projects instead of all of them.
     * @return array Keys are database names, values are actor IDs.
     */
    public function getDbNamesAndActorIds(User $user, ?array $dbNames = null): array
    {
        // Check cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'gc_db_names_actor_ids');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        if (!$dbNames) {
            $dbNames = array_column($this->caProject->getRepository()->getAll(), 'dbName');
        }

        if ($user->isIpRange()) {
            $username = $user->getIpSubstringFromCidr().'%';
            $whereClause = "actor_name LIKE :actor";
        } else {
            $username = $user->getUsername();
            $whereClause = "actor_name = :actor";
        }

        $queriesBySlice = [];

        foreach ($dbNames as $dbName) {
            $slice = $this->getDbList()[$dbName];
            // actor_revision table only includes users who have made at least one edit.
            $actorTable = $this->getTableName($dbName, 'actor', 'revision');
            $queriesBySlice[$slice][] = "SELECT '$dbName' AS `dbName`, actor_id " .
                "FROM $actorTable WHERE $whereClause";
        }

        $actorIds = [];

        foreach ($queriesBySlice as $slice => $queries) {
            $sql = implode(' UNION ', $queries);
            $resultQuery = $this->executeProjectsQuery($slice, $sql, [
                'actor' => $username,
            ]);

            while ($row = $resultQuery->fetchAssociative()) {
                $actorIds[$row['dbName']] = (int)$row['actor_id'];
            }
        }

        return $this->setCache($cacheKey, $actorIds);
    }

    /**
     * Get revisions by this user across the given Projects.
     * @param string[] $dbNames Database names of projects to iterate over.
     * @param User $user The user.
     * @param int|string $namespace Namespace ID or 'all' for all namespaces.
     * @param int|false $start Unix timestamp or false.
     * @param int|false $end Unix timestamp or false.
     * @param int $limit The maximum number of revisions to fetch from each project.
     * @param int|false $offset Unix timestamp. Used for pagination.
     * @return array
     */
    public function getRevisions(
        array $dbNames,
        User $user,
        $namespace = 'all',
        $start = false,
        $end = false,
        int $limit = 31, // One extra to know whether there should be another page.
        $offset = false
    ): array {
        // Check cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'gc_revisions');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        // Just need any Connection to use the ->quote() method.
        $quoteConn = $this->getProjectsConnection('s1');
        $username = $quoteConn->quote($user->getUsername(), PDO::PARAM_STR);

        // IP range handling.
        $startIp = '';
        $endIp = '';
        if ($user->isIpRange()) {
            [$startIp, $endIp] = IPUtils::parseRange($user->getUsername());
            $startIp = $quoteConn->quote($startIp, PDO::PARAM_STR);
            $endIp = $quoteConn->quote($endIp, PDO::PARAM_STR);
        }

        // Fetch actor IDs (for IP ranges, it strips trailing zeros and uses a LIKE query).
        $actorIds = $this->getDbNamesAndActorIds($user, $dbNames);

        if (!$actorIds) {
            return [];
        }

        $namespaceCond = 'all' === $namespace
            ? ''
            : 'AND page_namespace = '.(int)$namespace;
        $revDateConditions = $this->getDateConditions($start, $end, $offset, 'revs.', 'rev_timestamp');

        // Assemble queries.
        $queriesBySlice = [];
        $projectRepo = $this->caProject->getRepository();
        foreach ($dbNames as $dbName) {
            if (isset($actorIds[$dbName])) {
                $revisionTable = $projectRepo->getTableName($dbName, 'revision');
                $pageTable = $projectRepo->getTableName($dbName, 'page');
                $commentTable = $projectRepo->getTableName($dbName, 'comment', 'revision');
                $actorTable = $projectRepo->getTableName($dbName, 'actor', 'revision');
                $tagTable = $projectRepo->getTableName($dbName, 'change_tag');
                $tagDefTable = $projectRepo->getTableName($dbName, 'change_tag_def');

                if ($user->isIpRange()) {
                    $ipcTable = $projectRepo->getTableName($dbName, 'ip_changes');
                    $ipcJoin = "JOIN $ipcTable ON revs.rev_id = ipc_rev_id";
                    $whereClause = "ipc_hex BETWEEN $startIp AND $endIp";
                    $username = 'actor_name';
                } else {
                    $ipcJoin = '';
                    $whereClause = 'revs.rev_actor = '.$actorIds[$dbName];
                }

                $slice = $this->getDbList()[$dbName];
                $queriesBySlice[$slice][] = "
                    SELECT
                        '$dbName' AS dbName,
                        revs.rev_id AS id,
                        revs.rev_timestamp AS timestamp,
                        UNIX_TIMESTAMP(revs.rev_timestamp) AS unix_timestamp,
                        revs.rev_minor_edit AS minor,
                        revs.rev_deleted AS deleted,
                        revs.rev_len AS length,
                        (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                        revs.rev_parent_id AS parent_id,
                        $username AS username,
                        page.page_title,
                        page.page_namespace,
                        comment_text AS comment,
                        (
                            SELECT 1
                            FROM $tagTable
                            WHERE ct_rev_id = revs.rev_id
                            AND ct_tag_id = (
                                SELECT ctd_id
                                FROM $tagDefTable
                                WHERE ctd_name = 'mw-reverted'
                            )
                            LIMIT 1
                        ) AS reverted
                    FROM $revisionTable AS revs
                        $ipcJoin
                        JOIN $pageTable AS page ON (rev_page = page_id)
                        JOIN $actorTable ON (actor_id = revs.rev_actor)
                        LEFT JOIN $revisionTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                        LEFT OUTER JOIN $commentTable ON revs.rev_comment_id = comment_id
                    WHERE $whereClause
                        $namespaceCond
                        $revDateConditions";
            }
        }

        // Re-assemble into UNIONed queries, executing as many per slice as possible.
        $revisions = [];
        foreach ($queriesBySlice as $slice => $queries) {
            $sql = "SELECT * FROM ((\n" . join("\n) UNION (\n", $queries) . ")) a ORDER BY timestamp DESC LIMIT $limit";
            $revisions = array_merge($revisions, $this->executeProjectsQuery($slice, $sql)->fetchAllAssociative());
        }

        // If there are more than $limit results, re-sort by timestamp.
        if (count($revisions) > $limit) {
            usort($revisions, function ($a, $b) {
                if ($a['unix_timestamp'] === $b['unix_timestamp']) {
                    return 0;
                }
                return $a['unix_timestamp'] > $b['unix_timestamp'] ? -1 : 1;
            });

            // Truncate size to $limit.
            $revisions = array_slice($revisions, 0, $limit);
        }

        // Cache and return.
        return $this->setCache($cacheKey, $revisions);
    }
}
