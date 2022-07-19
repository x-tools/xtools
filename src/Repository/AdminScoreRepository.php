<?php
/**
 * This file contains only the AdminScoreRepository class.
 */

declare(strict_types = 1);

namespace App\Repository;

use App\Model\Project;
use App\Model\User;

/**
 * A AdminScoreRepository is responsible for retrieving information from the
 * databases for the AdminScore tool. It does not do any post-processing of that data.
 * @codeCoverageIgnore
 */
class AdminScoreRepository extends Repository
{
    /**
     * Fetches basic information and the counts used in calculating scores.
     * @param Project $project
     * @param User $user
     * @return array with keys 'account-age', 'edit-count', 'user-page', 'patrols', 'blocks', 'afd', 'recent-activity',
     *    aiv', 'edit-summaries', 'namespaces', 'pages-created-live', 'pages-created-deleted', and 'rpp'.
     */
    public function fetchData(Project $project, User $user): array
    {
        $userTable = $project->getTableName('user');
        $pageTable = $project->getTableName('page');
        $loggingTable = $project->getTableName('logging', 'userindex');
        $revisionTable = $project->getTableName('revision');
        $archiveTable = $project->getTableName('archive');

        $sql = "SELECT 'account-age' AS source, user_registration AS value FROM $userTable
                    WHERE user_name = :username
                UNION
                SELECT 'edit-count' AS source, user_editcount AS value FROM $userTable
                    WHERE user_name = :username
                UNION
                SELECT 'user-page' AS source, page_len AS value FROM $pageTable
                    WHERE page_namespace = 2 AND page_title = :username
                UNION
                SELECT 'patrols' AS source, COUNT(*) AS value FROM $loggingTable
                    WHERE log_type = 'patrol'
                        AND log_action = 'patrol'
                        AND log_namespace = 0
                        AND log_deleted = 0 AND log_actor = :actorId
                UNION
                SELECT 'blocks' AS source, COUNT(*) AS value FROM $loggingTable l
                    WHERE l.log_type = 'block' AND l.log_action = 'block'
                    AND l.log_namespace = 2 AND l.log_deleted = 0 AND l.log_actor = :actorId
                UNION
                SELECT 'afd' AS source, COUNT(*) AS value FROM $revisionTable r
                  INNER JOIN $pageTable p on p.page_id = r.rev_page
                    WHERE p.page_title LIKE 'Articles_for_deletion/%'
                        AND p.page_title NOT LIKE 'Articles_for_deletion/Log/%'
                        AND r.rev_actor = :actorId
                UNION
                SELECT 'recent-activity' AS source, COUNT(*) AS value FROM $revisionTable
                    WHERE rev_actor = :actorId AND rev_timestamp > (now()-INTERVAL 730 day)
                        AND rev_timestamp < now()
                UNION
                SELECT 'aiv' AS source, COUNT(*) AS value FROM $revisionTable r
                  INNER JOIN $pageTable p on p.page_id = r.rev_page
                    WHERE p.page_title LIKE 'Administrator_intervention_against_vandalism%'
                        AND r.rev_actor = :actorId
                UNION
                SELECT 'edit-summaries' AS source, COUNT(*) AS value
                FROM $revisionTable JOIN $pageTable ON rev_page = page_id
                    WHERE page_namespace = 0 AND rev_actor = :actorId
                UNION
                SELECT 'namespaces' AS source, count(*) AS value
                FROM $revisionTable JOIN $pageTable ON rev_page = page_id
                    WHERE rev_actor = :actorId AND page_namespace = 0
                UNION
                SELECT 'pages-created-live' AS source, COUNT(*) AS value FROM $revisionTable
                    WHERE rev_actor = :actorId AND rev_parent_id = 0
                UNION
                SELECT 'pages-created-deleted' AS source, COUNT(*) AS value FROM $archiveTable
                    WHERE ar_actor = :actorId AND ar_parent_id = 0
                UNION
                SELECT 'rpp' AS source, COUNT(*) AS value FROM $revisionTable r
                  INNER JOIN $pageTable p on p.page_id = r.rev_page
                    WHERE p.page_title LIKE 'Requests_for_page_protection%'
                        AND r.rev_actor = :actorId;";

        return $this->executeProjectsQuery($project, $sql, [
            'username' => $user->getUsername(),
            'actorId' => $user->getActorId($project),
        ])->fetchAllAssociative();
    }
}
