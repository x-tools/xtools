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
     * Get counts of revisions: deleted, not-deleted, and total.
     */
    public function testLiveAndDeletedEdits()
    {
        $this->editCounterRepo->expects($this->once())
            ->method('getPairData')
            ->willReturn([
                'deleted' => 10,
                'live' => 100,
            ]);

        $this->assertEquals(100, $this->editCounter->countLiveRevisions());
        $this->assertEquals(10, $this->editCounter->countDeletedRevisions());
        $this->assertEquals(110, $this->editCounter->countAllRevisions());
        $this->assertEquals(100, $this->editCounter->countLast5000());
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
        $userRepo->expects($this->once())
            ->method('getRegistrationDate')
            ->willReturn('20161105000000');
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
                11 => 0,
                12 => 0,
            ],
            $monthCounts['totals'][1][2016]
        );

        // Assert only active months are reported.
        $this->assertEquals([11, 12], array_keys($monthCounts['totals'][0][2016]));
        $this->assertEquals(['01', '02', '03', '04'], array_keys($monthCounts['totals'][0][2017]));

        // Assert that only active years are reported
        $this->assertEquals([2016, 2017], array_keys($monthCounts['totals'][0]));

        // Assert that only active namespaces are reported.
        $this->assertEquals([0, 1], array_keys($monthCounts['totals']));

        // Labels for the months
        $this->assertEquals(
            ['2016/11', '2016/12', '2017/01', '2017/02', '2017/03', '2017/04'],
            $monthCounts['monthLabels']
        );

        // Labels for the years
        $this->assertEquals(['2016', '2017'], $monthCounts['yearLabels']);
    }

    /**
     * Month counts when user registration date is unknown
     */
    public function testMonthCountsUknownRegDate()
    {
        $this->editCounterRepo->expects($this->once())
            ->method('getMonthCounts')
            ->willReturn([
                [
                    'year' => '2017',
                    'month' => '3',
                    'page_namespace' => '0',
                    'count' => '20',
                ],
            ]);
        $userRepo = $this->getMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('getRegistrationDate')
            ->willReturn(null);
        $this->user->setRepository($userRepo);
        $monthCounts = $this->editCounter->monthCounts(new DateTime('2017-05-30 23:59:59'));

        // All months of the year of the first edit should be reported
        $this->assertEquals(
            ['2017/01', '2017/02', '2017/03', '2017/04', '2017/05'],
            $monthCounts['monthLabels']
        );
        $this->assertEquals([2017], array_keys($monthCounts['totals'][0]));
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
        $userRepo->expects($this->once())
            ->method('getRegistrationDate')
            ->willReturn('20140505000000');
        $this->user->setRepository($userRepo);

        // Mock current time by passing it in (dummy parameter, so to speak).
        $yearCounts = $this->editCounter->yearCounts(new DateTime('2017-04-30 23:59:59'));

        // Make sure zeros were filled in for months with no edits,
        //   and for each namespace.
        $this->assertArraySubset(
            [
                2014 => 0,
                2015 => 0,
                2016 => 10,
                2017 => 20,
            ],
            $yearCounts['totals'][0]
        );
        $this->assertArraySubset(
            [
                2014 => 0,
                2015 => 5,
                2016 => 0,
                2017 => 50,
            ],
            $yearCounts['totals'][1]
        );

        // Assert that only active years are reported
        $this->assertEquals([2014, 2015, 2016, 2017], array_keys($yearCounts['totals'][0]));

        // Assert that only active namespaces are reported.
        $this->assertEquals([0, 1], array_keys($yearCounts['totals']));

        // Labels for the years
        $this->assertEquals(['2014', '2015', '2016', '2017'], $yearCounts['yearLabels']);
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
     */
    public function testLongestBlockDays()
    {
        // Scenario 1
        $this->editCounterRepo->expects($this->once())
            ->method('getBlocksReceived')
            ->with($this->project, $this->user)
            ->willReturn([
                [
                    'log_timestamp' => '20170101000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:8:"72 hours";s:8:"6::flags";s:8:"nocreate";}',
                ],
                [
                    'log_timestamp' => '20170301000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:7:"1 month";s:8:"6::flags";s:11:"noautoblock";}',
                ],
            ]);
        $this->assertEquals(31, $this->editCounter->getLongestBlockDays());

        // Scenario 2
        $editCounter2 = new EditCounter($this->project, $this->user);
        $editCounterRepo2 = $this->getMock(EditCounterRepository::class);
        $editCounter2->setRepository($editCounterRepo2);
        $editCounterRepo2->expects($this->once())
            ->method('getBlocksReceived')
            ->with($this->project, $this->user)
            ->willReturn([
                [
                    'log_timestamp' => '20170201000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:8:"infinite";s:8:"6::flags";s:8:"nocreate";}',
                ],
                [
                    'log_timestamp' => '20170701000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:7:"60 days";s:8:"6::flags";s:8:"nocreate";}',
                ],
            ]);
        $this->assertEquals(-1, $editCounter2->getLongestBlockDays());

        // Scenario 3
        $editCounter3 = new EditCounter($this->project, $this->user);
        $editCounterRepo3 = $this->getMock(EditCounterRepository::class);
        $editCounter3->setRepository($editCounterRepo3);
        $editCounterRepo3->expects($this->once())
            ->method('getBlocksReceived')
            ->with($this->project, $this->user)
            ->willReturn([
                [
                    'log_timestamp' => '20170701000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:7:"60 days";s:8:"6::flags";s:8:"nocreate";}',
                ],
                [
                    'log_timestamp' => '20170101000000',
                    'log_params' => "9 weeks\nnoautoblock",
                ],
            ]);
        $this->assertEquals(63, $editCounter3->getLongestBlockDays());
    }
}
