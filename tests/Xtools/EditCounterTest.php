<?php
/**
 * This file contains only the EditCounterTest class.
 */

namespace Tests\Xtools;

use Xtools\EditCounter;
use Xtools\EditCounterRepository;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;
use DateTime;

/**
 * Tests for the EditCounter.
 */
class EditCounterTest extends \PHPUnit_Framework_TestCase
{
    /** @var Project The project instance. */
    protected $project;

    /** @var ProjectRepository The project repository instance. */
    protected $projectRepo;

    /** @var EditCounter The edit counter instance. */
    protected $editCounter;

    /** @var EditCounterRepository The edit counter repository instance. */
    protected $editCounterRepo;

    /** @var User The user instance. */
    protected $user;

    /**
     * Set up shared mocks and class instances.
     */
    public function setUp()
    {
        $this->editCounterRepo = $this->getMock(EditCounterRepository::class);
        $this->projectRepo = $this->getMock(ProjectRepository::class);
        $this->project = new Project('TestProject');
        $this->project->setRepository($this->projectRepo);
        $this->user = new User('Testuser');
        $this->editCounter = new EditCounter($this->project, $this->user);
        $this->editCounter->setRepository($this->editCounterRepo);
    }

    /**
     * Get counts of revisions: deleted, not-deleted, total, and edit summary usage.
     */
    public function testLiveAndDeletedEdits()
    {
        $this->editCounterRepo->expects($this->once())
            ->method('getPairData')
            ->willReturn([
                'deleted' => 10,
                'live' => 100,
                'with_comments' => 75,
            ]);

        $this->assertEquals(100, $this->editCounter->countLiveRevisions());
        $this->assertEquals(10, $this->editCounter->countDeletedRevisions());
        $this->assertEquals(110, $this->editCounter->countAllRevisions());
        $this->assertEquals(100, $this->editCounter->countLast5000());
        $this->assertEquals(75, $this->editCounter->countRevisionsWithComments());
        $this->assertEquals(25, $this->editCounter->countRevisionsWithoutComments());
    }

    /**
     * A first and last date, and number of days between.
     */
    public function testDates()
    {
        $this->editCounterRepo->expects($this->once())->method('getPairData')->willReturn([
                'first' => '20170510100000',
                'last' => '20170515150000',
            ]);
        $this->assertEquals(
            new \DateTime('2017-05-10 10:00'),
            $this->editCounter->datetimeFirstRevision()
        );
        $this->assertEquals(
            new \DateTime('2017-05-15 15:00'),
            $this->editCounter->datetimeLastRevision()
        );
        $this->assertEquals(5, $this->editCounter->getDays());
    }

    /**
     * Only one edit means the dates will be the same.
     */
    public function testDatesWithOneRevision()
    {
        $this->editCounterRepo->expects($this->once())
            ->method('getPairData')
            ->willReturn([
                'first' => '20170510110000',
                'last' => '20170510110000',
            ]);
        $this->assertEquals(
            new \DateTime('2017-05-10 11:00'),
            $this->editCounter->datetimeFirstRevision()
        );
        $this->assertEquals(
            new \DateTime('2017-05-10 11:00'),
            $this->editCounter->datetimeLastRevision()
        );
        $this->assertEquals(1, $this->editCounter->getDays());
    }

    /**
     * Test that page counts are reported correctly.
     */
    public function testPageCounts()
    {
        $this->editCounterRepo->expects($this->once())
            ->method('getPairData')
            ->willReturn([
                'edited-live' => '3',
                'edited-deleted' => '1',
                'created-live' => '6',
                'created-deleted' => '2',
            ]);

        $this->assertEquals(3, $this->editCounter->countLivePagesEdited());
        $this->assertEquals(1, $this->editCounter->countDeletedPagesEdited());
        $this->assertEquals(4, $this->editCounter->countAllPagesEdited());

        $this->assertEquals(6, $this->editCounter->countCreatedPagesLive());
        $this->assertEquals(2, $this->editCounter->countPagesCreatedDeleted());
        $this->assertEquals(8, $this->editCounter->countPagesCreated());
    }

    /**
     * Test that namespace totals are reported correctly.
     */
    public function testNamespaceTotals()
    {
        $namespaceTotals = [
            // Namespace IDs => Edit counts
            '1' => '3',
            '2' => '6',
            '3' => '9',
            '4' => '12',
        ];
        $this->editCounterRepo->expects($this->once())
            ->method('getNamespaceTotals')
            ->willReturn($namespaceTotals);

        $this->assertEquals($namespaceTotals, $this->editCounter->namespaceTotals());
    }

    /**
     * Test that month counts are properly put together.
     */
    public function testMonthCounts()
    {
        $this->editCounterRepo->expects($this->once())
            ->method('getMonthCounts')
            ->willReturn([
                [
                    'year' => '2016',
                    'month' => '12',
                    'page_namespace' => '0',
                    'count' => '10',
                ],
                [
                    'year' => '2017',
                    'month' => '3',
                    'page_namespace' => '0',
                    'count' => '20',
                ],
                [
                    'year' => '2017',
                    'month' => '2',
                    'page_namespace' => '1',
                    'count' => '50',
                ],
            ]);
        $userRepo = $this->getMock(UserRepository::class);
        $this->user->setRepository($userRepo);

        // Mock current time by passing it in (dummy parameter, so to speak).
        $monthCounts = $this->editCounter->monthCounts(new DateTime('2017-04-30 23:59:59'));

        // Make sure zeros were filled in for months with no edits,
        //   and for each namespace.
        $this->assertArraySubset(
            [
                1 => 0,
                2 => 0,
                3 => 20,
                4 => 0,
            ],
            $monthCounts['totals'][0][2017]
        );
        $this->assertArraySubset(
            [
                12 => 0,
            ],
            $monthCounts['totals'][1][2016]
        );

        // Assert only active months are reported.
        $this->assertEquals([12], array_keys($monthCounts['totals'][0][2016]));
        $this->assertEquals(['01', '02', '03', '04'], array_keys($monthCounts['totals'][0][2017]));

        // Assert that only active years are reported
        $this->assertEquals([2016, 2017], array_keys($monthCounts['totals'][0]));

        // Assert that only active namespaces are reported.
        $this->assertEquals([0, 1], array_keys($monthCounts['totals']));

        // Labels for the months
        $this->assertEquals(
            ['2016-12', '2017-01', '2017-02', '2017-03', '2017-04'],
            $monthCounts['monthLabels']
        );

        // Labels for the years
        $this->assertEquals(['2016', '2017'], $monthCounts['yearLabels']);

        $monthTotals = $this->editCounter->monthTotals(new DateTime('2017-04-30 23:59:59'));
        $this->assertEquals(
            [
                '2016-12' => 10,
                '2017-01' => 0,
                '2017-02' => 50,
                '2017-03' => 20,
                '2017-04' => 0,
            ],
            $monthTotals
        );

        $yearTotals = $this->editCounter->yearTotals(new DateTime('2017-04-30 23:59:59'));
        $this->assertEquals(['2016' => 10, '2017' => 70], $yearTotals);
    }

    /**
     * Test that year counts are properly put together.
     */
    public function testYearCounts()
    {
        $this->editCounterRepo->expects($this->once())
            ->method('getMonthCounts')
            ->willReturn([
                [
                    'year' => '2015',
                    'month' => '6',
                    'page_namespace' => '1',
                    'count' => '5',
                ],
                [
                    'year' => '2016',
                    'month' => '12',
                    'page_namespace' => '0',
                    'count' => '10',
                ],
                [
                    'year' => '2017',
                    'month' => '3',
                    'page_namespace' => '0',
                    'count' => '20',
                ],
                [
                    'year' => '2017',
                    'month' => '2',
                    'page_namespace' => '1',
                    'count' => '50',
                ],
            ]);
        $userRepo = $this->getMock(UserRepository::class);
        $this->user->setRepository($userRepo);

        // Mock current time by passing it in (dummy parameter, so to speak).
        $yearCounts = $this->editCounter->yearCounts(new DateTime('2017-04-30 23:59:59'));

        // Make sure zeros were filled in for months with no edits,
        //   and for each namespace.
        $this->assertArraySubset(
            [
                2015 => 0,
                2016 => 10,
                2017 => 20,
            ],
            $yearCounts['totals'][0]
        );
        $this->assertArraySubset(
            [
                2015 => 5,
                2016 => 0,
                2017 => 50,
            ],
            $yearCounts['totals'][1]
        );

        // Assert that only active years are reported
        $this->assertEquals([2015, 2016, 2017], array_keys($yearCounts['totals'][0]));

        // Assert that only active namespaces are reported.
        $this->assertEquals([0, 1], array_keys($yearCounts['totals']));

        // Labels for the years
        $this->assertEquals(['2015', '2016', '2017'], $yearCounts['yearLabels']);
    }

    /**
     * Get all global edit counts, or just the top N, or the overall grand total.
     */
    public function testGlobalEditCounts()
    {
        $wiki1 = new Project('wiki1');
        $wiki2 = new Project('wiki2');
        $editCounts = [
            ['project' => new Project('wiki0'), 'total' => 30],
            ['project' => $wiki1, 'total' => 50],
            ['project' => $wiki2, 'total' => 40],
            ['project' => new Project('wiki3'), 'total' => 20],
            ['project' => new Project('wiki4'), 'total' => 10],
            ['project' => new Project('wiki5'), 'total' => 35],
        ];
        $this->editCounterRepo->expects($this->once())
            ->method('globalEditCounts')
            ->willReturn($editCounts);

        // Get the top 2.
        $this->assertEquals(
            [
                ['project' => $wiki1, 'total' => 50],
                ['project' => $wiki2, 'total' => 40],
            ],
            $this->editCounter->globalEditCountsTopN(2)
        );

        // And the bottom 4.
        $this->assertEquals(95, $this->editCounter->globalEditCountWithoutTopN(2));

        // Grand total.
        $this->assertEquals(185, $this->editCounter->globalEditCount());
    }

    /**
     * Ensure parsing of log_params properly works, based on known formats
     * @dataProvider longestBlockProvider
     */
    public function testLongestBlockSeconds($blockLog, $longestDuration)
    {
        $currentTime = time();
        $this->editCounterRepo->expects($this->once())
            ->method('getBlocksReceived')
            ->with($this->project, $this->user)
            ->willReturn($blockLog);
        $this->assertEquals($this->editCounter->getLongestBlockSeconds(), $longestDuration);
    }

    /**
     * Data for self::testLongestBlockSeconds().
     * @return string[]
     */
    public function longestBlockProvider()
    {
        return [
            // // Blocks that don't overlap, longest was 31 days.
            [
                [[
                    'log_timestamp' => '20170101000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:8:"72 hours"' .
                        ';s:8:"6::flags";s:8:"nocreate";}',
                    'log_action' => 'block',
                ],
                [
                    'log_timestamp' => '20170301000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:7:"1 month"' .
                        ';s:8:"6::flags";s:11:"noautoblock";}',
                    'log_action' => 'block',
                ]],
                2678400 // 31 days in seconds.
            ],
            // Blocks that do overlap, without any unblocks. Combined 10 days.
            [
                [[
                    'log_timestamp' => '20170101000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:7:"1 month"' .
                        ';s:8:"6::flags";s:8:"nocreate";}',
                    'log_action' => 'block',
                ],
                [
                    'log_timestamp' => '20170110000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:8:"24 hours"' .
                        ';s:8:"6::flags";s:11:"noautoblock";}',
                    'log_action' => 'reblock',
                ]],
                864000 // 10 days in seconds.
            ],
            // 30 day block that was later unblocked at only 10 days, followed by a shorter block.
            [
                [[
                    'log_timestamp' => '20170101000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:7:"1 month"' .
                        ';s:8:"6::flags";s:8:"nocreate";}',
                    'log_action' => 'block',
                ],
                [
                    'log_timestamp' => '20170111000000',
                    'log_params' => 'a:0:{}',
                    'log_action' => 'unblock',
                ],
                [
                    'log_timestamp' => '20170201000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:8:"24 hours"' .
                        ';s:8:"6::flags";s:11:"noautoblock";}',
                    'log_action' => 'block',
                ]],
                864000 // 10 days in seconds.
            ],
            // Blocks ending with a still active indefinite block. Older block uses legacy format.
            [
                [[
                    'log_timestamp' => '20170101000000',
                    'log_params' => "1 month\nnoautoblock",
                    'log_action' => 'block',
                ],
                [
                    'log_timestamp' => '20170301000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:10:"indefinite"' .
                        ';s:8:"6::flags";s:11:"noautoblock";}',
                    'log_action' => 'block',
                ]],
                -1 // Indefinite
            ],
            // Block that's active, with an explicit expiry set.
            [
                [[
                    'log_timestamp' => '20170927203624',
                    'log_params' => 'a:2:{s:11:"5::duration";s:29:"Sat, 06 Oct 2026 12:36:00 GMT"' .
                        ';s:8:"6::flags";s:11:"noautoblock";}',
                    'log_action' => 'block',
                ]],
                285091176
            ],
            // Two indefinite blocks.
            [
                [[
                    'log_timestamp' => '20160513200200',
                    'log_params' => 'a:2:{s:11:"5::duration";s:10:"indefinite"' .
                        ';s:8:"6::flags";s:19:"nocreate,nousertalk";}',
                    'log_action' => 'block',
                ],
                [
                    'log_timestamp' => '20160717021328',
                    'log_params' => 'a:2:{s:11:"5::duration";s:8:"infinite"' .
                        ';s:8:"6::flags";s:31:"nocreate,noautoblock,nousertalk";}',
                    'log_action' => 'reblock',
                ]],
                -1
            ],
        ];
    }

    /**
     * Parsing block log entries.
     * @dataProvider blockLogProvider
     */
    public function testParseBlockLogEntry($logEntry, $assertion)
    {
        $editCounter = new EditCounter($this->project, $this->user);
        $this->assertEquals(
            $editCounter->parseBlockLogEntry($logEntry),
            $assertion
        );
    }

    /**
     * Data for self::testParseBlockLogEntry().
     * @return string[]
     */
    public function blockLogProvider()
    {
        return [
            [
                [
                    'log_timestamp' => '20170701000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:7:"60 days";' .
                        's:8:"6::flags";s:8:"nocreate";}',
                ],
                [1498867200, 5184000]
            ],
            [
                [
                    'log_timestamp' => '20170101000000',
                    'log_params' => "9 weeks\nnoautoblock",
                ],
                [1483228800, 5443200]
            ],
            [
                [
                    'log_timestamp' => '20170101000000',
                    'log_params' => "invalid format",
                ],
                [1483228800, null]
            ],
            [
                [
                    'log_timestamp' => '20170101000000',
                    'log_params' => "infinity\nnocreate",
                ],
                [1483228800, -1]
            ],
            [
                [
                    'log_timestamp' => '20170927203205',
                    'log_params' => 'a:2:{s:11:"5::duration";s:19:"2017-09-30 12:36 PM";' .
                        's:8:"6::flags";s:11:"noautoblock";}'
                ],
                [1506544325, 230635]
            ]
        ];
    }

    /**
     * User rights changes.
     */
    public function testUserRightsChanges()
    {
        $this->editCounterRepo->expects($this->once())
            ->method('getRightsChanges')
            ->willReturn([[
                    'log_id' => '210221',
                    'log_timestamp' => '20180108132810',
                    'log_comment' => '',
                    'log_params' => 'a:4:{s:12:"4::oldgroups";a:6:{i:0;s:10:"bureaucrat";i:1;s:9:' .
                        '"filemover";i:2;s:6:"import";i:3;s:14:"ipblock-exempt";i:4;s:5:"sysop";i:5;' .
                        's:14:"templateeditor";}s:12:"5::newgroups";a:5:{i:0;s:10:"bureaucrat";i:1;s:9:' .
                        '"filemover";i:2;s:6:"import";i:3;s:14:"ipblock-exempt";i:4;s:5:"sysop";}s:11:' .
                        '"oldmetadata";a:6:{i:0;a:1:{s:6:"expiry";N;}i:1;a:1:{s:6:"expiry";s:14:"' .
                        '20180108132858";}i:2;a:1:{s:6:"expiry";N;}i:3;a:1:{s:6:"expiry";s:14:"20180108132858"' .
                        ';}i:4;a:1:{s:6:"expiry";N;}i:5;a:1:{s:6:"expiry";N;}}s:11:"newmetadata";a:5:{i:0;' .
                        'a:1:{s:6:"expiry";N;}i:1;a:1:{s:6:"expiry";s:14:"20180108132858";}i:2;a:1:{s:6:' .
                        '"expiry";N;}i:3;a:1:{s:6:"expiry";s:14:"20180108132858";}i:4;a:1:{s:6:"expiry";N;}}}',
                    'log_action' => 'rights',
                    'log_user_text' => 'MusikAnimal',
                ], [
                    'log_id' => '210220',
                    'log_timestamp' => '20180108132758',
                    'log_comment' => '',
                    'log_params' => 'a:4:{s:12:"4::oldgroups";a:3:{i:0;s:10:"bureaucrat";i:1;s:6:"import";' .
                        'i:2;s:5:"sysop";}s:12:"5::newgroups";a:6:{i:0;s:10:"bureaucrat";i:1;s:6:"import";' .
                        'i:2;s:5:"sysop";i:3;s:14:"ipblock-exempt";i:4;s:9:"filemover";i:5;s:14:"templateeditor";}' .
                        's:11:"oldmetadata";a:3:{i:0;a:1:{s:6:"expiry";N;}i:1;a:1:{s:6:"expiry";N;}i:2;a:1:' .
                        '{s:6:"expiry";N;}}s:11:"newmetadata";a:6:{i:0;a:1:{s:6:"expiry";N;}i:1;a:1:{s:6:' .
                        '"expiry";N;}i:2;a:1:{s:6:"expiry";N;}i:3;a:1:{s:6:"expiry";s:14:"20180108132858";}' .
                        'i:4;a:1:{s:6:"expiry";s:14:"20180108132858";}i:5;a:1:{s:6:"expiry";N;}}}',
                    'log_action' => 'rights',
                    'log_user_text' => 'MusikAnimal',
                ], [
                    'log_id' => '155321',
                    'log_timestamp' => '20150716002614',
                    'log_comment' => 'Per user request.',
                    'log_params' => 'a:2:{s:12:"4::oldgroups";a:3:{i:0;s:8:"reviewer";i:1;s:10:"rollbacker"' .
                        ';i:2;s:5:"sysop";}s:12:"5::newgroups";a:3:{i:0;s:8:"reviewer";i:1;s:5:"sysop";i:2;' .
                        's:10:"bureaucrat";}}',
                    'log_action' => 'rights',
                    'log_user_text' => 'Cyberpower678',
                ], [
                    'log_id' => '140643',
                    'log_timestamp' => '20141222034127',
                    'log_comment' => 'per request',
                    'log_params' => "\nsysop",
                    'log_action' => 'rights',
                    'log_user_text' => 'Snowolf',
                ]
            ]);

        $this->assertEquals([
            20180108132858 => [
                'logId' => '210220',
                'admin' => 'MusikAnimal',
                'comment' => null,
                'added' => [],
                'removed' => ['ipblock-exempt', 'filemover'],
                'automatic' => true,
            ],
            20180108132810 => [
                'logId' => '210221',
                'admin' => 'MusikAnimal',
                'comment' => '',
                'added' => [],
                'removed' => ['templateeditor'],
                'automatic' => false,
            ],
            20180108132758 => [
                'logId' => '210220',
                'admin' => 'MusikAnimal',
                'comment' => '',
                'added' => ['ipblock-exempt', 'filemover', 'templateeditor'],
                'removed' => [],
                'automatic' => false,
            ],
            20150716002614 => [
                'logId' => '155321',
                'admin' => 'Cyberpower678',
                'comment' => 'Per user request.',
                'added' => ['bureaucrat'],
                'removed' => ['rollbacker'],
                'automatic' => false,
            ],
            20141222034127 => [
                'logId' => '140643',
                'admin' => 'Snowolf',
                'comment' => 'per request',
                'added' => ['sysop'],
                'removed' => [],
                'automatic' => false,
            ],
        ], $this->editCounter->getRightsChanges());
    }
}
