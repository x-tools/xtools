<?php

declare( strict_types = 1 );

namespace App\Tests\Model;

use App\Model\GlobalContribs;
use App\Model\Project;
use App\Model\User;
use App\Repository\EditRepository;
use App\Repository\GlobalContribsRepository;
use App\Repository\PageRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \App\Model\GlobalContribs
 */
class GlobalContribsTest extends TestAdapter {
	protected GlobalContribs $globalContribs;
	protected GlobalContribsRepository $globalContribsRepo;

	/**
	 * Set up shared mocks and class instances.
	 */
	public function setUp(): void {
		$this->globalContribsRepo = $this->createMock( GlobalContribsRepository::class );
		$userRepo = $this->createMock( UserRepository::class );
		$this->globalContribs = new GlobalContribs(
			$this->globalContribsRepo,
			$this->createMock( PageRepository::class ),
			$userRepo,
			$this->createMock( EditRepository::class ),
			new User( $userRepo, 'Test user' )
		);
	}

	/**
	 * Get all global edit counts, or just the top N, or the overall grand total.
	 */
	public function testGlobalEditCounts(): void {
		$wiki1 = new Project( 'wiki1' );
		$wiki2 = new Project( 'wiki2' );
		$editCounts = [
			[ 'project' => new Project( 'wiki0' ), 'total' => 30 ],
			[ 'project' => $wiki1, 'total' => 50 ],
			[ 'project' => $wiki2, 'total' => 40 ],
			[ 'project' => new Project( 'wiki3' ), 'total' => 20 ],
			[ 'project' => new Project( 'wiki4' ), 'total' => 10 ],
			[ 'project' => new Project( 'wiki5' ), 'total' => 35 ],
		];
		$this->globalContribsRepo->expects( static::once() )
			->method( 'globalEditCounts' )
			->willReturn( $editCounts );

		// Get the top 2.
		static::assertEquals(
			[
				[ 'project' => $wiki1, 'total' => 50 ],
				[ 'project' => $wiki2, 'total' => 40 ],
			],
			$this->globalContribs->globalEditCountsTopN( 2 )
		);

		// And the bottom 4.
		static::assertEquals( 95, $this->globalContribs->globalEditCountWithoutTopN( 2 ) );

		// Grand total.
		static::assertEquals( 185, $this->globalContribs->globalEditCount() );
	}

	/**
	 * Test global edits.
	 * @dataProvider globalEditsProvider
	 * @param array $contribs
	 * @param array $projects
	 * @param int $count
	 * @param int $projectCount
	 */
	public function testGlobalEdits(
		array $contribs,
		array $projects,
		int $count,
		int $projectCount
	): void {
		$globalContribsRepo = $this->createMock( GlobalContribsRepository::class );
		$globalContribsRepo->expects( static::exactly( 2 + ( count( $projects ) ? 0 : 1 ) ) )
			->method( 'getProjectsWithEdits' )
			->willReturn( $projects );
		$globalContribsRepo->expects( static::any() )
			->method( 'getRevisions' )
			->willReturn( $contribs );
		$this->globalContribs->setRepository( $globalContribsRepo );

		$edits = $this->globalContribs->globalEdits();

		static::assertCount( $count, $edits );
		if ( $count > 0 ) {
			static::assertEquals( 'My user page', $edits['1514764800-1']->getComment() );
		}
		static::assertEquals( $projectCount, $this->globalContribs->numProjectsWithEdits() );

		$this->globalContribs->globalEdits();
	}

	public function globalEditsProvider(): array {
		/** @var ProjectRepository|MockObject $wiki1Repo */
		$wiki1Repo = $this->createMock( ProjectRepository::class );
		$wiki1Repo->expects( static::once() )
			->method( 'getMetadata' )
			->willReturn( [ 'namespaces' => [ 2 => 'User' ] ] );
		$wiki1Repo->expects( static::once() )
			->method( 'getOne' )
			->willReturn( [
				'dbName' => 'wiki1',
				'url' => 'https://wiki1.example.org',
			] );
		$wiki1 = new Project( 'wiki1' );
		$wiki1->setRepository( $wiki1Repo );
		$edit = [ [
			'dbName' => 'wiki1',
			'id' => 1,
			'timestamp' => '20180101000000',
			'unix_timestamp' => '1514764800',
			'minor' => 0,
			'deleted' => 0,
			'length' => 5,
			'length_change' => 10,
			'parent_id' => 0,
			'username' => 'Test user',
			'page_title' => 'Foo bar',
			'namespace' => '2',
			'comment' => 'My user page',
		] ];
		return [
			[ // Dataset #0: normal case. 1 edit, 1 project
				$edit,
				[ 'wiki1' => $wiki1 ],
				1,
				1,
			], [ // Dataset #1: project for edit is null
				$edit,
				[ 'wiki1' => null ],
				0,
				1,
			], [ // Dataset #2: no projects and no edit
				[],
				[],
				0,
				0,
			],
		];
	}
}
