<?php

declare( strict_types = 1 );

namespace App\Tests\Model;

use App\Helper\AutomatedEditsHelper;
use App\Model\Edit;
use App\Model\Page;
use App\Model\PageAssessments;
use App\Model\Project;
use App\Model\TopEdits;
use App\Model\User;
use App\Repository\EditRepository;
use App\Repository\PageRepository;
use App\Repository\ProjectRepository;
use App\Repository\TopEditsRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;

/**
 * Tests of the TopEdits class.
 * @covers \App\Model\TopEdits
 */
class TopEditsTest extends TestAdapter {
	protected AutomatedEditsHelper $autoEditsHelper;
	protected EditRepository $editRepo;
	protected PageRepository $pageRepo;
	protected Project $project;
	protected ProjectRepository $projectRepo;
	protected TopEditsRepository $teRepo;
	protected User $user;
	protected UserRepository $userRepo;

	/**
	 * Set up class instances and mocks.
	 */
	public function setUp(): void {
		$this->project = new Project( 'en.wikipedia.org' );
		$this->project->setPageAssessments( $this->createMock( PageAssessments::class ) );
		$this->projectRepo = $this->createMock( ProjectRepository::class );
		$this->projectRepo->method( 'getMetadata' )
			->willReturn( [ 'namespaces' => [ 0 => 'Main', 3 => 'User_talk' ] ] );
		$this->projectRepo->method( 'getOne' )
			->willReturn( [ 'url' => 'https://en.wikipedia.org' ] );
		$this->projectRepo->method( 'pageHasContent' )
			->with( $this->project, 2, 'Test user/EditCounterOptIn.js' )
			->willReturn( true );
		$this->project->setRepository( $this->projectRepo );
		$this->userRepo = $this->createMock( UserRepository::class );
		$this->user = new User( $this->userRepo, 'Test user' );
		$this->autoEditsHelper = $this->getAutomatedEditsHelper();
		$this->teRepo = $this->createMock( TopEditsRepository::class );
		$this->editRepo = $this->createMock( EditRepository::class );
		$this->editRepo->method( 'getAutoEditsHelper' )
			->willReturn( $this->autoEditsHelper );
		$this->pageRepo = $this->createMock( PageRepository::class );
	}

	/**
	 * Test the basic functionality of TopEdits.
	 */
	public function testBasic(): void {
		// Single namespace, with defaults.
		$te = $this->getTopEdits();
		static::assertSame( 0, $te->getNamespace() );
		static::assertEquals( 1000, $te->getLimit() );

		// Single namespace, explicit configuration.
		$te = $this->getTopEdits( null, 5, false, false, 50 );
		static::assertEquals( 5, $te->getNamespace() );
		static::assertEquals( 50, $te->getLimit() );

		// All namespaces, so limit set.
		$te = $this->getTopEdits( null, 'all' );
		static::assertEquals( 'all', $te->getNamespace() );
		static::assertEquals( 20, $te->getLimit() );

		// All namespaces, explicit limit.
		$te = $this->getTopEdits( null, 'all', false, false, 3 );
		static::assertEquals( 'all', $te->getNamespace() );
		static::assertEquals( 3, $te->getLimit() );

		$page = new Page( $this->pageRepo, $this->project, 'Test page' );
		$te->setPage( $page );
		static::assertEquals( $page, $te->getPage() );
	}

	/**
	 * Getting top edited pages across all namespaces.
	 */
	public function testTopEditsAllNamespaces(): void {
		$te = $this->getTopEdits( null, 'all', false, false, 2 );
		$this->teRepo->expects( $this->once() )
			->method( 'getTopEditsAllNamespaces' )
			->with( $this->project, $this->user, '', '', 2 )
			->willReturn( array_merge(
				$this->topEditsNamespaceFactory()[0],
				$this->topEditsNamespaceFactory()[3]
			) );
		$te->setRepository( $this->teRepo );
		$te->prepareData();

		$result = $te->getTopEdits();
		static::assertEquals( [ 0, 3 ], array_keys( $result ) );
		static::assertCount( 2, $result );
		static::assertCount( 2, $result[0] );
		static::assertCount( 2, $result[3] );
		static::assertEquals( [
			'namespace' => '0',
			'page_title' => 'Foo bar',
			'redirect' => '1',
			'count' => '24',
			'full_page_title' => 'Foo bar',
			'assessment' => [
				'class' => 'List',
			],
		], $result[0][0] );

		// Fetching again should use value of class property.
		// The $this->once() above will validate this.
		$result2 = $te->getTopEdits();
		static::assertEquals( $result, $result2 );
	}

	/**
	 * Getting top edited pages within a single namespace.
	 */
	public function testTopEditsNamespace(): void {
		$te = $this->getTopEdits( null, 3, false, false, 2 );
		$this->teRepo->expects( $this->once() )
			->method( 'getTopEditsNamespace' )
			->with( $this->project, $this->user, 3, false, false, 2 )
			->willReturn( $this->topEditsNamespaceFactory()[3] );
		$te->setRepository( $this->teRepo );
		$te->prepareData();

		$result = $te->getTopEdits();
		static::assertEquals( [ 3 ], array_keys( $result ) );
		static::assertCount( 1, $result );
		static::assertCount( 2, $result[3] );
		static::assertEquals( [
			'namespace' => '3',
			'page_title' => 'Jimbo Wales',
			'redirect' => '0',
			'count' => '1',
			'full_page_title' => 'User talk:Jimbo Wales',
		], $result[3][1] );
	}

	/**
	 * Data for self::testTopEditsAllNamespaces() and self::testTopEditsNamespace().
	 * @return array
	 */
	private function topEditsNamespaceFactory(): array {
		return [
			0 => [
				[
				  'namespace' => '0',
				  'page_title' => 'Foo_bar',
				  'redirect' => '1',
				  'count' => '24',
				  'pa_class' => 'List',
				  'full_page_title' => 'Foo_bar',
				], [
				  'namespace' => '0',
				  'page_title' => '101st_Airborne_Division',
				  'redirect' => '0',
				  'count' => '18',
				  'pa_class' => 'C',
				  'full_page_title' => '101st_Airborne_Division',
				],
			],
			3 => [
				[
				  'namespace' => '3',
				  'page_title' => 'Test_user',
				  'redirect' => '0',
				  'count' => '3',
				  'full_page_title' => 'User_talk:Test_user',
				], [
				  'namespace' => '3',
				  'page_title' => 'Jimbo_Wales',
				  'redirect' => '0',
				  'count' => '1',
				  'full_page_title' => 'User_talk:Jimbo_Wales',
				],
			],
		];
	}

	/**
	 * Top edits to a single page.
	 */
	public function testTopEditsPage(): void {
		$te = $this->getTopEdits( new Page( $this->pageRepo, $this->project, 'Test page' ) );
		$this->teRepo->expects( $this->once() )
			->method( 'getTopEditsPage' )
			->willReturn( $this->topEditsPageFactory() );
		// The Edit instantiation happens in the repo, so we need to mock it for each
		// revision so that the processing in TopEdits::prepareData() is done correctly.
		$this->teRepo->method( 'getEdit' )
			->willReturnCallback( function ( $page, $rev ) {
				return new Edit( $this->editRepo, $this->userRepo, $page, $rev );
			} );

		$te->prepareData();

		static::assertEquals( 4, $te->getNumTopEdits(), 'getNumTopEdits' );
		static::assertEquals( 100, $te->getTotalAdded(), 'getTotalAdded' );
		static::assertEquals( -50, $te->getTotalRemoved(), 'getTotalRemoved' );
		static::assertSame( 1, $te->getTotalMinor(), 'getTotalMinor' );
		static::assertSame( 1, $te->getTotalAutomated(), 'getTotalAutomated' );
		static::assertEquals( 2, $te->getTotalReverted(), 'getTotalReverted' );
		static::assertEquals( 10, $te->getTopEdits()[1]->getId(), 'ID of second mock TopEdit' );
		static::assertEquals( 22.5, $te->getAtbe(), 'getAtBe' );
	}

	/**
	 * Test data for self::TopEditsPage().
	 * @return array
	 */
	private function topEditsPageFactory(): array {
		return [
			[
				'id' => 0,
				'timestamp' => '20170423000000',
				'minor' => 0,
				'length' => 100,
				'length_change' => 100,
				'reverted' => 0,
				'user_id' => 5,
				'username' => 'Test user',
				'comment' => 'Foo bar',
				'parent_comment' => null,
			 ], [
				'id' => 10,
				'timestamp' => '20170313000000',
				'minor' => '1',
				'length' => 200,
				'length_change' => 50,
				'reverted' => 0,
				'user_id' => 5,
				'username' => 'Test user',
				'comment' => 'Weeee (using [[WP:AWB]])',
				'parent_comment' => 'Reverted edits by Test user ([[WP:HG]])',
			 ], [
				'id' => 20,
				'timestamp' => '20170223000000',
				'minor' => 0,
				'length' => 500,
				'length_change' => -50,
				'reverted' => 0,
				'user_id' => 5,
				'username' => 'Test user',
				'comment' => 'Boomshakalaka',
				'parent_comment' => 'Just another innocent edit',
			 ], [
				'id' => 30,
				'timestamp' => '20170123000000',
				'minor' => 0,
				'length' => 500,
				'length_change' => 100,
				'reverted' => 1,
				'user_id' => 5,
				'username' => 'Test user',
				'comment' => 'Best edit ever',
				'parent_comment' => 'I plead the Fifth',
			 ],
		];
	}

	/**
	 * @param Page|null $page
	 * @param string|int $namespace Namespace ID or 'all'.
	 * @param int|false $start Start date as Unix timestamp.
	 * @param int|false $end End date as Unix timestamp.
	 * @param int|null $limit Number of rows to fetch.
	 * @return TopEdits
	 */
	private function getTopEdits(
		?Page $page = null,
		$namespace = 0,
		$start = false,
		$end = false,
		?int $limit = null
	): TopEdits {
		return new TopEdits(
			$this->teRepo,
			$this->autoEditsHelper,
			$this->project,
			$this->user,
			$page,
			$namespace,
			$start,
			$end,
			$limit
		);
	}
}
