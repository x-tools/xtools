<?php
/**
 * This file contains only the TopEdits class.
 */

namespace Xtools;

use Symfony\Component\DependencyInjection\Container;
use DateTime;

/**
 * TopEdits returns the top-edited pages by a user. There is not a separate
 * repository because there is only one query :)
 */
class TopEdits extends Model
{
    /** @var Project The project. */
    protected $project;

    /** @var User The user. */
    protected $user;

    /** @var string[] Top edits object for quick caching, keyed by namespace ID. */
    protected $topEdits = [];

    /** @var int Number of rows to fetch. */
    protected $limit;

    /** @var int Which namespace we are querying for. */
    protected $namespace;

    const DEFAULT_LIMIT_SINGLE_NAMESPACE = 100;
    const DEFAULT_LIMIT_ALL_NAMESPACES = 20;

    /**
     * TopEdits constructor.
     * @param Project $project
     * @param User $user
     * @param string|int Namespace ID or 'all'.
     * @param int $limit Number of rows to fetch. This defaults to
     *   DEFAULT_LIMIT_SINGLE_NAMESPACE if $this->namespace is a single namespace (int),
     *   and DEFAULT_LIMIT_ALL_NAMESPACES if $this->namespace is 'all'.
     */
    public function __construct(Project $project, User $user, $namespace = 0, $limit = null)
    {
        $this->project = $project;
        $this->user = $user;
        $this->namespace = $namespace === 'all' ? 'all' : (int)$namespace;

        if ($limit) {
            $this->limit = $limit;
        } else {
            $this->limit = $this->namespace === 'all'
                ? self::DEFAULT_LIMIT_ALL_NAMESPACES
                : self::DEFAULT_LIMIT_SINGLE_NAMESPACE;
        }
    }

    /**
     * Get the limit set on number of rows to fetch.
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Get the namespace set on the instance.
     * @return int|string Namespace ID or 'all'.
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Get the top edits by a user in the given namespace, or 'all' namespaces.
     * This is the public function that should be used.
     * @return string[] Results of self::getTopEditsByNamespace(), keyed by namespace.
     */
    public function getTopEdits()
    {
        if (count($this->topEdits) > 0) {
            return $this->topEdits;
        }

        /** @var string[] The top edited pages, keyed by namespace ID. */
        $topEditedPages = [];

        /** @var int[] Which namespaces to iterate over. */
        $namespaces = $this->namespace === 'all'
            ? array_keys($this->project->getNamespaces())
            : [$this->namespace];

        foreach ($namespaces as $nsId) {
            $pages = $this->getTopEditsByNamespace($nsId);

            if (count($pages)) {
                $topEditedPages[$nsId] = $pages;
            }
        }

        $this->topEdits = $topEditedPages;
        return $topEditedPages;
    }

    /**
     * Get the top edits by a user in the given namespace.
     * @param int $namespace Namespace ID.
     * @return string[] page_namespace, page_title, page_is_redirect,
     *   count (number of edits), assessment (page assessment).
     */
    protected function getTopEditsByNamespace($namespace = 0)
    {
        $topPages = $this->getRepository()->getTopEdits(
            $this->project,
            $this->user,
            $namespace,
            $this->limit
        );

        // Display titles need to be fetched ahead of time.
        $displayTitles = $this->getDisplayTitles($topPages);

        $pages = [];
        foreach ($topPages as $page) {
            // If non-mainspace, prepend namespace to the titles.
            $ns = (int) $page['page_namespace'];
            $nsTitle = $ns > 0 ? $this->project->getNamespaces()[$page['page_namespace']] . ':' : '';
            $pageTitle = $nsTitle . $page['page_title'];
            $page['displaytitle'] = $displayTitles[$pageTitle];
            // $page['page_title'] is retained without the namespace
            //  so we can link to TopEdits for that page.
            $page['page_title_ns'] = $pageTitle;
            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * Get the display titles of the given pages.
     * @param  string[] $topPages As returned by $this->getRepository()->getTopEdits()
     * @return string[] Keys are the original supplied titles, and values are the display titles.
     */
    private function getDisplayTitles($topPages)
    {
        $namespaces = $this->project->getNamespaces();

        // First extract page titles including namespace.
        $pageTitles = array_map(function ($page) use ($namespaces) {
            // If non-mainspace, prepend namespace to the titles.
            $ns = $page['page_namespace'];
            $nsTitle = $ns > 0 ? $namespaces[$page['page_namespace']] . ':' : '';
            return $nsTitle . $page['page_title'];
        }, $topPages);

        return $this->getRepository()->getDisplayTitles($this->project, $pageTitles);
    }
}
