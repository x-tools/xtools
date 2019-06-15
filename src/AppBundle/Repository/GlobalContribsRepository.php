<?php
declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Project;
use AppBundle\Model\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A GlobalContribsRepository is responsible for retrieving information from the database for the GlobalContribs tool.
 * @codeCoverageIgnore
 */
class GlobalContribsRepository extends Repository
{
    /** @var Project CentralAuth project (meta.wikimedia for WMF installation). */
    protected $caProject;

    /**
     * Create Project and ProjectRepository once we have the container.
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container): void
    {
        parent::setContainer($container);

        $this->caProject = ProjectRepository::getProject(
            $this->container->getParameter('central_auth_project'),
            $this->container
        );
        $this->caProject->getRepository()
            ->setContainer($this->container);
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
            $out[] = [
                'dbName' => $editCount['dbName'],
                'total' => $editCount['total'],
                'project' => ProjectRepository::getProject($editCount['dbName'], $this->container),
            ];
        }
        return $out;
    }

    /**
     * Get a user's total edit count on one or more project.
     * Requires the CentralAuth extension to be installed on the project.
     * @param User $user The user.
     * @return mixed[]|false Elements are arrays with 'dbName' (string), and 'total' (int). False for logged out users.
     */
    protected function globalEditCountsFromCentralAuth(User $user)
    {
        if (true === $user->isAnon()) {
            return false;
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
            $projects[$dbName] = ProjectRepository::getProject($dbName, $this->container);
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
     * @return mixed[] Keys are database names, values are actor IDs.
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

        $sqlParts = [];

        foreach ($dbNames as $dbName) {
            // actor_revision table only includes users who have made at least one edit.
            $actorTable = $this->getTableName($dbName, 'actor', 'revision');
            $sqlParts []= "SELECT '$dbName' AS `dbName`, actor_id
                           FROM $actorTable WHERE actor_name = :actor";
        }

        $sql = implode(' UNION ', $sqlParts);
        $resultQuery = $this->executeProjectsQuery($sql, [
            'actor' => $user->getUsername(),
        ]);

        $actorIds = [];
        while ($row = $resultQuery->fetch()) {
            $actorIds[$row['dbName']] = (int)$row['actor_id'];
        }

        return $this->setCache($cacheKey, $actorIds);
    }

    /**
     * Get revisions by this user across the given Projects.
     * @param string[] $dbNames Database names of projects to iterate over.
     * @param User $user The user.
     * @param int|string $namespace Namespace ID or 'all' for all namespaces.
     * @param string $start Start date in a format accepted by strtotime().
     * @param string $end Start date in a format accepted by strtotime().
     * @param int $limit The maximum number of revisions to fetch from each project.
     * @param int $offset Offset results by this number of rows.
     * @return array|mixed
     */
    public function getRevisions(
        array $dbNames,
        User $user,
        $namespace = 'all',
        $start = '',
        $end = '',
        int $limit = 30,
        int $offset = 0
    ) {
        // Check cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'gc_revisions');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $username = $this->getProjectsConnection()->quote($user->getUsername(), \PDO::PARAM_STR);
        $actorIds = $this->getDbNamesAndActorIds($user, $dbNames);
        $namespaceCond = 'all' === $namespace
            ? ''
            : 'AND page_namespace = '.(int)$namespace;
        $revDateConditions = $this->getDateConditions($start, $end, 'revs.');

        // Assemble queries.
        $queries = [];
        $projectRepo = $this->caProject->getRepository();
        foreach ($dbNames as $dbName) {
            $revisionTable = $projectRepo->getTableName($dbName, 'revision');
            $pageTable = $projectRepo->getTableName($dbName, 'page');
            $commentTable = $projectRepo->getTableName($dbName, 'comment', 'revision');

            $sql = "SELECT
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
                    comment_text AS `comment`
                FROM $revisionTable AS revs
                    JOIN $pageTable AS page ON (rev_page = page_id)
                    LEFT JOIN $revisionTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                    LEFT OUTER JOIN $commentTable ON revs.rev_comment_id = comment_id
                WHERE revs.rev_actor = ".$actorIds[$dbName]."
                    $namespaceCond
                    $revDateConditions";
            $queries[] = $sql;
        }
        $sql = "SELECT * FROM ((\n" . join("\n) UNION (\n", $queries) . ")) a ORDER BY timestamp DESC LIMIT $limit";

        if (is_numeric($offset)) {
            $sql .= " OFFSET $offset";
        }

        $revisions = $this->executeProjectsQuery($sql)->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $revisions);
    }
}
