<?php
/**
 * This file contains only the UserRights class.
 */

namespace Xtools;

use AppBundle\Helper\I18nHelper;
use DateInterval;
use DatePeriod;
use DateTime;
use Exception;

/**
 * An UserRights provides methods around parsing changes to a user's rights.
 */
class UserRights extends Model
{
    /** @var string[] Rights changes, keyed by timestamp then 'added' and 'removed'. */
    protected $rightsChanges;

    /** @var string[] Localized names of the rights. */
    protected $rightsNames;

    /** @var string[] Global rights changes, keyed by timestamp then 'added' and 'removed'. */
    protected $globalRightsChanges;

    /**
     * Get user rights changes of the given user.
     * @param Project $project
     * @param User $user
     * @return string[] Keyed by timestamp then 'added' and 'removed'.
     */
    public function getRightsChanges()
    {
        if (isset($this->rightsChanges)) {
            return $this->rightsChanges;
        }

        $logData = $this->getRepository()
            ->getRightsChanges($this->project, $this->user);

        $this->rightsChanges = $this->processRightsChanges($logData);

        return $this->rightsChanges;
    }

    /**
     * Checks the user rights log to see whether the user is an admin
     * or used to be one.
     * @return string|false One of false (never an admin), 'current' or 'former'.
     */
    public function getAdminStatus()
    {
        $rightsStates = $this->getRightsStates();

        if (in_array('sysop', $rightsStates['current'])) {
            return 'current';
        } elseif (in_array('sysop', $rightsStates['former'])) {
            return 'former';
        } else {
            return false;
        }
    }

    /**
     * Get a list of the current and former rights of the user.
     * @return array With keys 'current' and 'former'.
     */
    public function getRightsStates()
    {
        static $rightsStates = null;
        if ($rightsStates !== null) {
            return $rightsStates;
        }

        $former = [];

        foreach (array_reverse($this->getRightsChanges()) as $change) {
            $former = array_diff(
                array_merge($former, $change['removed']),
                $change['added']
            );
        }

        // Current rights are not fetched from the log because really old
        // log entries contained little or no metadata, and the rights
        // changes may be undetectable.
        $rightsStates = [
            'current' => $this->user->getUserRights($this->project),
            'former' => array_unique($former),
        ];

        return $rightsStates;
    }

    /**
     * Get a list of the current and former global rights of the user.
     * @return array With keys 'current' and 'former'.
     */
    public function getGlobalRightsStates()
    {
        $current = [];
        $former = [];

        foreach (array_reverse($this->getGlobalRightsChanges()) as $change) {
            $current = array_diff(
                array_unique(array_merge($current, $change['added'])),
                $change['removed']
            );
            $former = array_diff(
                array_unique(array_merge($former, $change['removed'])),
                $change['added']
            );
        }

        return [
            'current' => $current,
            'former' => $former,
        ];
    }

    /**
     * Get the localized names for the user groups, fetched from on-wiki system messages.
     * @return string[] Localized names keyed by database value.
     */
    public function getRightsNames()
    {
        if (isset($this->rightsNames)) {
            return $this->rightsNames;
        }

        $rightsStates = $this->getRightsStates();
        $globalRightsStates = $this->getGlobalRightsStates();
        $rightsToCheck = array_merge(
            array_merge($rightsStates['current'], $globalRightsStates['current']),
            array_merge($rightsStates['former'], $globalRightsStates['former'])
        );

        $this->rightsNames = $this->getRepository()
            ->getRightsNames($this->project, $rightsToCheck, $this->i18n->getLang());

        return $this->rightsNames;
    }

    /**
     * Get global user rights changes of the given user.
     * @param Project $project
     * @param User $user
     * @return string[] Keyed by timestamp then 'added' and 'removed'.
     */
    public function getGlobalRightsChanges()
    {
        if (isset($this->globalRightsChanges)) {
            return $this->globalRightsChanges;
        }

        $logData = $this->getRepository()
            ->getGlobalRightsChanges($this->project, $this->user);

        $this->globalRightsChanges = $this->processRightsChanges($logData);

        return $this->globalRightsChanges;
    }

    /**
     * Process the given rights changes, sorting an putting in a human-readable format.
     * @param  array $logData As fetched with EditCounterRepository::getRightsChanges.
     * @return array
     */
    private function processRightsChanges($logData)
    {
        $rightsChanges = [];

        foreach ($logData as $row) {
            $unserialized = @unserialize($row['log_params']);
            if ($unserialized !== false) {
                $old = $unserialized['4::oldgroups'];
                $new = $unserialized['5::newgroups'];
                $added = array_diff($new, $old);
                $removed = array_diff($old, $new);

                $rightsChanges = $this->setAutoRemovals($rightsChanges, $row, $unserialized, $added);
            } else {
                // This is the old school format the most likely contains
                // the list of rights additions as a comma-separated list.
                try {
                    list($old, $new) = explode("\n", $row['log_params']);
                    $old = array_filter(array_map('trim', explode(',', $old)));
                    $new = array_filter(array_map('trim', explode(',', $new)));
                    $added = array_diff($new, $old);
                    $removed = array_diff($old, $new);
                } catch (Exception $e) {
                    // Really, really old school format that may be missing metadata
                    // altogether. Here we'll just leave $added and $removed empty.
                    $added = [];
                    $removed = [];
                }
            }

            // Remove '(none)'.
            if (in_array('(none)', $added)) {
                array_splice($added, array_search('(none)', $added), 1);
            }
            if (in_array('(none)', $removed)) {
                array_splice($removed, array_search('(none)', $removed), 1);
            }

            $rightsChanges[$row['log_timestamp']] = [
                'logId' => $row['log_id'],
                'admin' => $row['log_user_text'],
                'comment' => $row['log_comment'],
                'added' => array_values($added),
                'removed' => array_values($removed),
                'automatic' => $row['log_action'] === 'autopromote',
                'type' => $row['type'],
            ];
        }

        krsort($rightsChanges);

        return $rightsChanges;
    }

    /**
     * Check the given log entry for rights changes that are set to automatically expire,
     * and add entries to $rightsChanges accordingly.
     * @param array $rightsChanges
     * @param array $row Log entry row from database.
     * @param array $params Unserialized log params.
     * @param string[] $added List of added user rights.
     * @return array Modified $rightsChanges.
     */
    private function setAutoRemovals($rightsChanges, $row, $params, $added)
    {
        foreach ($added as $index => $entry) {
            if (!isset($params['newmetadata'][$index]) ||
                !array_key_exists('expiry', $params['newmetadata'][$index]) ||
                empty($params['newmetadata'][$index]['expiry'])
            ) {
                continue;
            }

            $expiry = $params['newmetadata'][$index]['expiry'];

            if (isset($rightsChanges[$expiry]) && !in_array($entry, $rightsChanges[$expiry]['removed'])) {
                $rightsChanges[$expiry]['removed'][] = $entry;
            } else {
                $rightsChanges[$expiry] = [
                    'logId' => $row['log_id'],
                    'admin' => $row['log_user_text'],
                    'comment' => null,
                    'added' => [],
                    'removed' => [$entry],
                    'automatic' => true,
                    'type' => $row['type'],
                ];
            }
        }

        return $rightsChanges;
    }
}
