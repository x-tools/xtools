<?php

declare( strict_types = 1 );

namespace App\Tests\Model;

use App\Exception\BadGatewayException;
use App\Model\Page;
use App\Model\Project;
use App\Model\User;
use App\Repository\PageRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;

/**
 * Tests for the Page class.
 * @covers \App\Model\Page
 */
class PageTest extends TestAdapter {
	use ArraySubsetAsserts;

	protected PageRepository $pageRepo;

	/**
	 * Set up client and set container.
	 */
	public function setUp(): void {
		$this->pageRepo = $this->createMock( PageRepository::class );
	}

	/**
	 * A page has a title and an HTML display title.
	 */
	public function testTitles(): void {
		$project = new Project( 'TestProject' );
		$data = [
			[ $project, 'Test_Page_1', [ 'title' => 'Test_Page_1' ] ],
			[ $project, 'Test_Page_2', [ 'title' => 'Test_Page_2', 'displaytitle' => '<em>Test</em> page 2' ] ],
		];
		$this->pageRepo->method( 'getPageInfo' )->willReturnMap( $data );

		// Page with no display title.
		$page = new Page( $this->pageRepo, $project, 'Test_Page_1' );
		static::assertEquals( 'Test_Page_1', $page->getTitle() );
		static::assertEquals( 'Test_Page_1', $page->getDisplayTitle() );

		// Page with a display title.
		$page = new Page( $this->pageRepo, $project, 'Test_Page_2' );
		static::assertEquals( 'Test_Page_2', $page->getTitle() );
		static::assertEquals( '<em>Test</em> page 2', $page->getDisplayTitle() );

		// Getting the unnormalized title should not call getPageInfo.
		$page = new Page( $this->pageRepo, $project, 'talk:Test Page_3' );
		$this->pageRepo->expects( $this->never() )->method( 'getPageInfo' );
		static::assertEquals( 'talk:Test Page_3', $page->getTitle( true ) );
	}

	/**
	 * A Page can be built through processing of a database row
	 */
	public function testNewFromRow(): void {
		$pageRepo = $this->createMock( PageRepository::class );
		$data = [
			[ [ 'page_title' => 'A', 'namespace' => 0 ], 'A' ],
			[ [ 'page_title' => 'B', 'namespace' => 1, 'length' => 4, ], 'Talk:B' ],
		];
		$project = $this->createMock( Project::class );
		$project->expects( static::once() )
			->method( 'getNamespaces' )
			->willReturn( [
				0 => '',
				1 => 'Talk',
			] );
		foreach ( $data as [ $row, $fullPageTitle ] ) {
			$page = Page::newFromRow( $pageRepo, $project, $row );
			static::assertEquals( $row['namespace'], $page->getNamespace() );
			static::assertInstanceOf( Page::class, $page );
			static::assertEquals( $fullPageTitle, $page->getTitle( true ) );
		}
	}

	/**
	 * A page either exists or doesn't.
	 */
	public function testExists(): void {
		$pageRepo = $this->createMock( PageRepository::class );
		$project = new Project( 'TestProject' );
		// Mock data (last element of each array is the return value).
		$data = [
			[ $project, 'Existing_page', [] ],
			[ $project, 'Missing_page', [ 'missing' => '' ] ],
		];
		$pageRepo // ->expects($this->exactly(2))
			->method( 'getPageInfo' )
			->willReturnMap( $data );

		// Existing page.
		$page1 = new Page( $this->pageRepo, $project, 'Existing_page' );
		$page1->setRepository( $pageRepo );
		static::assertTrue( $page1->exists() );

		// Missing page.
		$page2 = new Page( $this->pageRepo, $project, 'Missing_page' );
		$page2->setRepository( $pageRepo );
		static::assertFalse( $page2->exists() );
	}

	/**
	 * Test basic getters
	 */
	public function testBasicGetters(): void {
		$project = $this->createMock( Project::class );
		$project->method( 'getNamespaces' )
			->willReturn( [
				'',
				'Talk',
				'User',
			] );
		$project->expects( static::once() )
			->method( 'getLang' )
			->willReturn( 'en' );

		$pageRepo = $this->createMock( PageRepository::class );
		$pageRepo->expects( $this->once() )
			->method( 'getPageInfo' )
			->willReturn( [
				'pageid' => '42',
				'fullurl' => 'https://example.org/User:Test:123',
				'watchers' => 5000,
				'ns' => 2,
				'length' => 300,
				'pageprops' => [
					'wikibase_item' => 'Q95',
				],
			] );
		$page = new Page( $this->pageRepo, $project, 'User:Test:123' );
		$page->setRepository( $pageRepo );

		static::assertEquals( 2, $page->getNamespace() ); // Should set the pageInfo
		static::assertEquals( 42, $page->getId() );
		static::assertEquals( 'https://example.org/User:Test:123', $page->getUrl() );
		static::assertEquals( 5000, $page->getWatchers() );
		static::assertEquals( 300, $page->getLength() );
		static::assertEquals( 'User', $page->getNamespaceName() );
		static::assertEquals( 'Q95', $page->getWikidataId() );
		static::assertEquals( 'Test:123', $page->getTitleWithoutNamespace() );
		static::assertEquals( 'en', $page->getLang() ); // Intentionally defaults to project lang
		// Ensure caching
		static::assertEquals( 300, $page->getLength() );
		static::assertEquals( 2, $page->getNamespace() );
	}

	/**
	 * Test fetching of wikitext
	 */
	public function testWikitext(): void {
		$pageRepo = $this->getRealPageRepository();
		$page = new Page( $pageRepo, $this->getMockEnwikiProject(), 'Main Page' );

		// We want to do a real-world test. enwiki's Main Page does not change much,
		// and {{Main Page banner}} in particular should be there indefinitely, hopefully :)
		$content = $page->getWikitext();
		static::assertStringContainsString( '{{Main Page banner}}', $content );
	}

	/**
	 * Test fetching of HTML
	 */
	public function testHtml(): void {
		$page = new Page( $this->pageRepo, $this->getMockEnwikiProject(), 'A' );
		$date = new \DateTime( '20010101010101' ); // 01:01:01 UTC, January 1, 2001
		$this->pageRepo->expects( static::once() )
			->method( 'getRevisionIdAtDate' )
			->with( $page, $date )
			->willReturn( 1234 );
		$this->pageRepo->expects( static::once() )
			->method( 'getHTMLContent' )
			->with( $page, 1234 )
			->willReturn( '<em>Hello</em>' );
		static::assertEquals( '<em>Hello</em>', $page->getHTMLContent( $date ) );
	}

	/**
	 * Tests wikidata item getter.
	 */
	public function testWikidataItems(): void {
		$wikidataItems = [
			[
				'ips_site_id' => 'enwiki',
				'ips_site_page' => 'Google',
			],
			[
				'ips_site_id' => 'arwiki',
				'ips_site_page' => 'جوجل',
			],
		];

		$pageRepo = $this->createMock( PageRepository::class );
		$pageRepo->method( 'getPageInfo' )
			->willReturn( [
				'pageprops' => [
					'wikibase_item' => 'Q95',
				],
			] );
		$pageRepo->expects( $this->once() )
			->method( 'getWikidataItems' )
			->willReturn( $wikidataItems );
		$page = new Page( $this->pageRepo, new Project( 'TestProject' ), 'Test_Page' );
		$page->setRepository( $pageRepo );

		static::assertArraySubset( $wikidataItems, $page->getWikidataItems() );
		static::assertEquals( 2, $page->countWikidataItems() ); // Ensure that we count the above

		// If no wikidata item...
		$pageRepo2 = $this->createMock( PageRepository::class );
		$pageRepo2->expects( $this->once() )
			->method( 'getPageInfo' )
			->willReturn( [
				'pageprops' => [],
			] );
		$page2 = new Page( $this->pageRepo, new Project( 'TestProject' ), 'Test_Page' );
		$page2->setRepository( $pageRepo2 );
		static::assertNull( $page2->getWikidataId() );
		static::assertSame( 0, $page2->countWikidataItems() );
	}

	/**
	 * Tests wikidata item counter.
	 */
	public function testCountWikidataItems(): void {
		$page = new Page( $this->pageRepo, new Project( 'TestProject' ), 'Test_Page' );
		$this->pageRepo->method( 'countWikidataItems' )
			->with( $page )
			->willReturn( 2 );

		static::assertEquals( 2, $page->countWikidataItems() );
	}

	/**
	 * Fetching of revisions.
	 */
	public function testUsersEdits(): void {
		$this->pageRepo->method( 'getRevisions' )
			->with()
			->willReturn( [
				[
					'id' => '1',
					'timestamp' => '20170505100000',
					'length_change' => '1',
					'comment' => 'One',
				],
				[
					'id' => '2',
					'timestamp' => '20170506100000',
					'length_change' => '2',
					'comment' => 'Two',
				],
			] );
		$page = new Page( $this->pageRepo, new Project( 'exampleWiki' ), 'Page' );
		$user = new User( $this->createMock( UserRepository::class ), 'Testuser' );
		static::assertCount( 2, $page->getRevisions( $user ) );
		static::assertCount( 2, $page->getRevisions( $user ) ); // Test caching
		static::assertEquals( 2, $page->getNumRevisions() );
	}

	/**
	 * Cases for counting of number of edits
	 */
	public function testNumRevisions(): void {
		$pageRepo = $this->createMock( PageRepository::class );
		$pageRepo->expects( static::exactly( 2 ) )
			->method( 'getNumRevisions' )
			->willReturn( 42 );
		$pageRepo->expects( static::once() )
			->method( 'getRevisions' )
			->willReturn( [] );
		$page = new Page( $pageRepo, new Project( 'examplewiki' ), 'Page' );

		// #1: user given, so we query repo and don't cache
		static::assertEquals( 42, $page->getNumRevisions( $this->createMock( User::class ) ) );

		// #2: revisions happens to be set, so count that. doesn't query repo, but caches
		static::assertEquals( [], $page->getRevisions() );
		static::assertSame( 0, $page->getNumRevisions() );
		static::assertSame( 0, $page->getNumRevisions() ); // Test caching

		// #3: normal case, go out of our way to check
		$page = new Page( $pageRepo, new Project( 'examplewiki' ), 'Page' ); // reset caching
		static::assertEquals( 42, $page->getNumRevisions() ); // queries repo for the second time

		// #4: caching did work. If it doesn't we call the Repo method a third time
		static::assertEquals( 42, $page->getNumRevisions() );
	}

	/**
	 * Test getErros and getCheckWikiErrors.
	 */
	public function testErrors(): void {
		$checkWikiErrors = [
			[
				'error' => '61',
				'notice' => 'This is where the error is',
				'found' => '2017-08-09 00:05:09',
				'name' => 'Reference before punctuation',
				'prio' => '3',
				'explanation' => 'This is how to fix the error',
			],
		];

		$this->pageRepo->method( 'getCheckWikiErrors' )
			->willReturn( $checkWikiErrors );
		$this->pageRepo->method( 'getPageInfo' )
			->willReturn( [
				'pagelanguage' => 'en',
				'pageprops' => [
					'wikibase_item' => 'Q123',
				],
			] );
		$page = new Page( $this->pageRepo, new Project( 'exampleWiki' ), 'Page' );
		$page->setRepository( $this->pageRepo );

		static::assertEquals( $checkWikiErrors, $page->getCheckWikiErrors() );
		static::assertEquals( $checkWikiErrors, $page->getErrors() );
	}

	/**
	 * Tests for pageviews-related functions
	 */
	public function testPageviews(): void {
		$pageviewsData = [
			'items' => [
				[ 'views' => 2500 ],
				[ 'views' => 1000 ],
			],
		];

		$this->pageRepo->method( 'getPageviews' )->willReturn( $pageviewsData );
		$page = new Page( $this->pageRepo, new Project( 'exampleWiki' ), 'Page' );
		$page->setRepository( $this->pageRepo );

		static::assertEquals(
			3500,
			$page->getPageviews( '20160101', '20160201' )
		);

		static::assertEquals( 3500, $page->getLatestPageviews( 30 ) );

		// When the API fails.
		$this->pageRepo->expects( static::once() )
			->method( 'getPageviews' )
			->willThrowException( $this->createMock( BadGatewayException::class ) );
		static::assertNull( $page->getPageviews( '20230101', '20230131' ) );
		// 404, must return 0
		$pageRepo = $this->createMock( PageRepository::class );
		$pageRepo->expects( static::once() )
			->method( 'getPageviews' )
			->willThrowException( $this->createMock( ClientException::class ) );
		$page = new Page( $pageRepo, new Project( 'exampleWiki' ), 'Page' );
		static::assertSame( 0, $page->getPageviews( '20230101', '20230131' ) );
	}

	/**
	 * Is the page the Main Page?
	 */
	public function testIsMainPage(): void {
		$pageRepo = $this->getRealPageRepository();
		$page = new Page( $pageRepo, $this->getMockEnwikiProject(), 'Main Page' );
		static::assertTrue( $page->isMainPage() );
	}

	/**
	 * Links and redirects.
	 */
	public function testLinksAndRedirects(): void {
		$data = [
			'links_ext_count' => '418',
			'links_out_count' => '1085',
			'links_in_count' => '33300',
			'redirects_count' => '61',
		];
		$pageRepo = $this->createMock( PageRepository::class );
		$pageRepo->method( 'countLinksAndRedirects' )->willReturn( $data );
		$page = new Page( $this->pageRepo, new Project( 'exampleWiki' ), 'Page' );
		$page->setRepository( $pageRepo );

		static::assertEquals( $data, $page->countLinksAndRedirects() );
	}

	private function getRealPageRepository(): PageRepository {
		static::createClient();
		return new PageRepository(
			self::$container->get( 'doctrine' ),
			self::$container->get( 'cache.app' ),
			self::$container->get( 'eight_points_guzzle.client.xtools' ),
			$this->createMock( LoggerInterface::class ),
			self::$container->get( 'parameter_bag' ),
			true,
			30
		);
	}
}
