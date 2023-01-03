<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Model\Page;
use App\Model\Project;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * A LargestPagesRepository is responsible for retrieving information from the database for the LargestPages tool.
 * @codeCoverageIgnore
 */
class LargestPagesRepository extends Repository
{
    protected PageRepository $pageRepo;

    /**
     * @param ContainerInterface $container
     * @param CacheItemPoolInterface $cache
     * @param Client $guzzle
     * @param LoggerInterface $logger
     * @param bool $isWMF
     * @param int $queryTimeout
     * @param PageRepository $pageRepo
     */
    public function __construct(
        ContainerInterface $container,
        CacheItemPoolInterface $cache,
        Client $guzzle,
        LoggerInterface $logger,
        bool $isWMF,
        int $queryTimeout,
        PageRepository $pageRepo
    ) {
        $this->pageRepo = $pageRepo;
        parent::__construct($container, $cache, $guzzle, $logger, $isWMF, $queryTimeout);
    }

    /** @var int Max rows to display. */
    public const MAX_ROWS = 500;

    private function getLikeSql(string &$includePattern, string &$excludePattern): string
    {
        $sql = '';

        if ($includePattern) {
            $sql .= "page_title LIKE :include_pattern ";
            $includePattern = str_replace(' ', '_', $includePattern);
        }
        if ($excludePattern) {
            if ($includePattern) {
                $sql .= ' AND ';
            }
            $sql .= "page_title NOT LIKE :exclude_pattern ";
            $excludePattern = str_replace(' ', '_', $excludePattern);
        }

        return $sql;
    }

    /**
     * Fetches the largest pages for the given project.
     * @param Project $project
     * @param int|string $namespace Namespace ID or 'all' for all namespaces.
     * @param string $includePattern Either regular expression (starts/ends with forward slash),
     *   or a wildcard pattern with % as the wildcard symbol.
     * @param string $excludePattern Either regular expression (starts/ends with forward slash),
     *   or a wildcard pattern with % as the wildcard symbol.
     * @return array Results with keys 'page_namespace', 'page_title', 'page_len' and 'pa_class'
     */
    public function getData(Project $project, $namespace, string $includePattern, string $excludePattern): array
    {
        $pageTable = $project->getTableName('page');

        $where = '';
        $likeCond = $this->getLikeSql($includePattern, $excludePattern);
        $namespaceCond = '';
        if ('all' !== $namespace) {
            $namespaceCond = 'page_namespace = :namespace';
            if ($likeCond) {
                $namespaceCond .= ' AND ';
            }
        }
        if ($likeCond || $namespaceCond) {
            $where = 'WHERE ';
        }

        $sql = "SELECT page_namespace, page_title, page_len
                FROM $pageTable
                $where $namespaceCond
                $likeCond
                ORDER BY page_len DESC
                LIMIT ".self::MAX_ROWS;

        $rows = $this->executeProjectsQuery($project, $sql, [
            'namespace' => $namespace,
            'include_pattern' => $includePattern,
            'exclude_pattern' => $excludePattern,
        ])->fetchAllAssociative();

        $pages = [];

        foreach ($rows as $row) {
            $pages[] = Page::newFromRow($this->pageRepo, $project, $row);
        }

        return $pages;
    }
}
