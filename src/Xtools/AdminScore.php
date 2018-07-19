<?php
/**
 * This file contains only the AdminScore class.
 */

namespace Xtools;

use DateTime;

/**
 * An AdminScore provides scores of logged actions and on-wiki activity made by a user,
 * to measure if they would be suitable as an administrator.
 */
class AdminScore extends Model
{
    /**
     * @var array Multipliers (may need review). This currently is dynamic, but should be a constant.
     */
    private $multipliers = [
        'account-age-mult' => 1.25,
        'edit-count-mult' => 1.25,
        'user-page-mult' => 0.1,
        'patrols-mult' => 1,
        'blocks-mult' => 1.4,
        'afd-mult' => 1.15,
        'recent-activity-mult' => 0.9,
        'aiv-mult' => 1.15,
        'edit-summaries-mult' => 0.8,
        'namespaces-mult' => 1.0,
        'pages-created-live-mult' => 1.4,
        'pages-created-deleted-mult' => 1.4,
        'rpp-mult' => 1.15,
        'user-rights-mult' => 0.75,
    ];

    /** @var Project The project. */
    protected $project;

    /** @var User The user. */
    protected $user;

    /** @var array The scoring results. */
    protected $scores;

    /** @var int The total of all scores. */
    protected $total;

    /**
     * AdminScore constructor.
     * @param Project $project
     * @param User $user
     */
    public function __construct(Project $project, User $user)
    {
        $this->project = $project;
        $this->user = $user;
    }

    /**
     * Get the scoring results.
     * @return array See AdminScoreRepository::getData() for the list of keys.
     */
    public function getScores()
    {
        if (isset($this->scores)) {
            return $this->scores;
        }
        $this->prepareData();
        return $this->scores;
    }

    /**
     * Get the total score.
     * @return int
     */
    public function getTotal()
    {
        if (isset($this->total)) {
            return $this->total;
        }
        $this->prepareData();
        return $this->total;
    }

    /**
     * Set the scoring results on class properties $scores and $total.
     */
    public function prepareData()
    {
        $data = $this->getRepository()->fetchData($this->project, $this->user);
        $this->total = 0;
        $this->scores = [];

        foreach ($data as $row) {
            $key = $row['source'];
            $value = $row['value'];

            // WMF Replica databases are returning binary control characters
            // This is specifically shown with WikiData.
            // More details: T197165
            $value = str_replace("\x00", "", $value);

            if ($key === 'account-age') {
                if ($value == null) {
                    $value = 0;
                } else {
                    $now = new DateTime();
                    $date = new DateTime($value);
                    $diff = $date->diff($now);
                    $formula = 365 * (int)$diff->format('%y') + 30 *
                        (int)$diff->format('%m') + (int)$diff->format('%d');
                    if ($formula < 365) {
                        $this->multipliers["account-age-mult"] = 0;
                    }
                    $value = $formula;
                }
            }

            $multiplierKey = $row['source'] . '-mult';
            $multiplier = isset($this->multipliers[$multiplierKey]) ? $this->multipliers[$multiplierKey] : 1;
            $score = max(min($value * $multiplier, 100), -100);
            $this->scores[$key]['mult'] = $multiplier;
            $this->scores[$key]['value'] = $value;
            $this->scores[$key]['score'] = $score;
            $this->total += $score;
        }
    }
}
