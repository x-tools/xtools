<?php
/**
 * This file contains only the UserRightsRepository class.
 */

namespace Xtools;

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
    public function getRightsChanges(Project $project, User $user)
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
    public function getGlobalRightsChanges(Project $project, User $user)
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
    private function queryRightsChanges(Project $project, User $user, $type = 'local')
    {
        $dbName = $project->getDatabaseName();

        // Global rights and Meta-changed rights should use a Meta Project.
        if ($type !== 'local') {
            $dbName = 'metawiki';
        }

        $loggingTable = $this->getTableName($dbName, 'logging', 'logindex');
        $userTable = $this->getTableName($dbName, 'user');
        $username = str_replace(' ', '_', $user->getUsername());

        if ($type === 'meta') {
            // Reference the original Project.
            $username = $username.'@'.$project->getDatabaseName();
        }

        // Way back when it was possible to have usernames with lowercase characters.
        // Some log entires are caught unless we look for both variations.
        $usernameLower = lcfirst($username);

        $logType = $type == 'global' ? 'gblrights' : 'rights';

        $sql = "SELECT log_id, log_timestamp, log_comment, log_params, log_action,
                    IF(log_user_text != '', log_user_text, (
                        SELECT user_name
                        FROM $userTable
                        WHERE user_id = log_user
                    )) AS log_user_text,
                    '$type' AS type
                FROM $loggingTable
                WHERE log_type = '$logType'
                AND log_namespace = 2
                AND log_title IN (:username, :username2)
                ORDER BY log_timestamp DESC";

        return $this->executeProjectsQuery($sql, [
            'username' => $username,
            'username2' => $usernameLower,
        ])->fetchAll();
    }

    /**
     * Get the localized names for the user groups, fetched from on-wiki system messages.
     * @param Project $project
     * @param string[] $rights Database values for the rights we want the names of.
     * @param string $lang Language code to pass in.
     * @return string[] Localized names keyed by database value.
     */
    public function getRightsNames(Project $project, $rights, $lang)
    {
        $rightsPaths = array_map(function ($right) {
            return "Group-$right-member";
        }, $rights);

        $params = [
            'action' => 'query',
            'meta' => 'allmessages',
            'ammessages' => implode('|', $rightsPaths),
            'amlang' => $lang,
            'amenableparser' => 1,
            'formatversion' => 2,
        ];
        $api = $this->getMediawikiApi($project);
        $query = new SimpleRequest('query', $params);
        $result = $api->getRequest($query);

        $allmessages = $result['query']['allmessages'];

        $localized = [];

        foreach ($allmessages as $msg) {
            $normalized = preg_replace('/^group-|-member$/', '', $msg['normalizedname']);
            $localized[$normalized] = $msg['content'];
        }

        return $localized;
    }
}
