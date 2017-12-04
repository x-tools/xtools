<?php
/**
 * This file contains only the ArticleInfoTest class.
 */

namespace Tests\Xtools;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Xtools\ArticleInfo;
use Xtools\ArticleInfoRepository;
use Xtools\Edit;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\Page;
use Xtools\PageRepository;
use DateTime;
use Doctrine\DBAL\Driver\PDOStatement;

/**
 * Tests for ArticleInfo.
 */
class ArticleInfoTest extends WebTestCase
{
    /** @var Container The Symfony container. */
    protected $container;

    /** @var ArticleInfo The article info instance. */
    protected $articleInfo;

    /** @var Page The page instance. */
    protected $page;

    /** @var Project The project instance. */
    protected $project;

    /**
     * Set up shared mocks and class instances.
     */
    public function setUp()
    {
        $client = static::createClient();
        $this->container = $client->getContainer();
        $this->project = new Project('TestProject');
        $this->page = new Page($this->project, 'Test page');
        $this->articleInfo = new ArticleInfo($this->page, $this->container);

        // Don't care that private methods "shouldn't" be tested...
        // In ArticleInfo they are all super testworthy and otherwise fragile.
        $this->reflectionClass = new \ReflectionClass($this->articleInfo);
    }

    /**
     * Number of revisions
     */
    public function testNumRevisions()
    {
        $pageRepo = $this->getMock(PageRepository::class);
        $pageRepo->expects($this->once())
            ->method('getNumRevisions')
            ->willReturn(10);
        $this->page->setRepository($pageRepo);
        $this->assertEquals(10, $this->articleInfo->getNumRevisions());
        // Should be cached (will error out if repo's getNumRevisions is called again).
        $this->assertEquals(10, $this->articleInfo->getNumRevisions());
    }

    /**
     * Number of revisions processed, based on app.max_page_revisions
     * @dataProvider revisionsProcessedProvider
     */
    public function testRevisionsProcessed($numRevisions, $assertion)
    {
        $pageRepo = $this->getMock(PageRepository::class);
        $pageRepo->method('getNumRevisions')->willReturn($numRevisions);
        $this->page->setRepository($pageRepo);
        $this->assertEquals(
            $this->articleInfo->getNumRevisionsProcessed(),
            $assertion
        );
    }

    /**
     * Data for self::testRevisionsProcessed().
     * @return int[]
     */
    public function revisionsProcessedProvider()
    {
        return [
            [1000000, 50000],
            [10, 10],
        ];
    }

    /**
     * Whether there are too many revisions to process.
     */
    public function testTooManyRevisions()
    {
        $pageRepo = $this->getMock(PageRepository::class);
        $pageRepo->expects($this->once())
            ->method('getNumRevisions')
            ->willReturn(1000000);
        $this->page->setRepository($pageRepo);
        $this->assertTrue($this->articleInfo->tooManyRevisions());
    }

    /**
     * Getting the number of edits made to the page by current or former bots.
     */
    public function testBotRevisionCount()
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

        $this->assertEquals(
            15,
            $this->articleInfo->getBotRevisionCount($bots)
        );
    }

    public function testLinksAndRedirects()
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
        $this->assertEquals(5, $this->articleInfo->linksExtCount());
        $this->assertEquals(3, $this->articleInfo->linksOutCount());
        $this->assertEquals(10, $this->articleInfo->linksInCount());
        $this->assertEquals(0, $this->articleInfo->redirectsCount());
    }

    /**
     * Test some of the more important getters.
     */
    public function testGetters()
    {
        $edits = $this->setupData();

        $this->assertEquals(3, $this->articleInfo->getNumEditors());
        $this->assertEquals(2, $this->articleInfo->getAnonCount());
        $this->assertEquals(50, $this->articleInfo->anonPercentage());
        $this->assertEquals(2, $this->articleInfo->getMinorCount());
        $this->assertEquals(50, $this->articleInfo->minorPercentage());
        $this->assertEquals(1, $this->articleInfo->getBotRevisionCount());
        $this->assertEquals(93, $this->articleInfo->getTotalDays());
        $this->assertEquals(23, (int) $this->articleInfo->averageDaysPerEdit());
        $this->assertEquals(0, (int) $this->articleInfo->editsPerDay());
        $this->assertEquals(1.3, $this->articleInfo->editsPerMonth());
        $this->assertEquals(4, $this->articleInfo->editsPerYear());
        $this->assertEquals(1.3, $this->articleInfo->editsPerEditor());
        $this->assertEquals(1, $this->articleInfo->getAutomatedCount());
        $this->assertEquals(1, $this->articleInfo->getRevertCount());

        $this->assertEquals(100, $this->articleInfo->topTenPercentage());
        $this->assertEquals(4, $this->articleInfo->getTopTenCount());

        $this->assertEquals(
            $edits[3]->getId(),
            $this->articleInfo->getLastEdit()->getId()
        );

        $this->assertEquals(1, $this->articleInfo->getMaxAddition()->getId());
        $this->assertEquals(32, $this->articleInfo->getMaxDeletion()->getId());

        $this->assertEquals(
            ['Mick Jagger', '192.168.0.1', '192.168.0.2'],
            array_keys($this->articleInfo->getEditors())
        );
        $this->assertEquals(
            [
                'label' =>'Mick Jagger',
                'value' => 2,
                'percentage' => 50,
            ],
            $this->articleInfo->topTenEditorsByEdits()[0]
        );
        $this->assertEquals(
            [
                'label' =>'Mick Jagger',
                'value' => 30,
                'percentage' => 100,
            ],
            $this->articleInfo->topTenEditorsByAdded()[0]
        );

        // Top 10 counts should not include bots.
        $this->assertFalse(
            array_search(
                'XtoolsBot',
                array_column($this->articleInfo->topTenEditorsByEdits(), 'label')
            )
        );
        $this->assertFalse(
            array_search(
                'XtoolsBot',
                array_column($this->articleInfo->topTenEditorsByAdded(), 'label')
            )
        );

        $this->assertEquals(2, $this->articleInfo->getMaxEditsPerMonth());

        $this->assertContains(
            'Undo',
            array_keys($this->articleInfo->getTools())
        );
    }

    /**
     * Test that the data for each individual month and year is correct.
     */
    public function testMonthYearCounts()
    {
        $edits = $this->setupData();

        $yearMonthCounts = $this->articleInfo->getYearMonthCounts();

        $this->assertEquals([2016], array_keys($yearMonthCounts));
        $this->assertArraySubset([
            'all' => 4,
            'minor' => 2,
            'anon' => 2,
            'automated' => 1,
            'size' => 25,
        ], $yearMonthCounts[2016]);

        $this->assertEquals(
            ['07', '08', '09', '10', '11', '12'],
            array_keys($yearMonthCounts[2016]['months'])
        );

        // Just test a few, not every month.
        $this->assertArraySubset([
            'all' => 1,
            'minor' => 0,
            'anon' => 0,
            'automated' => 0,
        ], $yearMonthCounts[2016]['months']['07']);
        $this->assertArraySubset([
            'all' => 2,
            'minor' => 1,
            'anon' => 2,
            'automated' => 1,
        ], $yearMonthCounts[2016]['months']['10']);
    }


    /**
     * Test data around log events.
     */
    public function testLogEvents()
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
        $this->assertEquals([
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
    private function setupData()
    {
        // ArticleInfo::udpateToolCounts relies on there being entries in
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
            ]),
            new Edit($this->page, [
                'id' => 32,
                'timestamp' => '20160801000000',
                'minor' => '1',
                'length' => '25',
                'length_change' => '-5',
                'username' => 'Mick Jagger',
                'comment' => 'Blah',
            ]),
            new Edit($this->page, [
                'id' => 40,
                'timestamp' => '20161003000000',
                'minor' => '0',
                'length' => '15',
                'length_change' => '-10',
                'username' => '192.168.0.1',
                'comment' => 'Weeee',
            ]),
            new Edit($this->page, [
                'id' => 50,
                'timestamp' => '20161003010000',
                'minor' => '1',
                'length' => '25',
                'length_change' => '10',
                'username' => '192.168.0.2',
                'comment' => 'Undid revision 40 by [[Special:Contributions/192.168.0.1|192.168.0.1]]',
            ]),
            new Edit($this->page, [
                'id' => 60,
                'timestamp' => '20161005010000',
                'minor' => '1',
                'length' => '30',
                'length_change' => '35',
                'username' => 'XtoolsBot',
                'comment' => 'This is a bot edit',
            ]),
        ];

        $prevEdits = [
            'prev' => null,
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
        $prevEdits = $method->invoke($this->articleInfo, $edits[3], $prevEdits);

        $method = $this->reflectionClass->getMethod('setTopTenCounts');
        $method->setAccessible(true);
        $method->invoke($this->articleInfo);

        return $edits;
    }
}
