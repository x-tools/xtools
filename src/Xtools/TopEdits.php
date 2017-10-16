<?php
/**
 * This file contains only the TopEdits class.
 */

namespace Xtools;

use Symfony\Component\DependencyInjection\Container;
use DateTime;

/**
 * TopEdits returns the top-edited pages by a user.
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

        if ($this->namespace === 'all') {
            $pages = $this->getRepository()->getTopEditsAllNamespaces(
                $this->project,
                $this->user,
                $this->limit
            );
        } else {
            $pages = $this->getRepository()->getTopEditsNamespace(
                $this->project,
                $this->user,
                $this->namespace,
                $this->limit
            );
        }

        $this->topEdits = $this->formatTopPages($pages);
        return $this->topEdits;
    }

    /**
     * [formatTopPages description]
     * @param  string[] $pages As returned by TopEditsRepository::getTopEditsNamespace
     *                         or TopEditsRepository::getTopEditsAllNamespaces.
     * @return string[] Same as input but with 'displaytitle', and 'page_title_ns'.
     */
    private function formatTopPages($pages)
    {
        /** @var string[] The top edited pages, keyed by namespace ID. */
        $topEditedPages = [];

        /** @var string[] Display titles of the pages, which need to be fetched ahead of time. */
        $displayTitles = $this->getDisplayTitles($pages);

        foreach ($pages as $page) {
            $nsId = (int) $page['page_namespace'];
            $nsTitle = $nsId > 0 ? $this->project->getNamespaces()[$page['page_namespace']] . ':' : '';
            $pageTitle = $nsTitle . $page['page_title'];
            $page['displaytitle'] = $displayTitles[$pageTitle];

            // $page['page_title'] is retained without the namespace
            //  so we can link to TopEdits for that page.
            $page['page_title_ns'] = $pageTitle;

            if (isset($topEditedPages[$nsId])) {
                $topEditedPages[$nsId][] = $page;
            } else {
                $topEditedPages[$nsId] = [$page];
            }
        }

        return $topEditedPages;
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
