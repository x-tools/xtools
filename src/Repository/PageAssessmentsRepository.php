<?php
/**
 * This file contains only the PageAssessmentsRepository class.
 */

declare(strict_types = 1);

namespace App\Repository;

use App\Model\Page;
use App\Model\Project;

/**
 * An PageAssessmentsRepository is responsible for retrieving page assessment
 * information from the database, and the XTools configuration via the Container.
 * @codeCoverageIgnore
 */
class PageAssessmentsRepository extends Repository
{
    /**
     * Get page assessments configuration for the Project.
     * @param Project $project
     * @return string[]|bool As defined in config/assessments.yml, or false if none exists.
     */
    public function getConfig(Project $project)
    {
        $projectsConfig = $this->container->getParameter('assessments');
        return $projectsConfig[$project->getDomain()] ?? false;
    }

    /**
     * Get assessment data for the given pages
     * @param Page $page
     * @param bool $first Fetch only the first result, not for each WikiProject.
     * @return string[] Assessment data as retrieved from the database.
     */
    public function getAssessments(Page $page, bool $first = false): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_assessments');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $paTable = $this->getTableName($page->getProject()->getDatabaseName(), 'page_assessments');
        $papTable = $this->getTableName($page->getProject()->getDatabaseName(), 'page_assessments_projects');
        $pageId = $page->getId();

        $sql = "SELECT pap_project_title AS wikiproject, pa_class AS class, pa_importance AS importance
                FROM $paTable
                LEFT JOIN $papTable ON pa_project_id = pap_project_id
                WHERE pa_page_id = $pageId";

        if ($first) {
            $sql .= "\nAND pa_class != '' LIMIT 1";
        }

        $result = $this->executeProjectsQuery($page->getProject(), $sql)->fetchAll();

        // Cache and return.
        return $this->setCache($cacheKey, $result);
    }
}
