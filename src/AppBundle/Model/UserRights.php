<?php
/**
 * This file contains only the UserRights class.
 */

declare(strict_types = 1);

namespace AppBundle\Model;

use AppBundle\Helper\I18nHelper;
use DateInterval;
use Exception;

/**
 * An UserRights provides methods around parsing changes to a user's rights.
 */
class UserRights extends Model
{
    /** @var I18nHelper For i18n and l10n. */
    protected $i18n;

    /** @var string[] Rights changes, keyed by timestamp then 'added' and 'removed'. */
    protected $rightsChanges;

    /** @var string[] Localized names of the rights. */
    protected $rightsNames;

    /** @var string[] Global rights changes (log), keyed by timestamp then 'added' and 'removed'. */
    protected $globalRightsChanges;

    /** @var array The current and former rights of the user. */
    protected $rightsStates = [];

    /**
     * Get user rights changes of the given user.
     * @return string[] Keyed by timestamp then 'added' and 'removed'.
     */
    public function getRightsChanges(): array
    {
        if (isset($this->rightsChanges)) {
            return $this->rightsChanges;
        }

        $logData = $this->getRepository()
            ->getRightsChanges($this->project, $this->user);

        $this->rightsChanges = $this->processRightsChanges($logData);

        $acDate = $this->getAutoconfirmedTimestamp();
        if (false !== $acDate) {
            $this->rightsChanges[$acDate] = [
                'logId' => null,
                'performer' => null,
                'comment' => null,
                'added' => ['autoconfirmed'],
                'removed' => [],
                'grantType' => strtotime($acDate) > time() ? 'pending' : 'automatic',
                'type' => 'local',
            ];
            krsort($this->rightsChanges);
        }

        return $this->rightsChanges;
    }

    /**
     * Checks the user rights log to see whether the user is an admin or used to be one.
     * @return string|false One of false (never an admin), 'current' or 'former'.
     */
    public function getAdminStatus()
    {
        $rightsStates = $this->getRightsStates();

        if (in_array('sysop', $rightsStates['local']['current'])) {
            return 'current';
        } elseif (in_array('sysop', $rightsStates['local']['former'])) {
            return 'former';
        } else {
            return false;
        }
    }

    /**
     * Get a list of the current and former rights of the user.
     * @return array With keys 'local' and 'global', each with keys 'current' and 'former'.
     */
    public function getRightsStates(): array
    {
        if (count($this->rightsStates) > 0) {
            return $this->rightsStates;
        }

        foreach (['local', 'global'] as $type) {
            [$currentRights, $rightsChanges] = $this->getCurrentRightsAndChanges($type);

            $former = [];

            // We'll keep track of added rights, which we'll later compare with the
            // current rights to ensure the list of former rights is complete.
            // This is because sometimes rights were removed but there mysteriously
            // is no log entry of it.
            $added = [];

            foreach ($rightsChanges as $change) {
                $former = array_diff(
                    array_merge($former, $change['removed']),
                    $change['added']
                );

                $added = array_unique(array_merge($added, $change['added']));
            }

            // Also tag on rights that were previously added but mysteriously
            // don't have a log entry for when they were removed.
            $former = array_merge(
                array_diff($added, $currentRights),
                $former
            );

            // Remove the current rights for good measure. Autoconfirmed is a special case -- it can never be former,
            // but will end up in $former from the above code.
            $former = array_diff(array_unique($former), $currentRights, ['autoconfirmed']);

            $this->rightsStates[$type] = [
                'current' => $currentRights,
                'former' => $former,
            ];
        }

        return $this->rightsStates;
    }

    /**
     * Get a list of the current rights (of given type) and the log.
     * @param string $type 'local' or 'global'
     * @return array [string[] current rights, array rights changes].
     */
    private function getCurrentRightsAndChanges(string $type): array
    {
        // Current rights are not fetched from the log because really old
        // log entries contained little or no metadata, and the rights
        // changes may be undetectable.
        if ('local' === $type) {
            $currentRights = $this->user->getUserRights($this->project);
            $rightsChanges = $this->getRightsChanges();

            $acDate = $this->getAutoconfirmedTimestamp();
            if (false !== $acDate && strtotime($acDate) <= time()) {
                $currentRights[] = 'autoconfirmed';
            }
        } else {
            $currentRights = $this->user->getGlobalUserRights($this->project);
            $rightsChanges = $this->getGlobalRightsChanges();
        }

        return [$currentRights, $rightsChanges];
    }

    /**
     * Get a list of the current and former global rights of the user.
     * @return array With keys 'current' and 'former'.
     */
    public function getGlobalRightsStates(): array
    {
        return $this->getRightsStates()['global'];
    }

    /**
     * Get global user rights changes of the given user.
     * @return string[] Keyed by timestamp then 'added' and 'removed'.
     */
    public function getGlobalRightsChanges(): array
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
     * Get the localized names for the user groups, fetched from on-wiki system messages.
     * @return string[] Localized names keyed by database value.
     */
    public function getRightsNames(): array
    {
        if (isset($this->rightsNames)) {
            return $this->rightsNames;
        }

        $this->rightsNames = $this->getRepository()
            ->getRightsNames($this->project, $this->i18n->getLang());

        return $this->rightsNames;
    }

    /**
     * Get the localized translation for the given user right.
     * @param string $name The name of the right, such as 'sysop'.
     * @return string
     */
    public function getRightsName(string $name): string
    {
        return $this->getRightsNames()[$name] ?? $name;
    }

    /**
     * Process the given rights changes, sorting an putting in a human-readable format.
     * @param array $logData As fetched with EditCounterRepository::getRightsChanges.
     * @return array
     */
    private function processRightsChanges(array $logData): array
    {
        $rightsChanges = [];

        foreach ($logData as $row) {
            $unserialized = @unserialize($row['log_params']);
            if (false !== $unserialized) {
                $old = $unserialized['4::oldgroups'];
                $new = $unserialized['5::newgroups'];
                $added = array_diff($new, $old);
                $removed = array_diff($old, $new);
                $oldMetadata = $unserialized['oldmetadata'] ?? null;
                $newMetadata = $unserialized['newmetadata'] ?? null;

                // Check for changes only to expiry. If such exists, treat it as added. Various issets are safeguards.
                if (empty($added) && empty($removed) && isset($oldMetadata) && isset($newMetadata)) {
                    foreach ($old as $index => $right) {
                        $oldExpiry = $oldMetadata[$index]['expiry'] ?? null;
                        $newExpiry = $newMetadata[$index]['expiry'] ?? null;

                        // Check if an expiry was added, removed, or modified.
                        if ((null !== $oldExpiry && null === $newExpiry) ||
                            (null === $oldExpiry && null !== $newExpiry) ||
                            (null !== $oldExpiry && null !== $newExpiry)
                        ) {
                            $added[$index] = $right;

                            // Remove the last auto-removal(s), which must exist.
                            foreach (array_reverse($rightsChanges, true) as $timestamp => $change) {
                                if (in_array($right, $change['removed']) && !in_array($right, $change['added']) &&
                                    'automatic' === $change['grantType']
                                ) {
                                    unset($rightsChanges[$timestamp]);
                                }
                            }
                        }
                    }
                }

                // If a right was removed, remove any previously pending auto-removals.
                if (count($removed) > 0) {
                    $this->unsetAutoRemoval($rightsChanges, $removed);
                }

                $this->setAutoRemovals($rightsChanges, $row, $unserialized, $added);
            } else {
                // This is the old school format the most likely contains
                // the list of rights additions as a comma-separated list.
                try {
                    [$old, $new] = explode("\n", $row['log_params']);
                    $old = array_filter(array_map('trim', explode(',', $old)));
                    $new = array_filter(array_map('trim', explode(',', (string)$new)));
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
                'performer' => 'autopromote' === $row['log_action'] ? null : $row['log_user_text'],
                'comment' => $row['log_comment'],
                'added' => array_values($added),
                'removed' => array_values($removed),
                'grantType' => 'autopromote' === $row['log_action'] ? 'automatic' : 'manual',
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
     */
    private function setAutoRemovals(array &$rightsChanges, array $row, array $params, array $added): void
    {
        foreach ($added as $index => $entry) {
            // Skip if no expiry was set.
            if (!isset($params['newmetadata'][$index]) ||
                !array_key_exists('expiry', $params['newmetadata'][$index]) ||
                empty($params['newmetadata'][$index]['expiry'])
            ) {
                continue;
            }

            $expiry = $params['newmetadata'][$index]['expiry'];

            if (isset($rightsChanges[$expiry]) && !in_array($entry, $rightsChanges[$expiry]['removed'])) {
                // Temporary right expired.
                $rightsChanges[$expiry]['removed'][] = $entry;
            } else {
                // Temporary right was added.
                $rightsChanges[$expiry] = [
                    'logId' => $row['log_id'],
                    'performer' => $row['log_user_text'],
                    'comment' => null,
                    'added' => [],
                    'removed' => [$entry],
                    'grantType' => strtotime($expiry) > time() ? 'pending' : 'automatic',
                    'type' => $row['type'],
                ];
            }
        }

        // Resort because the auto-removal timestamp could be before other rights changes.
        ksort($rightsChanges);
    }

    private function unsetAutoRemoval(array &$rightsChanges, array $removed): void
    {
        foreach ($rightsChanges as $timestamp => $change) {
            if ('pending' === $change['grantType']) {
                $rightsChanges[$timestamp]['removed'] = array_diff($rightsChanges[$timestamp]['removed'], $removed);
                if (empty($rightsChanges[$timestamp]['removed'])) {
                    unset($rightsChanges[$timestamp]);
                }
            }
        }
    }

    /**
     * Get the timestamp of when the user became autoconfirmed.
     * @return string|false YmdHis format, or false if date is in the future or if AC status could not be determined.
     */
    private function getAutoconfirmedTimestamp()
    {
        static $acTimestamp = null;
        if (null !== $acTimestamp) {
            return $acTimestamp;
        }

        $thresholds = $this->getRepository()->getAutoconfirmedAgeAndCount($this->project);

        // Happens for non-WMF installations, or if there is no autoconfirmed status.
        if (null === $thresholds) {
            return false;
        }

        $registrationDate = $this->user->getRegistrationDate($this->project);

        // Sometimes for old accounts the registration date is null, in which case
        // we won't attempt to find out when they were autoconfirmed.
        if (!is_a($registrationDate, 'DateTime')) {
            return false;
        }

        $regDateImmutable = new \DateTimeImmutable(
            $registrationDate->format('YmdHis')
        );

        $acDate = $regDateImmutable->add(DateInterval::createFromDateString(
            $thresholds['wgAutoConfirmAge'].' seconds'
        ))->format('YmdHis');

        // First check if they already had 10 edits made as of $acDate
        $editsByAcDate = $this->getRepository()->getNumEditsByTimestamp(
            $this->project,
            $this->user,
            $acDate
        );

        // If more than wgAutoConfirmCount, then $acDate is when they became autoconfirmed.
        if ($editsByAcDate >= $thresholds['wgAutoConfirmCount']) {
            return $acDate;
        }

        // Now check when the nth edit was made, where n is wgAutoConfirmCount.
        // This will be false if they still haven't made 10 edits.
        $acTimestamp = $this->getRepository()->getNthEditTimestamp(
            $this->project,
            $this->user,
            $registrationDate->format('YmdHis'),
            $thresholds['wgAutoConfirmCount']
        );

        return $acTimestamp;
    }
}
