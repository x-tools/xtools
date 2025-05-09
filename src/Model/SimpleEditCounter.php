<?php

declare(strict_types = 1);

namespace App\Model;

use App\Repository\SimpleEditCounterRepository;

/**
 * A SimpleEditCounter provides basic edit count stats about a user.
 * This class is too 'simple' to bother with tests, we just get the results of the query and return them.
 * @codeCoverageIgnore
 */
class SimpleEditCounter extends Model
{
    /** @var bool Whether only limited results are given (due to high edit count). */
    private bool $limited = false;

    /** @var array The Simple Edit Counter results. */
    protected array $data = [
        'user_id' => null,
        'deleted_edit_count' => 0,
        'live_edit_count' => 0,
        'user_groups' => [],
        'global_user_groups' => [],
        'creation_count' => 0,
    ];

    /**
     * Constructor for the SimpleEditCounter class.
     * @param Project $project
     * @param User $user
     * @param string|int|null $namespace Namespace ID or 'all'.
     * @param false|int $start As Unix timestamp.
     * @param false|int $end As Unix timestamp.
     */
    public function __construct(
        SimpleEditCounterRepository $repository,
        Project $project,
        User $user,
        $namespace = 'all',
        $start = false,
        $end = false
    ) {
        $this->repository = $repository;
        $this->project = $project;
        $this->user = $user;

        if ($this->user->getEditCount($this->project) > $this->user->maxEdits()) {
            $this->limited = true;
            $this->namespace = 'all';
            $this->start = false;
            $this->end = false;
        } else {
            $this->namespace = '' == $namespace ? 0 : $namespace;
            $this->start = $start;
            $this->end = $end;
        }
    }

    /**
     * Fetch the data from the database and API,
     * then set class properties with the values.
     */
    public function prepareData(): void
    {
        if ($this->limited) {
            $this->data = [
                'user_id' => $this->user->getId($this->project),
                'total_edit_count' => $this->user->getEditCount($this->project),
                'user_groups' => $this->user->getUserRights($this->project),
                'approximate' => true,
                'namespace' => 'all',
            ];
        } else {
            $this->prepareFullData();
        }

        if (!$this->user->isAnon($this->project)) {
            $this->data['global_user_groups'] = $this->user->getGlobalUserRights($this->project);
        }
    }

    private function prepareFullData(): void
    {
        $results = $this->repository->fetchData(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end
        );

        // Iterate over the results, putting them in the right variables
        foreach ($results as $row) {
            switch ($row['source']) {
                case 'id':
                    $this->data['user_id'] = (int)$row['value'];
                    break;
                case 'arch':
                    $this->data['deleted_edit_count'] = (int)$row['value'];
                    break;
                case 'rev':
                    $this->data['live_edit_count'] = (int)$row['value'];
                    break;
                case 'groups':
                    $this->data['user_groups'][] = $row['value'];
                    break;
                case 'creations':
                    $this->data['creation_count'] = (int)$row['value'];
                    break;
            }
        }

        if ($this->user->isTemp($this->project)) {
            $this->data['user_groups'] = ['temp'];
        }
    }

    /**
     * Get back all the data as a single associative array.
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the user's ID.
     * @return int
     */
    public function getUserId(): int
    {
        return $this->data['user_id'];
    }

    /**
     * Get the number of deleted edits.
     * @return int
     */
    public function getDeletedEditCount(): int
    {
        return $this->data['deleted_edit_count'];
    }

    /**
     * Get number of page creations
     * @return int
     */
    public function getCreationCount() : int
    {
        return $this->data['creation_count'];
    }

    /**
     * Get the number of live edits.
     * @return int
     */
    public function getLiveEditCount(): int
    {
        return $this->data['live_edit_count'];
    }

    /**
     * Get the total number of edits.
     * @return int
     */
    public function getTotalEditCount(): int
    {
        return $this->data['total_edit_count'] ?? $this->data['deleted_edit_count'] + $this->data['live_edit_count'];
    }

    /**
     * Get the local user groups.
     * @return string[]
     */
    public function getUserGroups(): array
    {
        return $this->data['user_groups'];
    }

    /**
     * Get the global user groups.
     * @return string[]
     */
    public function getGlobalUserGroups(): array
    {
        return $this->data['global_user_groups'];
    }

    /**
     * Whether or not only limited, approximate data is provided.
     * @return bool
     */
    public function isLimited(): bool
    {
        return $this->limited;
    }
}
