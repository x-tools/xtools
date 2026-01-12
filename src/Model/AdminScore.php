<?php
declare( strict_types = 1 );

namespace App\Model;

use App\Repository\AdminScoreRepository;
use App\Repository\Repository;
use DateTime;

/**
 * An AdminScore provides scores of logged actions and on-wiki activity made by a user,
 * to measure if they would be suitable as an administrator.
 * @codeCoverageIgnore
 */
class AdminScore extends Model {
	/**
	 * @var array Multipliers (may need review). This currently is dynamic, but should be a constant.
	 */
	private array $multipliers = [
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

	/** @var array The scoring results. */
	protected array $scores;

	/** @var int The total of all scores. */
	protected int $total;

	/**
	 * AdminScore constructor.
	 * @param Repository|AdminScoreRepository $repository
	 * @param Project $project
	 * @param ?User $user
	 */
	public function __construct(
		protected Repository|AdminScoreRepository $repository,
		protected Project $project,
		protected ?User $user
	) {
	}

	/**
	 * Get the scoring results.
	 * @return array See AdminScoreRepository::getData() for the list of keys.
	 */
	public function getScores(): array {
		if ( isset( $this->scores ) ) {
			return $this->scores;
		}
		$this->prepareData();
		return $this->scores;
	}

	/**
	 * Get the total score.
	 * @return int
	 */
	public function getTotal(): int {
		if ( isset( $this->total ) ) {
			return $this->total;
		}
		$this->prepareData();
		return $this->total;
	}

	/**
	 * Set the scoring results on class properties $scores and $total.
	 */
	public function prepareData(): void {
		$data = $this->repository->fetchData( $this->project, $this->user );
		$this->total = 0;
		$this->scores = [];

		foreach ( $data as $row ) {
			$key = $row['source'];
			$value = $row['value'];

			// WMF Replica databases are returning binary control characters
			// This is specifically shown with WikiData.
			// More details: T197165
			$isnull = ( $value == null );
			if ( !$isnull ) {
				$value = str_replace( "\x00", "", $value );
			}

			if ( $key === 'account-age' ) {
				if ( $isnull ) {
					$value = 0;
				} else {
					$now = new DateTime();
					$date = new DateTime( $value );
					$diff = $date->diff( $now );
					$formula = 365 * (int)$diff->format( '%y' ) + 30 *
						(int)$diff->format( '%m' ) + (int)$diff->format( '%d' );
					if ( $formula < 365 ) {
						$this->multipliers['account-age-mult'] = 0;
					}
					$value = $formula;
				}
			}

			$multiplierKey = $row['source'] . '-mult';
			$multiplier = $this->multipliers[$multiplierKey] ?? 1;
			$score = max( min( $value * $multiplier, 100 ), -100 );
			$this->scores[$key]['mult'] = $multiplier;
			$this->scores[$key]['value'] = $value;
			$this->scores[$key]['score'] = $score;
			$this->total += (int)$score;
		}
	}
}
