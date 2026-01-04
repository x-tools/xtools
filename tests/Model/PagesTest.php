<?php

declare( strict_types = 1 );

namespace App\Tests\Model;

use App\Model\PageAssessments;
use App\Model\Pages;
use App\Model\Project;
use App\Model\User;
use App\Repository\PageAssessmentsRepository;
use App\Repository\PagesRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;

/**
 * Tests of the Pages class.
 * @covers \App\Model\Pages
 */
class PagesTest extends TestAdapter {
	protected Project $project;
	protected User $user;
	protected UserRepository $userRepo;
	protected PagesRepository $pagesRepo;

	/**
	 * Set up class instances and mocks.
	 */
	public function setUp(): void {
		$this->project = $this->createMock( Project::class );
		$paRepo = $this->createMock( PageAssessmentsRepository::class );
		$paRepo->method( 'getConfig' )
			->willReturn( $this->getAssessmentsConfig() );
		$pa = new PageAssessments( $paRepo, $this->project );
		$this->project->method( 'getPageAssessments' )
			->willReturn( $pa );
		$this->project->method( 'hasPageAssessments' )
			->willReturn( true );
		$this->project->method( 'getNamespaces' )
			->willReturn( [ 0 => 'Main', 1 => 'Talk', 3 => 'User_talk' ] );
		$this->userRepo = $this->createMock( UserRepository::class );
		$this->user = new User( $this->userRepo, 'Test user' );
		$this->pagesRepo = $this->createMock( PagesRepository::class );
	}

	/**
	 * Test the basic getters.
	 */
	public function testConstructor(): void {
		$pages = new Pages( $this->pagesRepo, $this->project, $this->user );
		static::assertSame( 0, $pages->getNamespace() );
		static::assertEquals( $this->project, $pages->getProject() );
		static::assertEquals( $this->user, $pages->getUser() );
		static::assertEquals( Pages::REDIR_NONE, $pages->getRedirects() );
		static::assertSame( 0, $pages->getOffset() );
	}

	/**
	 * @dataProvider provideSummaryColumnsData
	 */
	public function testSummaryColumns( string $redirects, string $deleted, array $expected ): void {
		$pages = new Pages( $this->pagesRepo, $this->project, $this->user, 0, $redirects, $deleted );
		static::assertEquals( array_merge( $expected, [
			'total-page-size',
			'average-page-size',
		] ), $pages->getSummaryColumns() );
	}

	/**
	 * @return array
	 */
	public function provideSummaryColumnsData(): array {
		return [
			[ Pages::REDIR_ALL, Pages::DEL_ALL, [ 'namespace', 'pages', 'redirects', 'deleted', 'live' ] ],
			[ Pages::REDIR_ONLY, Pages::DEL_ALL, [ 'namespace', 'redirects', 'deleted', 'live' ] ],
			[ Pages::REDIR_NONE, Pages::DEL_ALL, [ 'namespace', 'pages', 'deleted', 'live' ] ],
			[ Pages::REDIR_ALL, Pages::DEL_ONLY, [ 'namespace', 'redirects', 'deleted' ] ],
			[ Pages::REDIR_ONLY, Pages::DEL_ONLY, [ 'namespace', 'redirects', 'deleted' ] ],
			[ Pages::REDIR_NONE, Pages::DEL_ONLY, [ 'namespace', 'deleted' ] ],
			[ Pages::REDIR_ALL, Pages::DEL_NONE, [ 'namespace', 'pages', 'redirects' ] ],
			[ Pages::REDIR_ONLY, Pages::DEL_NONE, [ 'namespace', 'redirects' ] ],
			[ Pages::REDIR_NONE, Pages::DEL_NONE, [ 'namespace', 'pages' ] ],
		];
	}

	public function testResults(): void {
		$this->setPagesResults();
		$pages = new Pages( $this->pagesRepo, $this->project, $this->user, 'all', 'all' );
		$pages->setRepository( $this->pagesRepo );
		$pages->getResults(); // Ensure it does prepare
		static::assertEquals( 3, $pages->getNumResults() );
		static::assertSame( 1, $pages->getNumDeleted() );
		static::assertSame( 1, $pages->getNumRedirects() );

		static::assertEquals( [
			0 => [
				'count' => 2,
				'redirects' => 0,
				'deleted' => 1,
				'total_length' => 17,
				'avg_length' => 8.5,
			],
			1 => [
				'count' => 1,
				'redirects' => 1,
				'deleted' => 0,
				'total_length' => 10,
				'avg_length' => 10,
			],
		], $pages->getCounts() );

		$results = $pages->getResults(); // Also ensures it does cache

		static::assertEquals( [ 0, 1 ], array_keys( $results ) );
		static::assertEquals( [
			'deleted' => true,
			'namespace' => 0,
			'page_title' => 'My_fun_page',
			'full_page_title' => 'My_fun_page',
			'redirect' => true,
			'timestamp' => '20160519000000',
			'rev_id' => 16,
			'rev_length' => 5,
			'length' => null,
			'recreated' => true,
			'assessment' => [
				'class' => 'Unknown',
				'badge' => 'https://upload.wikimedia.org/wikipedia/commons/e/e0/Symbol_question.svg',
				'color' => '',
				'category' => 'Category:Unassessed articles',
				'projects' => [ 'Random' ],
			],
		], $results[0][0] );
		static::assertEquals( [
			'deleted' => false,
			'namespace' => 1,
			'page_title' => 'Google',
			'full_page_title' => 'Talk:Google',
			'redirect' => true,
			'timestamp' => '20160719000000',
			'rev_id' => 15,
			'rev_length' => 10,
			'length' => 50,
			'assessment' => [
				'class' => 'A',
				'badge' => 'https://upload.wikimedia.org/wikipedia/commons/2/25/Symbol_a_class.svg',
				'color' => '#66FFFF',
				'category' => 'Category:A-Class articles',
				'projects' => [ 'Technology', 'Internet' ],
			],
		], $results[1][0] );
		static::assertTrue( $pages->isMultiNamespace() );
		static::assertNull( $pages->getLastTimestamp() );
		static::assertEquals( 27, $pages->getTotalPageSize() );
		static::assertEquals( 9, $pages->averagePageSize() );
		static::assertSame( 'all', $pages->getNamespace() );
		static::assertEquals( 50, $pages->resultsPerPage() );
		static::assertFalse( $pages->resultsPerPage( true ) );
		static::assertEquals( [
			[ 'pap_project_title' => 'Technology', 'count' => 2 ],
			[ 'pap_project_title' => 'Random', 'count' => 1 ],
			[ 'pap_project_title' => 'Computing', 'count' => 1 ],
			[ 'pap_project_title' => 'Internet', 'count' => 1 ],
		], $pages->getWikiprojectCounts() );
		static::assertEquals( [
			'A' => 1,
			'Unknown' => 1,
			'FA' => 1,
		], $pages->getAssessmentCounts() );
	}

	/**
	 * Make sure we just spit out what the repo says,
	 * when there are more than 1 pages of results.
	 */
	public function testPaManyPages(): void {
		$project = $this->createMock( Project::class );
		$pagesRepo = $this->createMock( PagesRepository::class );
		$pagesRepo->expects( static::once() )
			->method( 'countPagesCreated' )
			->willReturn( [ [
				'namespace' => 0,
				'count' => 5001,
				'total_length' => 0,
				'deleted' => 0,
				'redirects' => 0,
			] ] );
		$pages = new Pages( $pagesRepo, $project, $this->user, 'all', 'all' );
		static::assertEquals( 5001, $pages->getNumPages() );
		$value = [ 'Exactly the value given as input.' ];
		$pagesRepo->expects( static::once() )
			->method( 'getWikiprojectCounts' )
			->willReturn( $value );
		static::assertSame( $value, $pages->getWikiprojectCounts() );
		$pagesRepo->expects( static::once() )
			->method( 'getAssessmentCounts' )
			->willReturn( $value );
		static::assertSame( $value, $pages->getAssessmentCounts() );
	}

	public function testSingleNamespace(): void {
		// Also does the ProofreadPage tests.
		$project = $this->createMock( Project::class );
		$project->method( 'hasPageAssessments' )
			->willReturn( false );
		$project->method( 'isPrpPage' )
			->with( 104 )
			->willReturn( true );
		$project->method( 'getNamespaces' )
			->willReturn( [ 0 => 'Main', 1 => 'Talk', 104 => 'Page' ] );
		$pages = new Pages( $this->pagesRepo, $project, $this->user, 104, 'all' );
		$pagesRepo = $this->createMock( PagesRepository::class );
		$pagesRepo->expects( static::once() )
			->method( 'getPagesCreated' )
			->willReturn( [ [
				'namespace' => 104,
				'type' => 'rev',
				'page_title' => 'AAA',
				'redirect' => '1',
				'rev_length' => 2,
				'length' => 20,
				'timestamp' => '20250101000000',
				'rev_id' => 42,
				'recreated' => null,
				'prp_quality' => 3,
				'was_redirect' => null,
			] ] );
		$pagesRepo->expects( static::once() )
			->method( 'countPagesCreated' )
			->willReturn( [ [
				'namespace' => 104,
				'count' => 1,
				'deleted' => 0,
				'redirects' => 1,
				'total_length' => 20,
				'prp_quality0' => 0,
				'prp_quality1' => 0,
				'prp_quality2' => 0,
				'prp_quality3' => 1,
				'prp_quality4' => 0,
			] ] );
		$pages->setRepository( $pagesRepo );
		static::assertFalse( $pages->isMultiNamespace() );
		static::assertEquals( '2025-01-01T00:00:00Z', $pages->getLastTimestamp() );
		static::assertSame( 1, $pages->getNumPages() );
		$counts = $pages->getCounts();
		static::assertSame( 1, $counts[104]['prp_quality3'] );
	}

	public function setPagesResults(): void {
		$this->pagesRepo->expects( static::exactly( 2 ) )
			->method( 'getPagesCreated' )
			->willReturn( [
				[
					'namespace' => 1,
					'type' => 'rev',
					'page_title' => 'Google',
					'redirect' => '1',
					'rev_length' => 10,
					'length' => 50,
					'timestamp' => '20160719000000',
					'rev_id' => 15,
					'recreated' => null,
					'pa_class' => 'A',
					'was_redirect' => null,
					'pap_project_title' => '["Technology","Internet"]',
				], [
					'namespace' => 0,
					'type' => 'arc',
					'page_title' => 'My_fun_page',
					'redirect' => '0',
					'rev_length' => 5,
					'length' => null,
					'timestamp' => '20160519000000',
					'rev_id' => 16,
					'recreated' => 1,
					'pa_class' => null,
					'was_redirect' => '1',
					'pap_project_title' => '["Random"]',
				], [
					'namespace' => 0,
					'type' => 'rev',
					'page_title' => 'Foo_bar',
					'redirect' => '0',
					'rev_length' => 12,
					'length' => 50,
					'timestamp' => '20160101000000',
					'rev_id' => 17,
					'recreated' => null,
					'pa_class' => 'FA',
					'was_redirect' => null,
					'pap_project_title' => '["Computing","Technology"]',
				],
			] );
		$this->pagesRepo->expects( $this->once() )
			->method( 'countPagesCreated' )
			->willReturn( [
				[
					'namespace' => 0,
					'count' => 2,
					'deleted' => 1,
					'redirects' => 0,
					'total_length' => 17,
				], [
					'namespace' => 1,
					'count' => 1,
					'deleted' => 0,
					'redirects' => 1,
					'total_length' => 10,
				],
			] );
	}

	/**
	 * Users get tooltips containing deletion summaries, for deleted pages.
	 * @dataProvider deletionSummaryProvider
	 * @param array|null $data
	 * @param int $ns
	 * @param string $title
	 * @param string $offset
	 * @param string|null $result
	 */
	public function testDeletionSummary(
		?array $data,
		int $ns,
		string $title,
		string $offset,
		?string $result
	): void {
		$project = new Project( 'testWiki' );
		$project->setRepository( $this->getProjectRepo() );
		$this->pagesRepo->expects( static::once() )
			->method( 'getDeletionSummary' )
			->willReturn( $data );
		$pages = new Pages( $this->pagesRepo, $project, $this->user );
		$pages->setRepository( $this->pagesRepo );
		static::assertEquals(
			$result,
			$pages->getDeletionSummary( $ns, $title, $offset )
		);
	}

	public function deletionSummaryProvider(): array {
		return [ [
			[
				'actor_name' => 'MusikAnimal',
				'comment_text' => '[[WP:AfD|Articles for deletion]]',
				'log_timestamp' => '20210108224022',
			],
			0,
			'Foobar',
			'20210108224000',
			"2021-01-08 22:40 (<a target='_blank' href=\"https://test.example.org/wiki/User:MusikAnimal\">" .
				"MusikAnimal</a>): <i><a target='_blank' href='https://test.example.org/wiki/WP:AfD'>" .
				"Articles for deletion</a></i>",
			], [
			[],
			0,
			'Foobar',
			'202101082240000',
			null,
			] ];
	}

	/**
	 * Mock assessments configuration.
	 * @return array
	 */
	private function getAssessmentsConfig(): array {
		return [
			'class' => [
				'FA' => [
					'badge' => 'b/bc/Featured_article_star.svg',
					'color' => '#9CBDFF',
					'category' => 'Category:FA-Class articles',
				],
				'A' => [
					'badge' => '2/25/Symbol_a_class.svg',
					'color' => '#66FFFF',
					'category' => 'Category:A-Class articles',
				],
				'Unknown' => [
					'badge' => 'e/e0/Symbol_question.svg',
					'color' => '',
					'category' => 'Category:Unassessed articles',
				],
			],
		];
	}
}
