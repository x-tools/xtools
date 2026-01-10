<?php

declare( strict_types = 1 );

namespace App\Model;

use App\Repository\LargestPagesRepository;
use App\Repository\Repository;

/**
 * A LargestPages provides a list of the largest pages on a project.
 */
class LargestPages extends Model {
	/**
	 * LargestPages constructor.
	 * @param Repository|LargestPagesRepository $repository
	 * @param Project $project
	 * @param string|int|null $namespace Namespace ID or 'all'.
	 * @param string $includePattern Either regular expression (starts/ends with forward slash),
	 *   or a wildcard pattern with % as the wildcard symbol.
	 * @param string $excludePattern Either regular expression (starts/ends with forward slash),
	 *   or a wildcard pattern with % as the wildcard symbol.
	 */
	public function __construct(
		protected Repository|LargestPagesRepository $repository,
		protected Project $project,
		string|int|null $namespace = 'all',
		protected string $includePattern = '',
		protected string $excludePattern = ''
	) {
		$this->namespace = $namespace == '' ? 0 : $namespace;
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
