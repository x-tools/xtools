<?php

declare(strict_types = 1);

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
use DateTime;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GuzzleHttp;
use ReflectionClass;

/**
 * Tests for PageInfo.
 * @covers \App\Model\PageInfo
 * @covers \App\Model\PageInfoApi
 */
class PageInfoTest extends TestAdapter
{
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
    public function setUp(): void
    {
        $autoEditsHelper = $this->getAutomatedEditsHelper();
        /** @var I18nHelper $i18nHelper */
        $i18nHelper = static::getContainer()->get('app.i18n_helper');
        $this->project = $this->getMockEnwikiProject();
        $this->pageRepo = $this->createMock(PageRepository::class);
        $this->page = $this->createMock(Page::class);
        $this->editRepo = $this->createMock(EditRepository::class);
        $this->editRepo->method('getAutoEditsHelper')
            ->willReturn($autoEditsHelper);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->pageInfoRepo = $this->createMock(PageInfoRepository::class);
        $this->pageInfoRepo->method('getMaxPageRevisions')
            ->willReturn(static::getContainer()->getParameter('app.max_page_revisions'));
        $this->pageInfo = new PageInfo(
            $this->pageInfoRepo,
            $i18nHelper,
            $autoEditsHelper,
            $this->page
        );

        // Used to set a few private properties without having to recreate everything
        $this->reflectionClass = new ReflectionClass($this->pageInfo);
    }

    /**
     * Number of revisions
     */
    public function testNumRevisions(): void
    {
        $this->setupData();
        $this->page->expects(static::once())
            ->method('getNumRevisions')
            ->willReturn(10);
        $this->pageInfo->prepareData();
        static::assertEquals(10, $this->pageInfo->getNumRevisions());
        // Should be cached (will error out if repo's getNumRevisions is called again).
        static::assertEquals(10, $this->pageInfo->getNumRevisions());
    }

    /**
     * Number of revisions processed, based on app.max_page_revisions
     * @dataProvider revisionsProcessedProvider
     * @param int $numRevisions
     * @param int $assertion
     */
    public function testRevisionsProcessed(int $numRevisions, int $assertion): void
    {
        $this->page->method('getNumRevisions')->willReturn($numRevisions);
        static::assertEquals(
            $this->pageInfo->getNumRevisionsProcessed(),
            $assertion
        );
    }

    /**
     * Data for self::testRevisionsProcessed().
     * @return int[]
     */
    public function revisionsProcessedProvider(): array
    {
        return [
            [1000000, 50000],
            [10, 10],
        ];
    }

    /**
     * Whether there are too many revisions to process.
     */
    public function testTooManyRevisions(): void
    {
        $this->page->expects(static::once())
            ->method('getNumRevisions')
            ->willReturn(1000000);
        static::assertTrue($this->pageInfo->tooManyRevisions());
    }

    /**
     * Various bot-related methods
     */
    public function testBots(): void
    {
        $this->pageInfoRepo->expects(static::once())
            ->method('getBotData')
            ->willReturn([
                [
                    'username' => 'Foo',
                    'count' => 3,
                    'current' => '1',
                ],
                [
                    'username' => 'Bar',
                    'count' => 12,
                    'current' => '0',
                ],
            ]);
        static::assertEquals([
            'Foo' => [
                'count' => 3,
                'current' => true,
            ],
            'Bar' => [
                'count' => 12,
                'current' => false,
            ],
        ], $this->pageInfo->getBots());
        static::assertEquals(2, $this->pageInfo->getNumBots());
        static::assertEquals(15, $this->pageInfo->getBotRevisionCount());
        static::assertEquals(15, $this->pageInfo->getBotRevisionCount()); // second time for caching
    }

    public function testTopEditorsByEditCount(): void
    {
        $this->pageInfoRepo->expects(static::once())
            ->method('getTopEditorsByEditCount')
            ->willReturn([
                [
                    'username' => 'Foo',
                    'count' => 22,
                    'minor' => 6,
                    'first_revid' => 100,
                    'first_timestamp' => '10000101000100',
                    'latest_revid' => 300,
                    'latest_timestamp' => '10000101000300',
                ],
                [
                    'username' => 'Bar',
                    'count' => 20,
                    'minor' => 4,
                    'first_revid' => 200,
                    'first_timestamp' => '10000101000200',
                    'latest_revid' => 400,
                    'latest_timestamp' => '10000101000400',
                ],
            ]);
        static::assertEquals([
            [
                'rank' => 1,
                'username' => 'Foo',
                'count' => 22,
                'minor' => 6,
                'first_edit' => [
                    'id' => 100,
                    'timestamp' => '1000-01-01T00:01:00Z',
                ],
                'latest_edit' => [
                    'id' => 300,
                    'timestamp' => '1000-01-01T00:03:00Z',
                ],
            ],
            [
                'rank' => 2,
                'username' => 'Bar',
                'count' => 20,
                'minor' => 4,
                'first_edit' => [
                    'id' => 200,
                    'timestamp' => '1000-01-01T00:02:00Z',
                ],
                'latest_edit' => [
                    'id' => 400,
                    'timestamp' => '1000-01-01T00:04:00Z',
                ],
            ],
        ], $this->pageInfo->getTopEditorsByEditCount());
        // Test caching
        $this->pageInfo->getTopEditorsByEditCount();
    }

    public function testLinksAndRedirects(): void
    {
        $this->setupData();
        $this->pageInfo->prepareData(); // Ensure we don't call the revisions (the second time will complain).
        $this->page->expects(static::once())
            ->method('countLinksAndRedirects')
            ->willReturn([
                'links_ext_count' => 5,
                'links_out_count' => 3,
                'links_in_count' => 10,
                'redirects_count' => 0,
            ]);
        static::assertEquals(5, $this->pageInfo->linksExtCount());
        static::assertEquals(3, $this->pageInfo->linksOutCount());
        static::assertEquals(10, $this->pageInfo->linksInCount());
        static::assertEquals(0, $this->pageInfo->redirectsCount());
    }

    public function testBugs(): void
    {
        $this->page->expects(static::once())
            ->method('getErrors')
            ->willReturn([]);
        static::assertSame([], $this->pageInfo->getBugs());
        static::assertSame([], $this->pageInfo->getBugs()); // Ensure caching
        static::assertEquals(0, $this->pageInfo->numBugs());
    }

    /**
     * Test some of the more important getters.
     */
    public function testGetters(): void
    {
        $this->setupData();
        $this->pageInfo->prepareData();

        static::assertEquals(
            32,
            $this->pageInfo->getFirstEdit()->getId()
        );
        static::assertEquals(
            60,
            $this->pageInfo->getLastEdit()->getId()
        );
        static::assertEquals(3, $this->pageInfo->getNumEditors());
        static::assertEquals(2, $this->pageInfo->getAnonCount());
        static::assertEquals(40, $this->pageInfo->anonPercentage());
        static::assertEquals(3, $this->pageInfo->getMinorCount());
        static::assertEquals(60, $this->pageInfo->minorPercentage());
        static::assertEquals(1, $this->pageInfo->getBotRevisionCount());
        static::assertEquals(63, $this->pageInfo->getTotalDays());
        static::assertEquals(12, (int) $this->pageInfo->averageDaysPerEdit());
        static::assertEquals(0, (int) $this->pageInfo->editsPerDay());
        static::assertEquals(2.4, $this->pageInfo->editsPerMonth());
        static::assertEquals(5, $this->pageInfo->editsPerYear());
        static::assertEquals(1.7, $this->pageInfo->editsPerEditor());
        static::assertEquals(2, $this->pageInfo->getAutomatedCount());
        static::assertEquals(1, $this->pageInfo->getRevertCount());

        static::assertEquals(80, $this->pageInfo->topTenPercentage());
        static::assertEquals(4, $this->pageInfo->getTopTenCount());

        static::assertEquals(1, $this->pageInfo->getMaxAddition()->getId());
        static::assertEquals(32, $this->pageInfo->getMaxDeletion()->getId());

        static::assertEquals(
            ['Mick Jagger', '192.168.0.2', '192.168.0.1'],
            array_keys($this->pageInfo->getEditors())
        );
        static::assertEquals(
            [
                'label' =>'Mick Jagger',
                'value' => 2,
                'percentage' => 50,
            ],
            $this->pageInfo->topTenEditorsByEdits()[0]
        );
        static::assertEquals(
            [
                'label' =>'Mick Jagger',
                'value' => 30,
                'percentage' => 100,
            ],
            $this->pageInfo->topTenEditorsByAdded()[0]
        );

        // Top 10 counts should not include bots.
        static::assertFalse(
            array_search(
                'XtoolsBot',
                array_column($this->pageInfo->topTenEditorsByEdits(), 'label')
            )
        );
        static::assertFalse(
            array_search(
                'XtoolsBot',
                array_column($this->pageInfo->topTenEditorsByAdded(), 'label')
            )
        );

        static::assertEquals(['Mick Jagger'], $this->pageInfo->getHumans(1));

        static::assertEquals(3, $this->pageInfo->getMaxEditsPerMonth());

        static::assertContains(
            'AutoWikiBrowser',
            array_keys($this->pageInfo->getTools())
        );

        static::assertEquals(1, $this->pageInfo->numDeletedRevisions());
        static::assertEquals(2, $this->pageInfo->getMobileCount());
        static::assertEquals(2, $this->pageInfo->getVisualCount());
    }

    /**
     * Make sure we don't divide by 0
     */
    public function testEmptyFallbacks(): void
    {
        $this->page->expects(static::once())
            ->method('getRevisions')
            ->willReturn([
            [
                'id' => 1,
                'timestamp' => '20010203040506',
                'minor' => '0',
                'length' => '30',
                'length_change' => '30',
                'username' => null,
                'comment' => 'Foo bar',
                'rev_sha1' => 'aaaaaa',
                'tags' => '["mobile edit"]',
            ],
            ]);
        $this->pageInfoRepo->expects(static::once())
            ->method('getEdit')
            ->willReturnCallback(fn($page, $rev) => new Edit($this->editRepo, $this->userRepo, $page, $rev));
        $this->pageInfo->prepareData();
        static::assertEquals(0, $this->pageInfo->editsPerDay());
        static::assertEquals(0, $this->pageInfo->editsPerMonth());
        static::assertEquals(0, $this->pageInfo->editsPerYear());
        static::assertEquals(0, $this->pageInfo->editsPerEditor());
    }

    public function testCountHistory(): void
    {
        $this->page->expects(static::once())
            ->method('getRevisions')
            ->willReturn([
            [
                'id' => 1,
                'timestamp' => (new DateTime('now'))->format('YmdHis'),
                'minor' => '0',
                'length' => '30',
                'length_change' => '30',
                'username' => null,
                'comment' => 'Foo bar',
                'rev_sha1' => 'aaaaaa',
                'tags' => '["mobile edit"]',
            ],
            ]);
        $this->pageInfoRepo->expects(static::once())
            ->method('getEdit')
            ->willReturnCallback(fn($page, $rev) => new Edit($this->editRepo, $this->userRepo, $page, $rev));
        $this->pageInfo->prepareData();
        static::assertEquals([1, 1, 1, 1], array_values($this->pageInfo->getCountHistory()));
    }


    /**
     * Test that the data for each individual month and year is correct.
     */
    public function testMonthYearCounts(): void
    {
        $this->setupData();
        $this->pageInfo->prepareData();

        $yearMonthCounts = $this->pageInfo->getYearMonthCounts();

        static::assertEquals([2016], array_keys($yearMonthCounts));
        static::assertEquals(['2016'], $this->pageInfo->getYearLabels());
        static::assertArraySubset([
            'all' => 5,
            'minor' => 3,
            'anon' => 2,
            'automated' => 2,
            'size' => 20,
        ], $yearMonthCounts[2016]);

        static::assertEquals(
            ['08', '09', '10', '11', '12'],
            array_keys($yearMonthCounts[2016]['months'])
        );
        static::assertEquals(
            ['2016-08', '2016-09', '2016-10', '2016-11', '2016-12'],
            $this->pageInfo->getMonthLabels()
        );

        // Just test a few, not every month.
        static::assertArraySubset([
            'all' => 3,
            'minor' => 2,
            'anon' => 2,
            'automated' => 2,
        ], $yearMonthCounts[2016]['months']['10']);
    }


    /**
     * Test data around log events.
     */
    public function testLogEvents(): void
    {
        $this->setupData();

        $this->pageInfoRepo->expects(static::once())
            ->method('getLogEvents')
            ->willReturn([
                [
                    'log_type' => 'protect',
                    'timestamp' => '20160705000000',
                ],
                [
                    'log_type' => 'delete',
                    'timestamp' => '20160905000000',
                ],
                [
                    'log_type' => 'delete',
                    'timestamp' => '20160905000001',
                ],
                [
                    'log_type' => 'move',
                    'timestamp' => '20161005000000',
                ],
            ]);

        $this->pageInfo->prepareData();
        $yearMonthCounts = $this->pageInfo->getYearMonthCounts();

        // Just test a few, not every month.
        static::assertEquals([
            'protections' => 1,
            'deletions' => 2,
            'moves' => 1,
        ], $yearMonthCounts[2016]['events']);
    }

    /**
     * Make sure that setLogEvents does nothing when yearMonthCounts is not set
     */
    public function testLogEventsFallback(): void
    {
        // Intentionally don't setup, so addYearMonthCountEntry never gets called
        $this->pageInfoRepo->expects(static::once())
            ->method('getLogEvents')
            ->willReturn([['timestamp' => 'yesterday']]);
        $this->pageInfo->prepareData(); // Will call setLogEvents under the hood
        static::assertSame([], $this->pageInfo->getYearMonthCounts());
    }

    /**
     * Set repository returns
     */
    private function setupData(): void
    {
        $revisions = [
            [
                'id' => 1,
                'timestamp' => '20160801000001',
                'minor' => '0',
                'length' => '30',
                'length_change' => '30',
                'username' => 'Mick Jagger',
                'comment' => 'Foo bar',
                'rev_sha1' => 'aaaaaa',
                'tags' => '["mobile edit"]',
            ],
            [
                'id' => 32,
                'timestamp' => '20160801000000',
                'minor' => '1',
                'length' => '25',
                'length_change' => '-5',
                'username' => 'Mick Jagger',
                'comment' => 'Blah',
                'rev_sha1' => 'bbbbbb',
                'tags' => '[]',
            ],
            [
                'id' => 40,
                'timestamp' => '20161003000000',
                'minor' => '0',
                'length' => '15',
                'length_change' => '1000',
                'username' => '192.168.0.1',
                'comment' => 'Weeee using [[WP:AWB|AWB]]',
                'rev_sha1' => 'cccccc',
                'tags' => '["mobile edit","visualeditor"]',
            ],
            [
                'id' => 50,
                'timestamp' => '20161003010000',
                'minor' => '1',
                'length' => '25',
                'length_change' => '-1000',
                'username' => '192.168.0.2',
                'comment' => 'I undo your edit cuz it bad',
                'rev_sha1' => 'bbbbbb',
                'tags' => '["visualeditor"]',
            ],
            [
                'id' => 60,
                'timestamp' => '20161003020000',
                'minor' => '1',
                'length' => '20',
                'length_change' => '-5',
                'username' => 'Offensive username',
                'comment' => 'Weeee using [[WP:AWB|AWB]]',
                'rev_sha1' => 'ddddd',
                'rev_deleted' => Edit::DELETED_USER,
                'tags' => '[]',
            ],
        ];
        $this->page->expects(static::once())
            ->method('getRevisions')
            ->willReturn($revisions);
        $this->pageInfoRepo->expects(static::exactly(count($revisions)))
            ->method('getEdit')
            ->willReturnCallback(fn($page, $rev) => new Edit($this->editRepo, $this->userRepo, $page, $rev));
        $this->pageInfoRepo->expects(static::once())
            ->method('getBotData')
            ->willReturn([['count' => 1, 'username' => 'XtoolsBot', 'current' => 1]]);
        $this->pageInfoRepo->expects(static::any())
            ->method('getMaxPageRevisions')
            ->willReturn(10);
    }

    /**
     * Test prose stats parser.
     */
    public function testProseStats(): void
    {
        // We'll use a live page to better test the prose stats parser.
        $client = new GuzzleHttp\Client();
        $ret = $client->request('GET', 'https://en.wikipedia.org/api/rest_v1/page/html/Hanksy/747629772')
            ->getBody()
            ->getContents();
        $this->page->expects(static::once())
            ->method('getHTMLContent')
            ->willReturn($ret);

        static::assertEquals([
            'bytes' => 1539,
            'characters' => 1539,
            'words' => 261,
            'references' => 13,
            'unique_references' => 12,
            'sections' => 2,
        ], $this->pageInfo->getProseStats());
        // Test caching
        $this->pageInfo->getProseStats();
    }

    /**
     * Ensure we react appropriately when getHTMLContent fails
     */
    public function testProseStatFallback(): void
    {
        $this->page->expects(static::once())
            ->method('getHTMLContent')
            ->willThrowException($this->createMock(BadGateWayException::class));
        static::assertNull($this->pageInfo->getProseStats());
    }

    /**
     * Ensure we don't divide by 0 when the page had no added text
     */
    public function testZeroAddedBytes(): void
    {
        $this->page->expects(static::once())
            ->method('getRevisions')
            ->willReturn([
                [
                    'id' => 1,
                    'timestamp' => '20160801000001',
                    'minor' => '0',
                    'length' => '0',
                    'length_change' => '0',
                    'username' => 'Mick Jagger',
                    'comment' => 'Foo bar',
                    'rev_sha1' => 'aaaaaa',
                    'tags' => '["mobile edit"]',
                ],
            ]);
        $this->pageInfoRepo->expects(static::once())
            ->method('getEdit')
            ->willReturnCallback(fn($page, $rev) => new Edit($this->editRepo, $this->userRepo, $page, $rev));
        $this->pageInfo->prepareData();
        static::assertEquals(0, $this->pageInfo->topTenEditorsByAdded()[0]['percentage']);
    }

    /**
     * Various methods involving start/end dates.
     */
    public function testWithDates(): void
    {
        $this->setupData();
        $this->pageInfo->prepareData();

        $start = $this->reflectionClass->getProperty('start');
        $start->setValue($this->pageInfo, strtotime('2016-06-30'));

        $end = $this->reflectionClass->getProperty('end');
        $end->setValue($this->pageInfo, strtotime('2016-10-14'));

        $meth = $this->reflectionClass->getMethod('getLastDay');
        $lastDayOfMonth = $meth->invoke($this->pageInfo);

        static::assertTrue($this->pageInfo->hasDateRange());
        static::assertEquals('2016-06-30', $this->pageInfo->getStartDate());
        static::assertEquals('2016-10-14', $this->pageInfo->getEndDate());
        static::assertEquals([
            'start' => '2016-06-30',
            'end' => '2016-10-14',
        ], $this->pageInfo->getDateParams());
        static::assertEquals(strtotime('2016-10-31'), $lastDayOfMonth);

        // Uses length of last edit because there is a date range.
        static::assertEquals(20, $this->pageInfo->getLength());

        // Pageviews with a date range.
        $this->page->expects(static::once())
            ->method('getPageviews')
            ->willReturn(1500);
        static::assertEquals(1500, $this->pageInfo->getPageviews()['count']);

        // no dates
        $start->setValue($this->pageInfo, null);
        $end->setValue($this->pageInfo, null);
        $this->page->expects(static::once())
            ->method('getLength')
            ->willReturn(42);
        static::assertEquals([], $this->pageInfo->getDateParams());
        static::assertEquals(42, $this->pageInfo->getLength());
    }

    /**
     * Transclusion counts.
     */
    public function testTransclusionData(): void
    {
        $pageInfoRepo = $this->createMock(PageInfoRepository::class);
        $pageInfoRepo->expects(static::once())
            ->method('getTransclusionData')
            ->willReturn([
                'categories' => 3,
                'templates' => 5,
                'files' => 2,
            ]);
        $this->pageInfo->setRepository($pageInfoRepo);

        static::assertEquals(3, $this->pageInfo->getNumCategories());
        static::assertEquals(5, $this->pageInfo->getNumTemplates());
        static::assertEquals(2, $this->pageInfo->getNumFiles());
    }

    public function testPageviews(): void
    {
        $this->page->expects(static::exactly($this->pageInfo->hasDateRange() ? 1 : 0))
            ->method('getPageviews')
            ->willReturn(1500);
        $this->page->expects(static::exactly($this->pageInfo->hasDateRange() ? 0 : 1))
            ->method('getLatestPageviews')
            ->willReturn(1500);

        static::assertEquals([
            'count' => 1500,
            'formatted' => '1,500',
            'tooltip' => '',
        ], $this->pageInfo->getPageviews());

        static::assertEquals(PageInfoApi::PAGEVIEWS_OFFSET, $this->pageInfo->getPageviewsOffset());
    }

    public function testPageviewsFailing(): void
    {
        $this->page->expects(static::exactly($this->pageInfo->hasDateRange() ? 1 : 0))
            ->method('getPageviews')
            ->willReturn(null);
        $this->page->expects(static::exactly($this->pageInfo->hasDateRange() ? 0 : 1))
            ->method('getLatestPageviews')
            ->willReturn(null);

        static::assertEquals([
            'count' => null,
            'formatted' => 'Data unavailable',
            'tooltip' => 'There was an error connecting to the Pageviews API. ' .
                'Try refreshing this page or try again later.',
        ], $this->pageInfo->getPageviews());
    }
}
