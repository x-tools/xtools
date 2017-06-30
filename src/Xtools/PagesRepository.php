<?php
/**
 * This file contains only the PagesRepository class.
 */

namespace Xtools;

use Mediawiki\Api\SimpleRequest;

/**
 * A PagesRepository fetches data about Pages, either singularly or for multiple.
 */
class PagesRepository extends Repository
{

    /**
     * Get metadata about a single page from the API.
     * @param Project $project The project to which the page belongs.
     * @param string $pageTitle Page title.
     * @return string[] Array with some of the following keys: pageid, title, missing, displaytitle,
     * url.
     */
    public function getPageInfo(Project $project, $pageTitle)
    {
        $info = $this->getPagesInfo($project, [$pageTitle]);
        return array_shift($info);
    }

    /**
     * Get metadata about a set of pages from the API.
     * @param Project $project The project to which the pages belong.
     * @param string[] $pageTitles Array of page titles.
     * @return string[] Array keyed by the page names, each element with some of the
     * following keys: pageid, title, missing, displaytitle, url.
     */
    public function getPagesInfo(Project $project, $pageTitles)
    {
        // @TODO: Also include 'extlinks' prop when we start checking for dead external links.
        $params = [
            'prop' => 'info|pageprops',
            'inprop' => 'protection|talkid|watched|watchers|notificationtimestamp|subjectid|url|readable|displaytitle',
            'converttitles' => '',
            // 'ellimit' => 20,
            // 'elexpandurl' => '',
            'titles' => join('|', $pageTitles),
            'formatversion' => 2
            // 'pageids' => $pageIds // FIXME: allow page IDs
        ];

        $query = new SimpleRequest('query', $params);
        $api = $this->getMediawikiApi($project);
        $res = $api->getRequest($query);
        $result = [];
        if (isset($res['query']['pages'])) {
            foreach ($res['query']['pages'] as $pageInfo) {
                $result[$pageInfo['title']] = $pageInfo;
            }
        }
        return $result;
    }

    /**
     * Get revisions of a single page.
     * @param Page $page The page.
     * @param User|null $user Specify to get only revisions by the given user.
     * @return string[] Each member with keys: id, timestamp, length-
     */
    public function getRevisions(Page $page, User $user = null)
    {
        $revTable = $this->getTableName($page->getProject()->getDatabaseName(), 'revision');
        $userClause = $user ? "revs.rev_user_text in (:username) AND " : "";

        $query = "SELECT
                    revs.rev_id AS id,
                    revs.rev_timestamp AS timestamp,
                    revs.rev_minor_edit AS minor,
                    revs.rev_len AS length,
                    (CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS length_change,
                    revs.rev_user AS user_id,
                    revs.rev_user_text AS username,
                    revs.rev_comment AS comment
                FROM $revTable AS revs
                LEFT JOIN $revTable AS parentrevs ON (revs.rev_parent_id = parentrevs.rev_id)
                WHERE $userClause revs.rev_page = :pageid
                ORDER BY revs.rev_timestamp ASC
            ";
        $params = ['pageid' => $page->getId()];
        if ($user) {
            $params['username'] = $user->getUsername();
        }
        $conn = $this->getProjectsConnection();
        return $conn->executeQuery($query, $params)->fetchAll();
    }

    /**
     * Get a count of the number of revisions of a single page
     * @param Page $page The page.
     * @param User|null $user Specify to only count revisions by the given user.
     * @return int
     */
    public function getNumRevisions(Page $page, User $user = null)
    {
        $revTable = $this->getTableName($page->getProject()->getDatabaseName(), 'revision');
        $userClause = $user ? "rev_user_text in (:username) AND " : "";

        $query = "SELECT COUNT(*)
                FROM $revTable
                WHERE $userClause rev_page = :pageid
            ";
        $params = ['pageid' => $page->getId()];
        if ($user) {
            $params['username'] = $user->getUsername();
        }
        $conn = $this->getProjectsConnection();
        return $conn->executeQuery($query, $params)->fetchColumn(0);
    }

    /**
     * Get assessment data for the given pages
     * @param Project   $project The project to which the pages belong.
     * @param  int[]    $pageIds Page IDs
     * @return string[] Assessment data as retrieved from the database.
     */
    public function getAssessments(Project $project, $pageIds)
    {
        if (!$project->hasPageAssessments()) {
            return [];
        }
        $pageAssessmentsTable = $this->getTableName($project->getDatabaseName(), 'page_assessments');
        $pageIds = implode($pageIds, ',');

        $query = "SELECT pap_project_title AS wikiproject, pa_class AS class, pa_importance AS importance
                  FROM page_assessments
                  LEFT JOIN page_assessments_projects ON pa_project_id = pap_project_id
                  WHERE pa_page_id IN ($pageIds)";

        $conn = $this->getProjectsConnection();
        return $conn->executeQuery($query)->fetchAll();
    }
}
