<?php
/**
 * This file contains only the AutoEdits class.
 */

namespace Xtools;

use DateTime;

/**
 * AutoEdits returns statistics about automated edits made by a user.
 */
class AutoEdits extends Model
{
    /** @var Project The project. */
    protected $project;

    /** @var User The user. */
    protected $user;

    /** @var int Which namespace we are querying for. */
    protected $namespace;

    /** @var DateTime Start date. */
    protected $start;

    /** @var DateTime End date. */
    protected $end;

    /**
     * Constructor for the AutoEdit class.
     * @param Project $project
     * @param User    $user
     * @param string  $namespace Namespace ID or 'all'
     * @param string  $start Start date in a format accepted by strtotime()
     * @param string  $end End date in a format accepted by strtotime()
     */
    public function __construct(Project $project, User $user, $namespace = 'all', $start = '', $end = '')
    {
        $this->project = $project;
        $this->user = $user;
        $this->namespace = $namespace;
        $this->start = $start;
        $this->end = $end;
    }

    /**
     * Get the number of edits this user made using semi-automated tools.
     * @return int Result of query, see below.
     */
    public function countAutomatedEdits()
    {
        return (int) $this->getRepository()->countAutomatedEdits(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end
        );
    }

    /**
     * Get non-automated contributions for this user.
     * @param int $offset Used for pagination, offset results by N edits.
     * @return array[] Result of query, with columns (string) 'full_page_title' including namespace,
     *   (string) 'page_title', (int) 'page_namespace', (int) 'rev_id', (DateTime) 'timestamp',
     *   (bool) 'minor', (int) 'length', (int) 'length_change', (string) 'comment'
     */
    public function getNonAutomatedEdits($offset = 0)
    {
        $revs = $this->getRepository()->getNonAutomatedEdits(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end,
            $offset
        );

        $namespaces = $this->project->getNamespaces();

        return array_map(function ($rev) use ($namespaces) {
            $pageTitle = $rev['page_title'];
            $fullPageTitle = '';

            if ($rev['page_namespace'] !== '0') {
                $fullPageTitle = $namespaces[$rev['page_namespace']] . ":$pageTitle";
            } else {
                $fullPageTitle = $pageTitle;
            }

            return [
                'full_page_title' => $fullPageTitle,
                'page_title' => $pageTitle,
                'page_namespace' => (int) $rev['page_namespace'],
                'rev_id' => (int) $rev['rev_id'],
                'timestamp' => DateTime::createFromFormat('YmdHis', $rev['timestamp']),
                'minor' => (bool) $rev['minor'],
                'length' => (int) $rev['length'],
                'length_change' => (int) $rev['length_change'],
                'comment' => $rev['comment'],
            ];
        }, $revs);
    }

    /**
     * Get counts of known automated tools used by the given user.
     * @return string[] Each tool that they used along with the count and link:
     *                  [
     *                      'Twinkle' => [
     *                          'count' => 50,
     *                          'link' => 'Wikipedia:Twinkle',
     *                      ],
     *                  ]
     */
    public function getAutomatedCounts()
    {
        return $this->getRepository()->getAutomatedCounts(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end
        );
    }
}
