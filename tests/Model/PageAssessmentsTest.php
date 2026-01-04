<?php

declare( strict_types = 1 );

namespace App\Tests\Model;

use App\Model\Page;
use App\Model\PageAssessments;
use App\Model\Project;
use App\Repository\PageAssessmentsRepository;
use App\Repository\PageRepository;
use App\Tests\TestAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests for the PageAssessments class.
 * @covers \App\Model\PageAssessments
 */
class PageAssessmentsTest extends TestAdapter {
	/** @var ContainerInterface The Symfony localContainer ($localContainer to not override self::$container). */
	protected ContainerInterface $localContainer;

	/** @var PageAssessments */
	protected $pa;

	/** @var PageAssessmentsRepository The repository for page assessments. */
	protected $paRepo;

	/** @var Project The project we're working with. */
	protected $project;

	/**
	 * Set up client and set container, and PageAssessmentsRepository mock.
	 */
	public function setUp(): void {
		$client = static::createClient();
		$this->localContainer = $client->getContainer();

		$this->paRepo = $this->createMock( PageAssessmentsRepository::class );
		$this->paRepo->expects( $this->once() )
			->method( 'getConfig' )
			->willReturn( $this->localContainer->getParameter( 'assessments' )['en.wikipedia.org'] );

		$this->project = $this->createMock( Project::class );
	}

	/**
	 * Some of the basics.
	 */
	public function testBasics(): void {
		$pa = new PageAssessments( $this->paRepo, $this->project );

		static::assertEquals(
			$this->localContainer->getParameter( 'assessments' )['en.wikipedia.org'],
			$pa->getConfig()
		);
		static::assertTrue( $pa->isEnabled() );
		static::assertTrue( $pa->hasImportanceRatings() );
		static::assertTrue( $pa->isSupportedNamespace( 6 ) );
	}

	/**
	 * Badges
	 */
	public function testBadges(): void {
		$config = $this->paRepo->getConfig( $this->project );
		$config['class']['Unknown'] = null;
		$paRepo = $this->createMock( PageAssessmentsRepository::class );
		$paRepo->expects( $this->once() )
			->method( 'getConfig' )
			->willReturn( $config );
		$pa = new PageAssessments( $paRepo, $this->project );

		static::assertEquals(
			'https://upload.wikimedia.org/wikipedia/commons/b/bc/Featured_article_star.svg',
			$pa->getBadgeURL( 'FA' )
		);

		static::assertEquals(
			'Featured_article_star.svg',
			$pa->getBadgeURL( 'FA', true )
		);

		static::assertSame(
			'',
			$pa->getBadgeURL( 'Bonjour', true )
		);
	}

	/**
	 * Page assements.
	 */
	public function testGetAssessments(): void {
		$pageRepo = $this->createMock( PageRepository::class );
		$pageRepo->method( 'getPageInfo' )->willReturn( [
			'title' => 'Test Page',
			'ns' => 0,
		] );
		$page = new Page( $pageRepo, $this->project, 'Test_page' );

		$this->paRepo->expects( $this->exactly( 2 ) )
			->method( 'getAssessments' )
			->with( $page )
			->willReturn( [
				[
					'wikiproject' => 'Military history',
					'class' => 'Start',
					'importance' => 'Low',
				],
				[
					'wikiproject' => 'Firearms',
					'class' => 'C',
					'importance' => 'High',
				],
			] );

		$pa = new PageAssessments( $this->paRepo, $this->project );

		$assessments = $pa->getAssessments( $page );
		$assessment = $pa->getAssessment( $page );

		// Picks the first assessment.
		static::assertEquals( [
			'class' => 'Start',
			'color' => '#FFAA66',
			'category' => 'Category:Start-Class articles',
			'badge' => 'https://upload.wikimedia.org/wikipedia/commons/a/a4/Symbol_start_class.svg',
		], $assessments['assessment'] );
		static::assertEquals( [
			'color' => '#FFAA66',
			'category' => 'Category:Start-Class articles',
			'badge' => 'https://upload.wikimedia.org/wikipedia/commons/a/a4/Symbol_start_class.svg',
			'value' => 'Start',
		], $assessment );

		static::assertCount( 2, $assessments['wikiprojects'] );
	}

	public function testWrongNsAssessments(): void {
		$pageRepo = $this->createMock( PageRepository::class );
		$pageRepo->method( 'getPageInfo' )->willReturn( [
			'title' => 'Talk:Test Page',
			'ns' => 1,
		] );
		$page = new Page( $pageRepo, $this->project, 'Talk:Test_page' );
		$pa = new PageAssessments( $this->paRepo, $this->project );
		static::assertFalse( $pa->getAssessment( $page ) );
		static::assertNull( $pa->getAssessments( $page ) );
	}

	public function testUnknownAssessment(): void {
		$pageRepo = $this->createMock( PageRepository::class );
		$pageRepo->method( 'getPageInfo' )->willReturn( [
			'title' => 'Test Page',
			'ns' => 6,
		] );
		$page = new Page( $pageRepo, $this->project, 'Test_page' );
		$this->paRepo->expects( static::exactly( 2 ) )
			->method( 'getAssessments' )
			->willReturn( [] );
		$pa = new PageAssessments( $this->paRepo, $this->project );
		static::assertEquals( [
			'badge' => 'https://upload.wikimedia.org/wikipedia/commons/e/e0/Symbol_question.svg',
			'color' => '',
			'category' => 'Category:Unassessed articles',
			'value' => '???',
		], $pa->getAssessment( $page ) );
		static::assertEquals( [], $pa->getAssessments( $page ) );
	}

	public function testImportanceFromUnknownAssessment(): void {
		$pa = new PageAssessments( $this->paRepo, $this->project );
		static::assertEquals( [
			'color' => '',
			'category' => 'Category:Unknown-importance articles',
			'weight' => 0,
			'value' => '???',
		], $pa->getImportanceFromAssessment( [ 'importance' => '' ] ) );
	}

	public function testImportanceWithMissingConfig(): void {
		// To make it happy about being used once:
		$this->paRepo->getConfig( $this->project );
		// Also ensures we don't use it by mistake in the next tests.
		$paRepo = $this->createMock( PageAssessmentsRepository::class );
		$paRepo->expects( static::once() )
			->method( 'getConfig' )
			->willReturn( [] );
		$pa = new PageAssessments( $paRepo, $this->project );
		static::assertNull( $pa->getImportanceFromAssessment( [ 'importance' => '' ] ) );
	}
}
