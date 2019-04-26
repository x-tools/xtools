<?php
/**
 * This file contains only the ArticleInfoTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Model;

use AppBundle\Helper\I18nHelper;
use AppBundle\Model\ArticleInfo;
use AppBundle\Model\Edit;
use AppBundle\Model\Page;
use AppBundle\Model\Project;
use AppBundle\Repository\ArticleInfoRepository;
use AppBundle\Repository\PageRepository;
use AppBundle\Repository\ProjectRepository;
use GuzzleHttp;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\AppBundle\TestAdapter;

/**
 * Tests for ArticleInfo.
 */
class ArticleInfoTest extends TestAdapter
{
    /** @var ArticleInfo The article info instance. */
    protected $articleInfo;

    /** @var Page The page instance. */
    protected $page;

    /** @var Project The project instance. */
    protected $project;

    /** @var \ReflectionClass Hack to test private methods. */
    private $reflectionClass;

    /**
     * Set up shared mocks and class instances.
     */
    public function setUp(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $this->project = new Project('en.wikipedia.org');
        $this->page = new Page($this->project, 'Test page');
        $this->articleInfo = new ArticleInfo($this->page, $container);

        $stack = new RequestStack();
        $session = new Session();
        $i18nHelper = new I18nHelper($container, $stack, $session);
        $this->articleInfo->setI18nHelper($i18nHelper);

        // Don't care that private methods "shouldn't" be tested...
        // In ArticleInfo they are all super testworthy and otherwise fragile.
        $this->reflectionClass = new \ReflectionClass($this->articleInfo);
    }

    /**
     * Number of revisions
     */
    public function testNumRevisions(): void
    {
        $pageRepo = $this->getMock(PageRepository::class);
        $pageRepo->expects($this->once())
            ->method('getNumRevisions')
            ->willReturn(10);
        $this->page->setRepository($pageRepo);
        static::assertEquals(10, $this->articleInfo->getNumRevisions());
        // Should be cached (will error out if repo's getNumRevisions is called again).
        static::assertEquals(10, $this->articleInfo->getNumRevisions());
    }

    /**
     * Number of revisions processed, based on app.max_page_revisions
     * @dataProvider revisionsProcessedProvider
     * @param int $numRevisions
     * @param int $assertion
     */
    public function testRevisionsProcessed(int $numRevisions, int $assertion): void
    {
        $pageRepo = $this->getMock(PageRepository::class);
        $pageRepo->method('getNumRevisions')->willReturn($numRevisions);
        $this->page->setRepository($pageRepo);
        static::assertEquals(
            $this->articleInfo->getNumRevisionsProcessed(),
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
        $pageRepo = $this->getMock(PageRepository::class);
        $pageRepo->expects($this->once())
            ->method('getNumRevisions')
            ->willReturn(1000000);
        $this->page->setRepository($pageRepo);
        static::assertTrue($this->articleInfo->tooManyRevisions());
    }

    /**
     * Getting the number of edits made to the page by current or former bots.
     */
    public function testBotRevisionCount(): void
    {
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
            $this->articleInfo->getBotRevisionCount($bots)
        );
    }

    public function testLinksAndRedirects(): void
    {
        $pageRepo = $this->getMock(PageRepository::class);
        $pageRepo->expects($this->once())
            ->method('countLinksAndRedirects')
            ->willReturn([
                'links_ext_count' => 5,
                'links_out_count' => 3,
                'links_in_count' => 10,
                'redirects_count' => 0,
            ]);
        $this->page->setRepository($pageRepo);
        static::assertEquals(5, $this->articleInfo->linksExtCount());
        static::assertEquals(3, $this->articleInfo->linksOutCount());
        static::assertEquals(10, $this->articleInfo->linksInCount());
        static::assertEquals(0, $this->articleInfo->redirectsCount());
    }

    /**
     * Test some of the more important getters.
     */
    public function testGetters(): void
    {
        $edits = $this->setupData();

        static::assertEquals(3, $this->articleInfo->getNumEditors());
        static::assertEquals(2, $this->articleInfo->getAnonCount());
        static::assertEquals(50, $this->articleInfo->anonPercentage());
        static::assertEquals(2, $this->articleInfo->getMinorCount());
        static::assertEquals(50, $this->articleInfo->minorPercentage());
        static::assertEquals(1, $this->articleInfo->getBotRevisionCount());
        static::assertEquals(93, $this->articleInfo->getTotalDays());
        static::assertEquals(23, (int) $this->articleInfo->averageDaysPerEdit());
        static::assertEquals(0, (int) $this->articleInfo->editsPerDay());
        static::assertEquals(1.3, $this->articleInfo->editsPerMonth());
        static::assertEquals(4, $this->articleInfo->editsPerYear());
        static::assertEquals(1.3, $this->articleInfo->editsPerEditor());
        static::assertEquals(1, $this->articleInfo->getAutomatedCount());
        static::assertEquals(1, $this->articleInfo->getRevertCount());

        static::assertEquals(100, $this->articleInfo->topTenPercentage());
        static::assertEquals(4, $this->articleInfo->getTopTenCount());

        static::assertEquals(
            $edits[0]->getId(),
            $this->articleInfo->getFirstEdit()->getId()
        );
        static::assertEquals(
            $edits[3]->getId(),
            $this->articleInfo->getLastEdit()->getId()
        );

        static::assertEquals(1, $this->articleInfo->getMaxAddition()->getId());
        static::assertEquals(32, $this->articleInfo->getMaxDeletion()->getId());

        static::assertEquals(
            ['Mick Jagger', '192.168.0.1', '192.168.0.2'],
            array_keys($this->articleInfo->getEditors())
        );
        static::assertEquals(
            [
                'label' =>'Mick Jagger',
                'value' => 2,
                'percentage' => 50,
            ],
            $this->articleInfo->topTenEditorsByEdits()[0]
        );
        static::assertEquals(
            [
                'label' =>'Mick Jagger',
                'value' => 30,
                'percentage' => 100,
            ],
            $this->articleInfo->topTenEditorsByAdded()[0]
        );

        // Top 10 counts should not include bots.
        static::assertFalse(
            array_search(
                'XtoolsBot',
                array_column($this->articleInfo->topTenEditorsByEdits(), 'label')
            )
        );
        static::assertFalse(
            array_search(
                'XtoolsBot',
                array_column($this->articleInfo->topTenEditorsByAdded(), 'label')
            )
        );

        static::assertEquals(2, $this->articleInfo->getMaxEditsPerMonth());

        static::assertContains(
            'AutoWikiBrowser',
            array_keys($this->articleInfo->getTools())
        );
    }

    /**
     * Test that the data for each individual month and year is correct.
     */
    public function testMonthYearCounts(): void
    {
        $this->setupData();

        $yearMonthCounts = $this->articleInfo->getYearMonthCounts();

        static::assertEquals([2016], array_keys($yearMonthCounts));
        static::assertArraySubset([
            'all' => 4,
            'minor' => 2,
            'anon' => 2,
            'automated' => 1,
            'size' => 25,
        ], $yearMonthCounts[2016]);

        static::assertEquals(
            ['07', '08', '09', '10', '11', '12'],
            array_keys($yearMonthCounts[2016]['months'])
        );

        // Just test a few, not every month.
        static::assertArraySubset([
            'all' => 1,
            'minor' => 0,
            'anon' => 0,
            'automated' => 0,
        ], $yearMonthCounts[2016]['months']['07']);
        static::assertArraySubset([
            'all' => 2,
            'minor' => 1,
            'anon' => 2,
            'automated' => 1,
        ], $yearMonthCounts[2016]['months']['10']);
    }


    /**
     * Test data around log events.
     */
    public function testLogEvents(): void
    {
        $this->setupData();

        $articleInfoRepo = $this->getMock(ArticleInfoRepository::class);
        $articleInfoRepo->expects($this->once())
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
            ]);
        $this->articleInfo->setRepository($articleInfoRepo);

        $method = $this->reflectionClass->getMethod('setLogsEvents');
        $method->setAccessible(true);
        $method->invoke($this->articleInfo);

        $yearMonthCounts = $this->articleInfo->getYearMonthCounts();

        // Just test a few, not every month.
        static::assertEquals([
            'protections' => 1,
            'deletions' => 1,
        ], $yearMonthCounts[2016]['events']);
    }

    /**
     * Use ReflectionClass to set up some data and populate the class properties for testing.
     *
     * We don't care that private methods "shouldn't" be tested...
     * In ArticleInfo the update methods are all super testworthy and otherwise fragile.
     *
     * @return Edit[] Array of Edit objects that represent the revision history.
     */
    private function setupData(): array
    {
        // ArticleInfo::updateToolCounts relies on there being entries in
        // semi_automated.yml for the project the edits were made on.
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getOne')
            ->willReturn([
                'url' => 'https://en.wikipedia.org',
            ]);
        $this->project->setRepository($projectRepo);

        $edits = [
            new Edit($this->page, [
                'id' => 1,
                'timestamp' => '20160701101205',
                'minor' => '0',
                'length' => '30',
                'length_change' => '30',
                'username' => 'Mick Jagger',
                'comment' => 'Foo bar',
                'rev_sha1' => 'aaaaaa',
            ]),
            new Edit($this->page, [
                'id' => 32,
                'timestamp' => '20160801000000',
                'minor' => '1',
                'length' => '25',
                'length_change' => '-5',
                'username' => 'Mick Jagger',
                'comment' => 'Blah',
                'rev_sha1' => 'bbbbbb',
            ]),
            new Edit($this->page, [
                'id' => 40,
                'timestamp' => '20161003000000',
                'minor' => '0',
                'length' => '15',
                'length_change' => '-10',
                'username' => '192.168.0.1',
                'comment' => 'Weeee using [[WP:AWB|AWB]]',
                'rev_sha1' => 'cccccc',
            ]),
            new Edit($this->page, [
                'id' => 50,
                'timestamp' => '20161003010000',
                'minor' => '1',
                'length' => '25',
                'length_change' => '10',
                'username' => '192.168.0.2',
                'comment' => 'I undo your edit cuz it bad',
                'rev_sha1' => 'bbbbbb',
            ]),
        ];

        $prevEdits = [
            'prev' => null,
            'prevSha' => null,
            'maxAddition' => null,
            'maxDeletion' => null,
        ];

        $prop = $this->reflectionClass->getProperty('firstEdit');
        $prop->setAccessible(true);
        $prop->setValue($this->articleInfo, $edits[0]);

        $prop = $this->reflectionClass->getProperty('numRevisionsProcessed');
        $prop->setAccessible(true);
        $prop->setValue($this->articleInfo, 4);

        $prop = $this->reflectionClass->getProperty('bots');
        $prop->setAccessible(true);
        $prop->setValue($this->articleInfo, [
            'XtoolsBot' => ['count' => 1],
        ]);

        $method = $this->reflectionClass->getMethod('updateCounts');
        $method->setAccessible(true);
        $prevEdits = $method->invoke($this->articleInfo, $edits[0], $prevEdits);
        $prevEdits = $method->invoke($this->articleInfo, $edits[1], $prevEdits);
        $prevEdits = $method->invoke($this->articleInfo, $edits[2], $prevEdits);
        $method->invoke($this->articleInfo, $edits[3], $prevEdits);

        $method = $this->reflectionClass->getMethod('doPostPrecessing');
        $method->setAccessible(true);
        $method->invoke($this->articleInfo);

        return $edits;
    }

    /**
     * Textshare stats from WhoColor API.
     */
    public function testTextshares(): void
    {
        /** @var ArticleInfoRepository $articleInfoRepo */
        $articleInfoRepo = $this->getMock(ArticleInfoRepository::class);
        $articleInfoRepo->expects($this->once())
            ->method('getTextshares')
            ->willReturn([
                'revisions' => [[
                    '123' => [
                        'tokens' => [
                            [
                                'editor' => '1',
                                'str' => 'Foo',
                            ], [
                                'editor' => '0|192.168.0.1',
                                'str' => 'Bar',
                            ], [
                                'editor' => '0|192.168.0.1',
                                'str' => 'Baz',
                            ], [
                                'editor' => '2',
                                'str' => 'Foo bar',
                            ],
                        ],
                    ],
                ]],
            ]);
        $articleInfoRepo->expects($this->once())
            ->method('getUsernamesFromIds')
            ->willReturn([
                ['user_id' => 1, 'user_name' => 'Mick Jagger'],
                ['user_id' => 2, 'user_name' => 'Mr. Rogers'],
            ]);
        $this->articleInfo->setRepository($articleInfoRepo);

        static::assertEquals(
            [
                'list' => [
                    'Mr. Rogers' => [
                        'count' => 7,
                        'percentage' => 43.8,
                    ],
                    '192.168.0.1' => [
                        'count' => 6,
                        'percentage' => 37.5,
                    ],
                ],
                'totalAuthors' => 3,
                'totalCount' => 16,
                'others' => [
                    'count' => 3,
                    'percentage' => 18.7,
                    'numEditors' => 1,
                ],
            ],
            $this->articleInfo->getTextshares(2)
        );
    }

    /**
     * Test prose stats parser.
     */
    public function testProseStats(): void
    {
        // We'll use a live page to better test the prose stats parser.
        $client = new GuzzleHttp\Client();
        $ret = $client->request('GET', 'https://en.wikipedia.org/wiki/Hanksy?oldid=747629772')
            ->getBody()
            ->getContents();
        $pageRepo = $this->getMock(PageRepository::class);
        $pageRepo->expects($this->once())
            ->method('getHTMLContent')
            ->willReturn($ret);
        $this->page->setRepository($pageRepo);

        static::assertEquals([
            'characters' => 1541,
            'words' => 263,
            'references' => 13,
            'unique_references' => 12,
            'sections' => 1,
        ], $this->articleInfo->getProseStats());
    }

    /**
     * Various methods involving start/end dates.
     */
    public function testWithDates(): void
    {
        $this->setupData();

        $prop = $this->reflectionClass->getProperty('start');
        $prop->setAccessible(true);
        $prop->setValue($this->articleInfo, strtotime('2016-06-30'));

        $prop = $this->reflectionClass->getProperty('end');
        $prop->setAccessible(true);
        $prop->setValue($this->articleInfo, strtotime('2016-10-14'));

        static::assertTrue($this->articleInfo->hasDateRange());
        static::assertEquals('2016-06-30', $this->articleInfo->getStartDate());
        static::assertEquals('2016-10-14', $this->articleInfo->getEndDate());
        static::assertEquals([
            'start' => '2016-06-30',
            'end' => '2016-10-14',
        ], $this->articleInfo->getDateParams());

        // Uses length of last edit because there is a date range.
        static::assertEquals(25, $this->articleInfo->getLength());
    }

    /**
     * Transclusion counts.
     */
    public function testTransclusionData(): void
    {
        $articleInfoRepo = $this->getMock(ArticleInfoRepository::class);
        $articleInfoRepo->expects($this->once())
            ->method('getTransclusionData')
            ->willReturn([
                'categories' => 3,
                'templates' => 5,
                'files' => 2,
            ]);
        $this->articleInfo->setRepository($articleInfoRepo);

        static::assertEquals(3, $this->articleInfo->getNumCategories());
        static::assertEquals(5, $this->articleInfo->getNumTemplates());
        static::assertEquals(2, $this->articleInfo->getNumFiles());
    }
}
