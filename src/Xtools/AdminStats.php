<?php
/**
 * This file contains only the AdminStats class.
 */

namespace Xtools;

use Symfony\Component\DependencyInjection\Container;
use DateTime;

/**
 * AdminStats returns information about users with administrative
 * rights on a given wiki.
 */
class AdminStats extends Model
{
    /** @var Project Project associated with this AdminStats instance. */
    protected $project;

    /** @var string[] Keyed by user name, values are arrays containing actions and counts. */
    protected $adminStats;

    /**
     * Keys are user names, values are their abbreviated user groups.
     * If abbreviations are turned on, this will instead be a string of the abbreviated
     * user groups, separated by slashes.
     * @var string[]|string
     */
    protected $adminsAndGroups;

    /** @var int Number of admins (sysops) who haven't made any actions within the time period. */
    protected $adminsWithoutActions = 0;

    /** @var int Number of actual admins (sysops), not users with admin-like actions. */
    protected $numSysops = 0;

    /** @var int Start of time period as UTC timestamp */
    protected $start;

    /** @var int End of time period as UTC timestamp */
    protected $end;

    /**
     * TopEdits constructor.
     * @param Project $project
     * @param int $start as UTC timestamp.
     * @param int $end as UTC timestamp.
     */
    public function __construct(Project $project, $start = null, $end = null)
    {
        $this->project = $project;
        $this->start = $start;
        $this->end = $end;
    }

    /**
     * Get users of the project that are capable of making 'admin actions',
     * keyed by user name with abbreviations for the user groups as the values.
     * @param  string $abbreviate If set, the keys of the result with be a string containing
     *   abbreviated versions of their user groups, such as 'A' instead of administrator,
     *   'CU' instead of CheckUser, etc. If $abbreviate is false, the keys of the result
     *   will be an array of the full-named user groups.
     * @see Project::getAdmins()
     * @return string[]
     */
    public function getAdminsAndGroups($abbreviate = true)
    {
        if ($this->adminsAndGroups) {
            return $this->adminsAndGroups;
        }

        /**
         * Each user group that is considered capable of making 'admin actions'.
         * @var string[]
         */
        $adminGroups = $this->getRepository()->getAdminGroups($this->project);

        /** @var array Keys are the usernames, values are thier user groups. */
        $admins = $this->project->getUsersInGroups($adminGroups);

        if ($abbreviate === false) {
            return $admins;
        }

        /**
         * Keys are the database-stored names, values are the abbreviations.
         * FIXME: i18n this somehow.
         * @var string[]
         */
        $userGroupAbbrMap = [
            'sysop' => 'A',
            'bureaucrat' => 'B',
            'steward' => 'S',
            'checkuser' => 'CU',
            'oversight' => 'OS',
            'bot' => 'Bot',
        ];

        foreach ($admins as $admin => $groups) {
            $abbrGroups = [];

            // Keep track of actual number of sysops.
            if (in_array('sysop', $groups)) {
                $this->numSysops++;
            }

            foreach ($groups as $group) {
                if (isset($userGroupAbbrMap[$group])) {
                    $abbrGroups[] = $userGroupAbbrMap[$group];
                }
            }

            // Make 'A' (admin) come before 'CU' (CheckUser), etc.
            sort($abbrGroups);

            $this->adminsAndGroups[$admin] = implode('/', $abbrGroups);
        }

        return $this->adminsAndGroups;
    }

    /**
     * The number of days we're spanning between the start and end date.
     * @return int
     */
    public function numDays()
    {
        return ($this->end - $this->start) / 60 / 60 / 24;
    }

    /**
     * Get the array of statistics for each qualifying user. This may be called
     * ahead of self::getStats() so certain class-level properties will be supplied
     * (such as self::numUsers(), which is called in the view before iterating
     * over the master array of statistics).
     * @param boolean $abbreviateGroups If set, the 'groups' list will be
     *   a string with abbreivated user groups names, as opposed to an array
     *   of full-named user groups.
     * @return string[]
     */
    public function prepareStats($abbreviateGroups = true)
    {
        if (isset($this->adminStats)) {
            return $this->adminStats;
        }

        // UTC to YYYYMMDDHHMMSS.
        $startDb = date('Ymd000000', $this->start);
        $endDb = date('Ymd235959', $this->end);

        $stats = $this->getRepository()->getStats($this->project, $startDb, $endDb);

        // Group by username.
        $stats = $this->groupAdminStatsByUsername($stats, $abbreviateGroups);

        // Resort, as for some reason the SQL isn't doing this properly.
        uasort($stats, function ($a, $b) {
            if ($a['total'] === $b['total']) {
                return 0;
            }
            return ($a['total'] < $b['total']) ? 1 : -1;
        });

        $this->adminStats = $stats;
        return $this->adminStats;
    }

    /**
     * Get the master array of statistics for each qualifying user.
     * @param boolean $abbreviateGroups If set, the 'groups' list will be
     *   a string with abbreivated user groups names, as opposed to an array
     *   of full-named user groups.
     * @return string[]
     */
    public function getStats($abbreviateGroups = true)
    {
        if (isset($this->adminStats)) {
            $this->adminStats = $this->prepareStats($abbreviateGroups);
        }
        return $this->adminStats;
    }

    /**
     * Given the data returned by AdminStatsRepository::getStats,
     * return the stats keyed by user name, adding in a key/value for user groups.
     * @param  string[] $data As retrieved by AdminStatsRepository::getStats
     * @param boolean $abbreviateGroups If set, the 'groups' list will be
     *   a string with abbreivated user groups names, as opposed to an array
     *   of full-named user groups.
     * @return string[] Stats keyed by user name.
     * Functionality covered in test for self::getStats().
     * @codeCoverageIgnore
     */
    private function groupAdminStatsByUsername($data, $abbreviateGroups = true)
    {
        $adminsAndGroups = $this->getAdminsAndGroups($abbreviateGroups);
        $users = [];

        foreach ($data as $datum) {
            $username = $datum['user_name'];

            // Push to array containing all users with admin actions.
            // We also want numerical values to be integers.
            $users[$username] = array_map('intval', $datum);

            // Push back username which was casted to an integer.
            $users[$username]['user_name'] = $username;

            // Set the 'groups' property with the user groups they belong to (if any),
            // going off of self::getAdminsAndGroups().
            if (isset($adminsAndGroups[$username])) {
                $users[$username]['groups'] = $adminsAndGroups[$username];

                // Remove from actual admin list so later we can re-populate with zeros.
                unset($adminsAndGroups[$username]);
            } else {
                $users[$username]['groups'] = $abbreviateGroups ? '' : [];
            }
        }

        // Push any inactive admins back to $users with zero values.
        $users = $this->fillInactiveAdmins($users, $adminsAndGroups);

        return $users;
    }

    /**
     * Fill in inactive admins (no actions within time period), given the list of
     * remaining $adminsAndGroups from processing within self::groupAdminStatsByUsername().
     * @param  string[] $users As computed by self::groupAdminStatsByUsername().
     * @param  string[] $adminsAndGroups Remaining from self::getAdminsAndGroups().
     * @return string[]
     * @codeCoverageIgnore
     */
    private function fillInactiveAdmins($users, $adminsAndGroups)
    {
        foreach ($adminsAndGroups as $username => $groups) {
            $users[$username] = [
                'user_name' => $username,
                'delete' => 0,
                'restore' => 0,
                'block' => 0,
                'unblock' => 0,
                'protect' => 0,
                'unprotect' => 0,
                'rights' => 0,
                'import' => 0,
                'total' => 0,
                'groups' => $groups,
            ];
            $this->adminsWithoutActions++;
        }

        return $users;
    }

    /**
     * Get the formatted start date.
     * @return string
     */
    public function getStart()
    {
        return date('Y-m-d', $this->start);
    }

    /**
     * Get the formatted end date.
     * @return string
     */
    public function getEnd()
    {
        return date('Y-m-d', $this->end);
    }

    /**
     * Get the total number of admins (users currently with qualifying permissions).
     * @return int
     */
    public function numAdmins()
    {
        return $this->numSysops;
    }

    /**
     * Get the total number of users we're reporting as having made admin actions.
     * @return int
     */
    public function numUsers()
    {
        return count($this->adminStats);
    }

    /**
     * Number of admins who did make actions within the time period.
     * @return int
     */
    public function getNumAdminsWithActions()
    {
        return $this->numAdmins() - $this->adminsWithoutActions;
    }

    /**
     * Number of admins who did not make any actions within the time period.
     * @return int
     */
    public function getNumAdminsWithoutActions()
    {
        return $this->adminsWithoutActions;
    }
}
