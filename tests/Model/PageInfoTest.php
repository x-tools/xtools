<?php

declare( strict_types = 1 );

namespace App\Tests\Model;

use App\Exception\BadGatewayException;
use App\Helper\I18nHelper;
use App\Model\Edit;
use App\Model\Page;
use App\Model\PageInfo;
use App\Model\PageInfoApi;
use App\Model\Project;
use App\Repository\EditRepository;
use App\Repository\PageInfoRepository;
use App\Repository\PageRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ReflectionClass;

/**
 * Tests for PageInfo.
 * @covers \App\Model\PageInfo
 * @covers \App\Model\PageInfoApi
 */
class PageInfoTest extends TestAdapter {
	use ArraySubsetAsserts;

	protected PageInfo $pageInfo;
	protected PageInfoRepository $pageInfoRepo;
	protected EditRepository $editRepo;
	protected Page $page;
	protected PageRepository $pageRepo;
	protected Project $project;
	protected UserRepository $userRepo;

	/** @var ReflectionClass Hack to test private methods. */
	private ReflectionClass $reflectionClass;

	/**
	 * Set up shared mocks and class instances.
	 */
	public function setUp(): void {
		$autoEditsHelper = $this->getAutomatedEditsHelper();
		/** @var I18nHelper $i18nHelper */
		$i18nHelper = static::getContainer()->get( 'app.i18n_helper' );
		$this->project = $this->getMockEnwikiProject();
		$this->pageRepo = $this->createMock( PageRepository::class );
		$this->page = new Page( $this->pageRepo, $this->project, 'Test page' );
		$this->editRepo = $this->createMock( EditRepository::class );
		$this->editRepo->method( 'getAutoEditsHelper' )
			->willReturn( $autoEditsHelper );
		$this->userRepo = $this->createMock( UserRepository::class );
		$this->pageInfoRepo = $this->createMock( PageInfoRepository::class );
		$this->pageInfoRepo->method( 'getMaxPageRevisions' )
			->willReturn( static::getContainer()->getParameter( 'app.max_page_revisions' ) );
		$this->pageInfo = new PageInfo(
			$this->pageInfoRepo,
			$i18nHelper,
			$autoEditsHelper,
			$this->page
		);

		// Don't care that private methods "shouldn't" be tested...
		// In PageInfo they are all super test-worthy and otherwise fragile.
		$this->reflectionClass = new ReflectionClass( $this->pageInfo );
	}

	/**
	 * Number of revisions
	 */
	public function testNumRevisions(): void {
		$this->pageRepo->expects( $this->once() )
			->method( 'getNumRevisions' )
			->willReturn( 10 );
		static::assertEquals( 10, $this->pageInfo->getNumRevisions() );
		// Should be cached (will error out if repo's getNumRevisions is called again).
		static::assertEquals( 10, $this->pageInfo->getNumRevisions() );
	}

	/**
	 * Number of revisions processed, based on app.max_page_revisions
	 * @dataProvider revisionsProcessedProvider
	 * @param int $numRevisions
	 * @param int $assertion
	 */
	public function testRevisionsProcessed( int $numRevisions, int $assertion ): void {
		$this->pageRepo->method( 'getNumRevisions' )->willReturn( $numRevisions );
		static::assertEquals(
			$this->pageInfo->getNumRevisionsProcessed(),
			$assertion
		);
	}

	/**
	 * Data for self::testRevisionsProcessed().
	 * @return int[]
	 */
	public function revisionsProcessedProvider(): array {
		return [
			[ 1000000, 50000 ],
			[ 10, 10 ],
		];
	}

	/**
	 * Whether there are too many revisions to process.
	 */
	public function testTooManyRevisions(): void {
		$this->pageRepo->expects( $this->once() )
			->method( 'getNumRevisions' )
			->willReturn( 1000000 );
		static::assertTrue( $this->pageInfo->tooManyRevisions() );
	}

	/**
	 * Getting the number of edits made to the page by current or former bots.
	 */
	public function testBotRevisionCount(): void {
		$bots = [
			'Foo' => [
				'count' => 3,
				'current' => true,
			],
			'Bar' => [
				'count' => 12,
				'current' => false,
			],
		];

		static::assertEquals(
			15,
			$this->pageInfo->getBotRevisionCount( $bots )
		);
	}

	public function testLinksAndRedirects(): void {
		$this->pageRepo->expects( $this->once() )
			->method( 'countLinksAndRedirects' )
			->willReturn( [
				'links_ext_count' => 5,
				'links_out_count' => 3,
				'links_in_count' => 10,
				'redirects_count' => 0,
			] );
		$this->page->setRepository( $this->pageRepo );
		static::assertEquals( 5, $this->pageInfo->linksExtCount() );
		static::assertEquals( 3, $this->pageInfo->linksOutCount() );
		static::assertEquals( 10, $this->pageInfo->linksInCount() );
		static::assertSame( 0, $this->pageInfo->redirectsCount() );
	}

	/**
	 * Test some of the more important getters.
	 */
	public function testGetters(): void {
		$edits = $this->setupData();

		static::assertEquals( 3, $this->pageInfo->getNumEditors() );
		static::assertEquals( 2, $this->pageInfo->getAnonCount() );
		static::assertEquals( 40, $this->pageInfo->anonPercentage() );
		static::assertEquals( 3, $this->pageInfo->getMinorCount() );
		static::assertEquals( 60, $this->pageInfo->minorPercentage() );
		static::assertSame( 1, $this->pageInfo->getBotRevisionCount() );
		static::assertEquals( 93, $this->pageInfo->getTotalDays() );
		static::assertEquals( 18, (int)$this->pageInfo->averageDaysPerEdit() );
		static::assertSame( 0, (int)$this->pageInfo->editsPerDay() );
		static::assertEquals( 1.6, $this->pageInfo->editsPerMonth() );
		static::assertEquals( 5, $this->pageInfo->editsPerYear() );
		static::assertEquals( 1.7, $this->pageInfo->editsPerEditor() );
		static::assertEquals( 2, $this->pageInfo->getAutomatedCount() );
		static::assertSame( 1, $this->pageInfo->getRevertCount() );

		static::assertEquals( 80, $this->pageInfo->topTenPercentage() );
		static::assertEquals( 4, $this->pageInfo->getTopTenCount() );

		static::assertEquals(
			$edits[0]->getId(),
			$this->pageInfo->getFirstEdit()->getId()
		);
		static::assertEquals(
			$edits[4]->getId(),
			$this->pageInfo->getLastEdit()->getId()
		);

		static::assertSame( 1, $this->pageInfo->getMaxAddition()->getId() );
		static::assertEquals( 32, $this->pageInfo->getMaxDeletion()->getId() );

		static::assertEquals(
			[ 'Mick Jagger', '192.168.0.1', '192.168.0.2' ],
			array_keys( $this->pageInfo->getEditors() )
		);
		static::assertEquals(
			[
				'label' => 'Mick Jagger',
				'value' => 2,
				'percentage' => 50,
			],
			$this->pageInfo->topTenEditorsByEdits()[0]
		);
		static::assertEquals(
			[
				'label' => 'Mick Jagger',
				'value' => 30,
				'percentage' => 100,
			],
			$this->pageInfo->topTenEditorsByAdded()[0]
		);

		// Top 10 counts should not include bots.
		static::assertFalse(
			array_search(
				'XtoolsBot',
				array_column( $this->pageInfo->topTenEditorsByEdits(), 'label' )
			)
		);
		static::assertFalse(
			array_search(
				'XtoolsBot',
				array_column( $this->pageInfo->topTenEditorsByAdded(), 'label' )
			)
		);

		static::assertEquals( [ 'Mick Jagger' ], $this->pageInfo->getHumans( 1 ) );

		static::assertEquals( 3, $this->pageInfo->getMaxEditsPerMonth() );

		static::assertContains(
			'AutoWikiBrowser',
			array_keys( $this->pageInfo->getTools() )
		);

		static::assertSame( 1, $this->pageInfo->numDeletedRevisions() );
	}

	/**
	 * Test that the data for each individual month and year is correct.
	 */
	public function testMonthYearCounts(): void {
		$this->setupData();

		$yearMonthCounts = $this->pageInfo->getYearMonthCounts();

		static::assertEquals( [ 2016 ], array_keys( $yearMonthCounts ) );
		static::assertEquals( [ '2016' ], $this->pageInfo->getYearLabels() );
		static::assertArraySubset( [
			'all' => 5,
			'minor' => 3,
			'anon' => 2,
			'automated' => 2,
			'size' => 20,
		], $yearMonthCounts[2016] );

		static::assertEquals(
			[ '07', '08', '09', '10', '11', '12' ],
			array_keys( $yearMonthCounts[2016]['months'] )
		);
		static::assertEquals(
			[ '2016-07', '2016-08', '2016-09', '2016-10', '2016-11', '2016-12' ],
			$this->pageInfo->getMonthLabels()
		);

		// Just test a few, not every month.
		static::assertArraySubset( [
			'all' => 1,
			'minor' => 0,
			'anon' => 0,
			'automated' => 0,
		], $yearMonthCounts[2016]['months']['07'] );
		static::assertArraySubset( [
			'all' => 3,
			'minor' => 2,
			'anon' => 2,
			'automated' => 2,
		], $yearMonthCounts[2016]['months']['10'] );
	}

	/**
	 * Test data around log events.
	 */
	public function testLogEvents(): void {
		$this->setupData();

		$this->pageInfoRepo->expects( $this->once() )
			->method( 'getLogEvents' )
			->willReturn( [
				[
					'log_type' => 'protect',
					'timestamp' => '20160705000000',
				],
				[
					'log_type' => 'delete',
					'timestamp' => '20160905000000',
				],
				[
					'log_type' => 'move',
					'timestamp' => '20161005000000',
				],
			] );

		$method = $this->reflectionClass->getMethod( 'setLogsEvents' );
		$method->setAccessible( true );
		$method->invoke( $this->pageInfo );

		$yearMonthCounts = $this->pageInfo->getYearMonthCounts();

		// Just test a few, not every month.
		static::assertEquals( [
			'protections' => 1,
			'deletions' => 1,
			'moves' => 1,
		], $yearMonthCounts[2016]['events'] );
	}

	/**
	 * Use ReflectionClass to set up some data and populate the class properties for testing.
	 *
	 * We don't care that private methods "shouldn't" be tested...
	 * In PageInfo the update methods are all super test-worthy and otherwise fragile.
	 *
	 * @return Edit[] Array of Edit objects that represent the revision history.
	 */
	private function setupData(): array {
		$edits = [
			new Edit( $this->editRepo, $this->userRepo, $this->page, [
				'id' => 1,
				'timestamp' => '20160701101205',
				'minor' => '0',
				'length' => '30',
				'length_change' => '30',
				'username' => 'Mick Jagger',
				'comment' => 'Foo bar',
				'rev_sha1' => 'aaaaaa',
			] ),
			new Edit( $this->editRepo, $this->userRepo, $this->page, [
				'id' => 32,
				'timestamp' => '20160801000000',
				'minor' => '1',
				'length' => '25',
				'length_change' => '-5',
				'username' => 'Mick Jagger',
				'comment' => 'Blah',
				'rev_sha1' => 'bbbbbb',
			] ),
			new Edit( $this->editRepo, $this->userRepo, $this->page, [
				'id' => 40,
				'timestamp' => '20161003000000',
				'minor' => '0',
				'length' => '15',
				'length_change' => '-10',
				'username' => '192.168.0.1',
				'comment' => 'Weeee using [[WP:AWB|AWB]]',
				'rev_sha1' => 'cccccc',
			] ),
			new Edit( $this->editRepo, $this->userRepo, $this->page, [
				'id' => 50,
				'timestamp' => '20161003010000',
				'minor' => '1',
				'length' => '25',
				'length_change' => '10',
				'username' => '192.168.0.2',
				'comment' => 'I undo your edit cuz it bad',
				'rev_sha1' => 'bbbbbb',
			] ),
			new Edit( $this->editRepo, $this->userRepo, $this->page, [
				'id' => 60,
				'timestamp' => '20161003020000',
				'minor' => '1',
				'length' => '20',
				'length_change' => '-5',
				'username' => 'Offensive username',
				'comment' => 'Weeee using [[WP:AWB|AWB]]',
				'rev_sha1' => 'ddddd',
				'rev_deleted' => Edit::DELETED_USER,
			] ),
		];

		$prevEdits = [
			'prev' => null,
			'prevSha' => null,
			'maxAddition' => null,
			'maxDeletion' => null,
		];

		$prop = $this->reflectionClass->getProperty( 'firstEdit' );
		$prop->setAccessible( true );
		$prop->setValue( $this->pageInfo, $edits[0] );

		$prop = $this->reflectionClass->getProperty( 'numRevisionsProcessed' );
		$prop->setAccessible( true );
		$prop->setValue( $this->pageInfo, 5 );

		$prop = $this->reflectionClass->getProperty( 'bots' );
		$prop->setAccessible( true );
		$prop->setValue( $this->pageInfo, [
			'XtoolsBot' => [ 'count' => 1 ],
		] );

		$prop = $this->reflectionClass->getProperty( 'numDeletedRevisions' );
		$prop->setAccessible( true );
		$prop->setValue( $this->pageInfo, 1 );

		$method = $this->reflectionClass->getMethod( 'updateCounts' );
		$method->setAccessible( true );
		$prevEdits = $method->invoke( $this->pageInfo, $edits[0], $prevEdits );
		$prevEdits = $method->invoke( $this->pageInfo, $edits[1], $prevEdits );
		$prevEdits = $method->invoke( $this->pageInfo, $edits[2], $prevEdits );
		$prevEdits = $method->invoke( $this->pageInfo, $edits[3], $prevEdits );
		$method->invoke( $this->pageInfo, $edits[4], $prevEdits );

		$method = $this->reflectionClass->getMethod( 'doPostPrecessing' );
		$method->setAccessible( true );
		$method->invoke( $this->pageInfo );

		return $edits;
	}

	/**
	 * Test prose stats parser.
	 */
	public function testProseStats(): void {
		// We'll use a live page to better test the prose stats parser.
		$client = static::getContainer()->get( 'eight_points_guzzle.client.xtools' );
		$ret = $client->request( 'GET', 'https://en.wikipedia.org/api/rest_v1/page/html/Hanksy/747629772' )
			->getBody()
			->getContents();
		$this->pageRepo->expects( $this->once() )
			->method( 'getHTMLContent' )
			->willReturn( $ret );
		$this->page->setRepository( $this->pageRepo );

		static::assertEquals( [
			'bytes' => 1539,
			'characters' => 1539,
			'words' => 261,
			'references' => 13,
			'unique_references' => 12,
			'sections' => 2,
		], $this->pageInfo->getProseStats() );
	}

	/**
	 * Various methods involving start/end dates.
	 */
	public function testWithDates(): void {
		$this->setupData();

		$prop = $this->reflectionClass->getProperty( 'start' );
		$prop->setAccessible( true );
		$prop->setValue( $this->pageInfo, strtotime( '2016-06-30' ) );

		$prop = $this->reflectionClass->getProperty( 'end' );
		$prop->setAccessible( true );
		$prop->setValue( $this->pageInfo, strtotime( '2016-10-14' ) );

		static::assertTrue( $this->pageInfo->hasDateRange() );
		static::assertEquals( '2016-06-30', $this->pageInfo->getStartDate() );
		static::assertEquals( '2016-10-14', $this->pageInfo->getEndDate() );
		static::assertEquals( [
			'start' => '2016-06-30',
			'end' => '2016-10-14',
		], $this->pageInfo->getDateParams() );

		// Uses length of last edit because there is a date range.
		static::assertEquals( 20, $this->pageInfo->getLength() );

		// Pageviews with a date range.
		$this->pageRepo->expects( $this->once() )
			->method( 'getPageviews' )
			->with( $this->page, '2016-06-30', '2016-10-14' )
			->willReturn( [
				'items' => [
					[ 'views' => 1000 ],
					[ 'views' => 500 ],
				],
			] );
		static::assertEquals( 1500, $this->pageInfo->getPageviews()['count'] );
	}

	/**
	 * Transclusion counts.
	 */
	public function testTransclusionData(): void {
		$pageInfoRepo = $this->createMock( PageInfoRepository::class );
		$pageInfoRepo->expects( static::once() )
			->method( 'getTransclusionData' )
			->willReturn( [
				'categories' => 3,
				'templates' => 5,
				'files' => 2,
			] );
		$this->pageInfo->setRepository( $pageInfoRepo );

		static::assertEquals( 3, $this->pageInfo->getNumCategories() );
		static::assertEquals( 5, $this->pageInfo->getNumTemplates() );
		static::assertEquals( 2, $this->pageInfo->getNumFiles() );
	}

	public function testPageviews(): void {
		$this->pageRepo->expects( $this->once() )
			->method( 'getPageviews' )
			->willReturn( [
				'items' => [
					[ 'views' => 1000 ],
					[ 'views' => 500 ],
				],
			] );

		static::assertEquals( [
			'count' => 1500,
			'formatted' => '1,500',
			'tooltip' => '',
		], $this->pageInfo->getPageviews() );

		static::assertEquals( PageInfoApi::PAGEVIEWS_OFFSET, $this->pageInfo->getPageviewsOffset() );
	}

	public function testPageviewsFailing(): void {
		$this->pageRepo->expects( $this->once() )
			->method( 'getPageviews' )
			->willThrowException( $this->createMock( BadGatewayException::class ) );

		static::assertEquals( [
			'count' => null,
			'formatted' => 'Data unavailable',
			'tooltip' => 'There was an error connecting to the Pageviews API. ' .
				'Try refreshing this page or try again later.',
		], $this->pageInfo->getPageviews() );
	}
}
