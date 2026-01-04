<?php

declare( strict_types = 1 );

namespace App\Model;

use App\Repository\LargestPagesRepository;

/**
 * A LargestPages provides a list of the largest pages on a project.
 */
class LargestPages extends Model {
	/** @var string */
	protected string $includePattern;

	/** @var string */
	protected string $excludePattern;

	/**
	 * LargestPages constructor.
	 * @param LargestPagesRepository $repository
	 * @param Project $project
	 * @param string|int|null $namespace Namespace ID or 'all'.
	 * @param string $includePattern Either regular expression (starts/ends with forward slash),
	 *   or a wildcard pattern with % as the wildcard symbol.
	 * @param string $excludePattern Either regular expression (starts/ends with forward slash),
	 *   or a wildcard pattern with % as the wildcard symbol.
	 */
	public function __construct(
		LargestPagesRepository $repository,
		Project $project,
		$namespace = 'all',
		string $includePattern = '',
		string $excludePattern = ''
	) {
		$this->repository = $repository;
		$this->project = $project;
		$this->namespace = '' == $namespace ? 0 : $namespace;
		$this->includePattern = $includePattern;
		$this->excludePattern = $excludePattern;
	}

	/**
	 * Get the inclusion pattern.
	 * @return string
	 */
	public function getIncludePattern(): string {
		return $this->includePattern;
	}

	/**
	 * Get the exclusion pattern.
	 * @return string
	 */
	public function getExcludePattern(): string {
		return $this->excludePattern;
	}

	/**
	 * Get the largest pages on the project.
	 * @return Page[]
	 * Just returns a repository result.
	 * @codeCoverageIgnore
	 */
	public function getResults(): array {
		return $this->repository->getData(
			$this->project,
			$this->namespace,
			$this->includePattern,
			$this->excludePattern
		);
	}
}
