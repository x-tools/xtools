<?php
/**
 * This file contains only the UserRightsRepository class.
 */

declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Project;
use AppBundle\Model\User;
use GuzzleHttp;
use Mediawiki\Api\SimpleRequest;

/**
 * An UserRightsRepository is responsible for retrieving information around a user's
 * rights on a given wiki. It doesn't do any post-processing of that information.
 * @codeCoverageIgnore
 */
class UserRightsRepository extends Repository
{
    /**
     * Get user rights changes of the given user, including those made on Meta.
     * @param Project $project
     * @param User $user
     * @return array
     */
    public function getRightsChanges(Project $project, User $user): array
    {
        $changes = $this->queryRightsChanges($project, $user);

        if ((bool)$this->container->hasParameter('app.is_labs')) {
            $changes = array_merge(
                $changes,
                $this->queryRightsChanges($project, $user, 'meta')
            );
        }

        return $changes;
    }

    /**
     * Get global user rights changes of the given user.
     * @param Project $project Global rights are always on Meta, so this
     *     Project instance is re-used if it is already Meta, otherwise
     *     a new Project instance is created.
     * @param User $user
     * @return array
     */
    public function getGlobalRightsChanges(Project $project, User $user): array
    {
        return $this->queryRightsChanges($project, $user, 'global');
    }

    /**
     * User rights changes for given project, optionally fetched from Meta.
     * @param Project $project Global rights and Meta-changed rights will
     *     automatically use the Meta Project. This Project instance is re-used
     *     if it is already Meta, otherwise a new Project instance is created.
     * @param User $user
     * @param string $type One of 'local' - query the local rights log,
     *     'meta' - query for username@dbname for local rights changes made on Meta, or
     *     'global' - query for global rights changes.
     * @return array
     */
    private function queryRightsChanges(Project $project, User $user, string $type = 'local'): array
    {
        $dbName = $project->getDatabaseName();

        // Global rights and Meta-changed rights should use a Meta Project.
        if ('local' !== $type) {
            $dbName = 'metawiki';
        }

        $loggingTable = $this->getTableName($dbName, 'logging', 'logindex');
        $commentTable = $this->getTableName($dbName, 'comment');
        $userTable = $this->getTableName($dbName, 'user');
        $username = str_replace(' ', '_', $user->getUsername());

        if ('meta' === $type) {
            // Reference the original Project.
            $username .= '@'.$project->getDatabaseName();
        }

        // Way back when it was possible to have usernames with lowercase characters.
        // Some log entries are caught unless we look for both variations.
        $usernameLower = lcfirst($username);

        $logType = 'global' == $type ? 'gblrights' : 'rights';

        $sql = "SELECT log_id, log_timestamp, log_params, log_action,
                    IF(log_user_text != '', log_user_text, (
                        SELECT user_name
                        FROM $userTable
                        WHERE user_id = log_user
                    )) AS log_user_text,
                    CASE WHEN log_comment_id = 0
                        THEN log_comment
                        ELSE IFNULL(comment_text, '')
                        END AS `log_comment`,
                    '$type' AS type
                FROM $loggingTable
                LEFT OUTER JOIN $commentTable ON comment_id = log_comment_id
                WHERE log_type = '$logType'
                AND log_namespace = 2
                AND log_title IN (:username, :username2)";

        return $this->executeProjectsQuery($sql, [
            'username' => $username,
            'username2' => $usernameLower,
        ])->fetchAll();
    }

    /**
     * Get the localized names for all user groups on given Project (and global),
     * fetched from on-wiki system messages.
     * @param Project $project
     * @param string $lang Language code to pass in.
     * @return string[] Localized names keyed by database value.
     */
    public function getRightsNames(Project $project, string $lang): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'project_rights_names');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $rightsPaths = array_map(function ($right) {
            return "Group-$right-member";
        }, $this->getRawRightsNames($project));

        $rightsNames = [];

        for ($i = 0; $i < count($rightsPaths); $i += 50) {
            $rightsSlice = array_slice($rightsPaths, $i, 50);
            $params = [
                'action' => 'query',
                'meta' => 'allmessages',
                'ammessages' => implode('|', $rightsSlice),
                'amlang' => $lang,
                'amenableparser' => 1,
                'formatversion' => 2,
            ];
            $api = $this->getMediawikiApi($project);
            $query = new SimpleRequest('query', $params);
            $result = $api->getRequest($query)['query']['allmessages'];

            foreach ($result as $msg) {
                $normalized = preg_replace('/^group-|-member$/', '', $msg['normalizedname']);
                $rightsNames[$normalized] = $msg['content'] ?? $normalized;
            }
        }

        // Cache for one day and return.
        return $this->setCache($cacheKey, $rightsNames, 'P1D');
    }

    /**
     * Get the names of all the possible local and global user groups.
     * @param Project $project
     * @return string[]
     */
    private function getRawRightsNames(Project $project): array
    {
        $ugTable = $project->getTableName('user_groups');
        $ufgTable = $project->getTableName('user_former_groups');
        $sql = "SELECT DISTINCT(ug_group)
                FROM $ugTable
                UNION
                SELECT DISTINCT(ufg_group)
                FROM $ufgTable";
        if ($this->isLabs()) {
            $sql .= "UNION SELECT DISTINCT(gug_group)
                     FROM centralauth_p.global_user_groups";
        }

        $groups = $this->executeProjectsQuery($sql)->fetchAll(\PDO::FETCH_COLUMN);

        // WMF installations have a special 'autoconfirmed' user group.
        if ($this->isLabs()) {
            $groups[] = 'autoconfirmed';
        }

        return array_unique($groups);
    }

    /**
     * Get the threshold values to become autoconfirmed for the given Project.
     * Yes, eval is bad, but here we're validating only mathematical expressions are ran.
     * @param Project $project
     * @return array|null With keys 'wgAutoConfirmAge' and 'wgAutoConfirmCount'. Null if not found/not applicable.
     */
    public function getAutoconfirmedAgeAndCount(Project $project): ?array
    {
        if (!$this->isLabs()) {
            return null;
        }

        // Set up cache.
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_rightschanges_autoconfirmed');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $client = new GuzzleHttp\Client();
        $url = 'https://noc.wikimedia.org/conf/InitialiseSettings.php.txt';

        $contents = $client->request('GET', $url)
            ->getBody()
            ->getContents();

        $dbname = $project->getDatabaseName();
        $out = [];

        foreach (['wgAutoConfirmAge', 'wgAutoConfirmCount'] as $type) {
            // Extract the text of the file that contains the rules we're looking for.
            $typeRegex = "/\'$type.*?\]/s";
            $matches = [];
            if (1 === preg_match($typeRegex, $contents, $matches)) {
                $group = $matches[0];

                // Find the autoconfirmed expression for the $type and $dbname.
                $matches = [];
                $regex = "/\'$dbname\'\s*\=\>\s*([\d\*\s]+)/s";
                if (1 === preg_match($regex, $group, $matches)) {
                    $out[$type] = (int)eval('return('.$matches[1].');');
                    continue;
                }

                // Find the autoconfirmed expression for the 'default' and $dbname.
                $matches = [];
                $regex = "/\'default\'\s*\=\>\s*([\d\*\s]+)/s";
                if (1 === preg_match($regex, $group, $matches)) {
                    $out[$type] = (int)eval('return('.$matches[1].');');
                    continue;
                }
            } else {
                return null;
            }
        }

        // Cache for one day and return.
        return $this->setCache($cacheKey, $out, 'P1D');
    }

    /**
     * Get the timestamp of the nth edit made by the given user.
     * @param Project $project
     * @param User $user
     * @param string $offset Date to start at, in YYYYMMDDHHSS format.
     * @param int $edits Offset of rows to look for (edit threshold for autoconfirmed).
     * @return string|false Timestamp in YYYYMMDDHHSS format. False if not found.
     */
    public function getNthEditTimestamp(Project $project, User $user, string $offset, int $edits)
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_rightschanges_nthtimestamp');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revisionTable = $project->getTableName('revision');
        $sql = "SELECT rev_timestamp
                FROM $revisionTable
                WHERE rev_user = :userId
                AND rev_timestamp >= $offset
                LIMIT 1 OFFSET ".($edits - 1);

        $ret = $this->executeProjectsQuery($sql, [
            'userId' => $user->getId($project),
        ])->fetchColumn();

        // Cache and return.
        return $this->setCache($cacheKey, $ret);
    }

    /**
     * Get the number of edits the user has made as of the given timestamp.
     * @param Project $project
     * @param User $user
     * @param string $timestamp In YYYYMMDDHHSS format.
     * @return int
     */
    public function getNumEditsByTimestamp(Project $project, User $user, string $timestamp): int
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'ec_rightschanges_editstimestamp');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $revisionTable = $project->getTableName('revision');
        $sql = "SELECT COUNT(rev_id)
                FROM $revisionTable
                WHERE rev_user = :userId
                AND rev_timestamp <= $timestamp";

        $ret = (int)$this->executeProjectsQuery($sql, [
            'userId' => $user->getId($project),
        ])->fetchColumn();

        // Cache and return.
        return $this->setCache($cacheKey, $ret);
    }
}
