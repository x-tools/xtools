<?php
/**
 * This file contains only the AdminStats class.
 */

declare(strict_types = 1);

namespace AppBundle\Model;

/**
 * AdminStats returns information about users with rights defined in admin_stats.yml.
 */
class AdminStats extends Model
{

    /** @var string[][] Keyed by user name, values are arrays containing actions and counts. */
    protected $adminStats;

    /** @var string[] Keys are user names, values are their user groups. */
    protected $usersAndGroups;

    /** @var int Number of users in the relevant group who made any actions within the time period. */
    protected $numWithActions = 0;

    /** @var string[] Usernames of users who are in the relevant user group (sysop for admins, etc.). */
    private $usersInGroup = [];

    /** @var string Type that we're getting stats for (admin, patrollers, stewards, etc.). See admin_stats.yml */
    private $type;

    /** @var string[] Which actions to show ('block', 'protect', etc.) */
    private $actions;

    /**
     * AdminStats constructor.
     * @param Project $project
     * @param int $start as UTC timestamp.
     * @param int $end as UTC timestamp.
     * @param string $group Which user group to get stats for. Refer to admin_stats.yml for possible values.
     * @param string[] $actions Which actions to query for ('block', 'protect', etc.). Null for all actions.
     */
    public function __construct(
        Project $project,
        int $start,
        int $end,
        string $group,
        array $actions
    ) {
        $this->project = $project;
        $this->start = $start;
        $this->end = $end;
        $this->type = $group;
        $this->actions = $actions;
    }

    /**
     * Get the group for this AdminStats.
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the user_group from the config given the 'group'.
     * @return string
     */
    public function getRelevantUserGroup(): string
    {
        // Quick cache, valid only for the same request.
        static $relevantUserGroup = '';
        if ('' !== $relevantUserGroup) {
            return $relevantUserGroup;
        }

        return $relevantUserGroup = $this->getRepository()->getRelevantUserGroup($this->type);
    }

    /**
     * Get the array of statistics for each qualifying user. This may be called ahead of self::getStats() so certain
     * class-level properties will be supplied (such as self::numUsers(), which is called in the view before iterating
     * over the master array of statistics).
     * @return string[]
     */
    public function prepareStats(): array
    {
        if (isset($this->adminStats)) {
            return $this->adminStats;
        }

        $stats = $this->getRepository()
            ->getStats($this->project, $this->start, $this->end, $this->type, $this->actions);

        // Group by username.
        $stats = $this->groupStatsByUsername($stats);

        // Resort, as for some reason the SQL doesn't do this properly.
        uasort($stats, function ($a, $b) {
            if ($a['total'] === $b['total']) {
                return 0;
            }
            return $a['total'] < $b['total'] ? 1 : -1;
        });

        $this->adminStats = $stats;
        return $this->adminStats;
    }

    /**
     * Get users of the project that are capable of making the relevant actions,
     * keyed by user name, with the user groups as the values.
     * @return string[][]
     */
    public function getUsersAndGroups(): array
    {
        if (isset($this->usersAndGroups)) {
            return $this->usersAndGroups;
        }

        /**
         * All the user groups that are considered capable of making the relevant actions for $this->group.
         * @var string[]
         */
        $groupUserGroups = $this->getRepository()->getUserGroups($this->project, $this->type);

        $this->usersAndGroups = $this->project->getUsersInGroups($groupUserGroups['local'], $groupUserGroups['global']);
        dump($this->usersAndGroups);

        // Populate $this->usersInGroup with users who are in the relevant user group for $this->group.
        $this->usersInGroup = array_keys(array_filter($this->usersAndGroups, function ($groups) {
            return in_array($this->getRelevantUserGroup(), $groups);
        }));

        return $this->usersAndGroups;
    }

    /**
     * Get all user groups with permissions applicable to the $this->group.
     * @return array Each entry contains 'name' (user group) and 'rights' (the permissions).
     */
    public function getUserGroupIcons(): array
    {
        // Quick cache, valid only for the same request.
        static $userGroupIcons = null;
        if (null !== $userGroupIcons) {
            return $userGroupIcons;
        }
        $userGroupIcons = $this->getRepository()->getUserGroupIcons();
        return $userGroupIcons;
    }

    /**
     * The number of days we're spanning between the start and end date.
     * @return int
     */
    public function numDays(): int
    {
        return (int)(($this->end - $this->start) / 60 / 60 / 24);
    }

    /**
     * Get the master array of statistics for each qualifying user.
     * @return string[]
     */
    public function getStats(): array
    {
        if (isset($this->adminStats)) {
            $this->adminStats = $this->prepareStats();
        }
        return $this->adminStats;
    }

    /**
     * Get the actions that are shown as columns in the view.
     * @return string[] Each the i18n key of the action.
     */
    public function getActions(): array
    {
        return count($this->getStats()) > 0
            ? array_diff(array_keys(array_values($this->getStats())[0]), ['username', 'user-groups', 'total'])
            : [];
    }

    /**
     * Given the data returned by AdminStatsRepository::getStats, return the stats keyed by user name,
     * adding in a key/value for user groups.
     * @param string[][] $data As retrieved by AdminStatsRepository::getStats
     * @return string[] Stats keyed by user name.
     * Functionality covered in test for self::getStats().
     * @codeCoverageIgnore
     */
    private function groupStatsByUsername(array $data): array
    {
        $usersAndGroups = $this->getUsersAndGroups();
        $users = [];

        foreach ($data as $datum) {
            $username = $datum['username'];

            // Push to array containing all users with admin actions.
            // We also want numerical values to be integers.
            $users[$username] = array_map('intval', $datum);

            // Push back username which was casted to an integer.
            $users[$username]['username'] = $username;

            // Set the 'user-groups' property with the user groups they belong to (if any),
            // going off of self::getUsersAndGroups().
            if (isset($usersAndGroups[$username])) {
                $users[$username]['user-groups'] = $usersAndGroups[$username];
            } else {
                $users[$username]['user-groups'] = [];
            }

            // Keep track of users who are not in the relevant user group but made applicable actions.
            if (in_array($username, $this->usersInGroup)) {
                $this->numWithActions++;
            }
        }

        return $users;
    }

    /**
     * Get the total number of users in the relevant user group.
     * @return int
     */
    public function getNumInRelevantUserGroup(): int
    {
        return count($this->usersInGroup);
    }

    /**
     * Number of users who made any relevant actions within the time period.
     * @return int
     */
    public function getNumWithActions(): int
    {
        return $this->numWithActions;
    }

    /**
     * Number of currently users who made any actions within the time period who are not in the relevant user group.
     * @return int
     */
    public function getNumWithActionsNotInGroup(): int
    {
        return count($this->adminStats) - $this->numWithActions;
    }
}
