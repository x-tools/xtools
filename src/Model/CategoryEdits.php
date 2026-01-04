<?php

declare( strict_types = 1 );

namespace App\Model;

use App\Repository\CategoryEditsRepository;

/**
 * CategoryEdits returns statistics about edits made by a user to pages in given categories.
 */
class CategoryEdits extends Model {
	/** @var string[] The categories. */
	protected array $categories;

	/** @var Edit[] The list of contributions. */
	protected array $categoryEdits;

	/** @var int Total number of edits. */
	protected int $editCount;

	/** @var int Total number of edits within the category. */
	protected int $categoryEditCount;

	/** @var array Counts of edits within each category, keyed by category name. */
	protected array $categoryCounts;

	/**
	 * Constructor for the CategoryEdits class.
	 * @param CategoryEditsRepository $repository
	 * @param Project $project
	 * @param User $user
	 * @param array $categories
	 * @param int|false $start As Unix timestamp.
	 * @param int|false $end As Unix timestamp.
	 * @param int|false $offset As Unix timestamp. Used for pagination.
	 */
	public function __construct(
		CategoryEditsRepository $repository,
		Project $project,
		User $user,
		array $categories,
		$start = false,
		$end = false,
		$offset = false
	) {
		$this->repository = $repository;
		$this->project = $project;
		$this->user = $user;
		$this->categories = array_map( static function ( $category ) {
			return str_replace( ' ', '_', $category );
		}, $categories );
		$this->start = $start;
		$this->end = $end;
		$this->offset = $offset;
	}

	/**
	 * Get the categories.
	 * @return string[]
	 */
	public function getCategories(): array {
		return $this->categories;
	}

	/**
	 * Get the categories as a piped string.
	 * @return string
	 */
	public function getCategoriesPiped(): string {
		return implode( '|', $this->categories );
	}

	/**
	 * Get the categories as an array of normalized strings (without namespace).
	 * @return string[]
	 */
	public function getCategoriesNormalized(): array {
		return array_map( static function ( $category ) {
			return str_replace( '_', ' ', $category );
		}, $this->categories );
	}

	/**
	 * Get the raw edit count of the user.
	 * @return int
	 */
	public function getEditCount(): int {
		if ( !isset( $this->editCount ) ) {
			$this->editCount = $this->user->countEdits(
				$this->project,
				'all',
				$this->start,
				$this->end
			);
		}

		return $this->editCount;
	}

	/**
	 * Get the number of edits this user made within the categories.
	 * @return int Result of query, see below.
	 */
	public function getCategoryEditCount(): int {
		if ( isset( $this->categoryEditCount ) ) {
			return $this->categoryEditCount;
		}

		$this->categoryEditCount = $this->repository->countCategoryEdits(
			$this->project,
			$this->user,
			$this->categories,
			$this->start,
			$this->end
		);

		return $this->categoryEditCount;
	}

	/**
	 * Get the percentage of all edits made to the categories.
	 * @return float
	 */
	public function getCategoryPercentage(): float {
		return $this->getEditCount() > 0
			? ( $this->getCategoryEditCount() / $this->getEditCount() ) * 100
			: 0;
	}

	/**
	 * Get the number of pages edited.
	 * @return int
	 */
	public function getCategoryPageCount(): int {
		$pageCount = 0;
		foreach ( $this->getCategoryCounts() as $categoryCount ) {
			$pageCount += $categoryCount['pageCount'];
		}

		return $pageCount;
	}

	/**
	 * Get contributions made to the categories.
	 * @param bool $raw Wether to return raw data from the database, or get Edit objects.
	 * @return string[]|Edit[]
	 */
	public function getCategoryEdits( bool $raw = false ): array {
		if ( isset( $this->categoryEdits ) ) {
			return $this->categoryEdits;
		}

		$revs = $this->repository->getCategoryEdits(
			$this->project,
			$this->user,
			$this->categories,
			$this->start,
			$this->end,
			$this->offset
		);

		if ( $raw ) {
			return $revs;
		}

		$this->categoryEdits = $this->repository->getEditsFromRevs(
			$this->project,
			$this->user,
			$revs
		);

		return $this->categoryEdits;
	}

	/**
	 * Get counts of edits made to each individual category.
	 * @return array Counts, keyed by category name.
	 */
	public function getCategoryCounts(): array {
		if ( isset( $this->categoryCounts ) ) {
			return $this->categoryCounts;
		}

		$this->categoryCounts = $this->repository->getCategoryCounts(
			$this->project,
			$this->user,
			$this->categories,
			$this->start,
			$this->end
		);

		return $this->categoryCounts;
	}
}
