<?php

declare( strict_types = 1 );

namespace App\Tests\Model;

use App\Model\LargestPages;
use App\Model\Project;
use App\Repository\LargestPagesRepository;
use PHPUnit\Framework\TestCase;

class LargestPagesTest extends TestCase {
	public function testGetters(): void {
		$largestPages = new LargestPages(
			$this->createMock( LargestPagesRepository::class ),
			$this->createMock( Project::class ),
			0,
			'foo%',
			'%bar'
		);

		static::assertEquals( 'foo%', $largestPages->getIncludePattern() );
		static::assertEquals( '%bar', $largestPages->getExcludePattern() );
	}
}
