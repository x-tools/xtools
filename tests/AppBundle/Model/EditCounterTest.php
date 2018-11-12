<?php
/**
 * This file contains only the EditCounterTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Model;

use AppBundle\Helper\I18nHelper;
use AppBundle\Model\EditCounter;
use AppBundle\Model\Project;
use AppBundle\Model\User;
use AppBundle\Repository\EditCounterRepository;
use AppBundle\Repository\ProjectRepository;
use AppBundle\Repository\UserRepository;
use DateTime;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\AppBundle\TestAdapter;

/**
 * Tests for the EditCounter.
 */
class EditCounterTest extends TestAdapter
{
    /** @var Project The project instance. */
    protected $project;

    /** @var ProjectRepository The project repository instance. */
    protected $projectRepo;

    /** @var EditCounter The edit counter instance. */
    protected $editCounter;

    /** @var \PHPUnit_Framework_MockObject_MockObject|EditCounterRepository The edit counter repository instance. */
    protected $editCounterRepo;

    /** @var User The user instance. */
    protected $user;

    /** @var I18nHelper For i18n and l10n. */
    protected $i18n;

    /**
     * Set up shared mocks and class instances.
     */
    public function setUp(): void
    {
        $container = static::createClient()->getContainer();
        $stack = new RequestStack();
        $session = new Session();
        $this->i18n = new I18nHelper($container, $stack, $session);

        $this->editCounterRepo = $this->getMock(EditCounterRepository::class);
        $this->projectRepo = $this->getProjectRepo();
        $this->project = new Project('test.example.org');
        $this->project->setRepository($this->projectRepo);

        $this->user = new User('Testuser');
        /** @var \PHPUnit_Framework_MockObject_MockObject|UserRepository $userRepo */
        $userRepo = $this->getMock(UserRepository::class);
        $this->user->setRepository($userRepo);

        $this->editCounter = new EditCounter($this->project, $this->user, $this->i18n);
        $this->editCounter->setRepository($this->editCounterRepo);
    }

    /**
     * Get counts of revisions: deleted, not-deleted, total, and edit summary usage.
     */
    public function testLiveAndDeletedEdits(): void
    {
        $this->editCounterRepo->expects(static::once())
            ->method('getPairData')
            ->willReturn([
                'deleted' => 10,
                'live' => 100,
                'with_comments' => 75,
            ]);

        static::assertEquals(100, $this->editCounter->countLiveRevisions());
        static::assertEquals(10, $this->editCounter->countDeletedRevisions());
        static::assertEquals(110, $this->editCounter->countAllRevisions());
        static::assertEquals(100, $this->editCounter->countLast5000());
        static::assertEquals(75, $this->editCounter->countRevisionsWithComments());
        static::assertEquals(25, $this->editCounter->countRevisionsWithoutComments());
    }

    /**
     * A first and last actions, and number of days between.
     */
    public function testFirstLastActions(): void
    {
        $this->editCounterRepo->expects(static::once())->method('getFirstAndLatestActions')->willReturn([
                'rev_first' => [
                    'id' => 123,
                    'timestamp' => '20170510100000',
                    'type' => null,
                ],
                'rev_latest' => [
                    'id' => 321,
                    'timestamp' => '20170515150000',
                    'type' => null,
                ],
                'log_latest' => [
                    'id' => 456,
                    'timestamp' => '20170510150000',
                    'type' => 'thanks',
                ],
            ]);
        static::assertEquals(
            [
                'id' => 123,
                'timestamp' => '20170510100000',
                'type' => null,
            ],
            $this->editCounter->getFirstAndLatestActions()['rev_first']
        );
        static::assertEquals(
            [
                'id' => 321,
                'timestamp' => '20170515150000',
                'type' => null,
            ],
            $this->editCounter->getFirstAndLatestActions()['rev_latest']
        );
        static::assertEquals(5, $this->editCounter->getDays());
    }

    /**
     * Test that page counts are reported correctly.
     */
    public function testPageCounts(): void
    {
        $this->editCounterRepo->expects(static::once())
            ->method('getPairData')
            ->willReturn([
                'edited-live' => '3',
                'edited-deleted' => '1',
                'created-live' => '6',
                'created-deleted' => '2',
            ]);

        static::assertEquals(3, $this->editCounter->countLivePagesEdited());
        static::assertEquals(1, $this->editCounter->countDeletedPagesEdited());
        static::assertEquals(4, $this->editCounter->countAllPagesEdited());

        static::assertEquals(6, $this->editCounter->countCreatedPagesLive());
        static::assertEquals(2, $this->editCounter->countPagesCreatedDeleted());
        static::assertEquals(8, $this->editCounter->countPagesCreated());
    }

    /**
     * Test that namespace totals are reported correctly.
     */
    public function testNamespaceTotals(): void
    {
        $namespaceTotals = [
            // Namespace IDs => Edit counts
            '1' => '3',
            '2' => '6',
            '3' => '9',
            '4' => '12',
        ];
        $this->editCounterRepo->expects(static::once())
            ->method('getNamespaceTotals')
            ->willReturn($namespaceTotals);

        static::assertEquals($namespaceTotals, $this->editCounter->namespaceTotals());
    }

    /**
     * Test that month counts are properly put together.
     */
    public function testMonthCounts(): void
    {
        $mockTime = new DateTime('2017-04-30 23:59:59');

        $this->editCounterRepo->expects(static::once())
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

        // Mock current time by passing it in (dummy parameter, so to speak).
        $monthCounts = $this->editCounter->monthCounts($mockTime);

        // Make sure zeros were filled in for months with no edits,
        //   and for each namespace.
        static::assertArraySubset(
            [
                1 => 0,
                2 => 0,
                3 => 20,
                4 => 0,
            ],
            $monthCounts['totals'][0][2017]
        );
        static::assertArraySubset(
            [
                12 => 0,
            ],
            $monthCounts['totals'][1][2016]
        );

        // Assert only active months are reported.
        static::assertEquals([12], array_keys($monthCounts['totals'][0][2016]));
        static::assertEquals(['01', '02', '03', '04'], array_keys($monthCounts['totals'][0][2017]));

        // Assert that only active years are reported
        static::assertEquals([2016, 2017], array_keys($monthCounts['totals'][0]));

        // Assert that only active namespaces are reported.
        static::assertEquals([0, 1], array_keys($monthCounts['totals']));

        // Labels for the months
        static::assertEquals(
            ['2016-12', '2017-01', '2017-02', '2017-03', '2017-04'],
            $monthCounts['monthLabels']
        );

        // Labels for the years
        static::assertEquals(['2016', '2017'], $monthCounts['yearLabels']);

        // Month counts by namespace.
        $monthsWithNamespaces = $this->editCounter->monthCountsWithNamespaces($mockTime);
        static::assertEquals(
            $monthCounts['monthLabels'],
            array_keys($monthsWithNamespaces)
        );
        static::assertEquals([0, 1], array_keys($monthsWithNamespaces['2017-03']));

        // Month totals.
        $monthTotals = $this->editCounter->monthTotals($mockTime);
        static::assertEquals(
            [
                '2016-12' => 10,
                '2017-01' => 0,
                '2017-02' => 50,
                '2017-03' => 20,
                '2017-04' => 0,
            ],
            $monthTotals
        );

        $yearTotals = $this->editCounter->yearTotals($mockTime);
        static::assertEquals(['2016' => 10, '2017' => 70], $yearTotals);
    }

    /**
     * Test that year counts are properly put together.
     */
    public function testYearCounts(): void
    {
        $this->editCounterRepo->expects(static::once())
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

        // Mock current time by passing it in (dummy parameter, so to speak).
        $yearCounts = $this->editCounter->yearCounts(new DateTime('2017-04-30 23:59:59'));

        // Make sure zeros were filled in for months with no edits,
        //   and for each namespace.
        static::assertArraySubset(
            [
                2015 => 0,
                2016 => 10,
                2017 => 20,
            ],
            $yearCounts['totals'][0]
        );
        static::assertArraySubset(
            [
                2015 => 5,
                2016 => 0,
                2017 => 50,
            ],
            $yearCounts['totals'][1]
        );

        // Assert that only active years are reported
        static::assertEquals([2015, 2016, 2017], array_keys($yearCounts['totals'][0]));

        // Assert that only active namespaces are reported.
        static::assertEquals([0, 1], array_keys($yearCounts['totals']));

        // Labels for the years
        static::assertEquals(['2015', '2016', '2017'], $yearCounts['yearLabels']);
    }

    /**
     * Get all global edit counts, or just the top N, or the overall grand total.
     */
    public function testGlobalEditCounts(): void
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
        $this->editCounterRepo->expects(static::once())
            ->method('globalEditCounts')
            ->willReturn($editCounts);

        // Get the top 2.
        static::assertEquals(
            [
                ['project' => $wiki1, 'total' => 50],
                ['project' => $wiki2, 'total' => 40],
            ],
            $this->editCounter->globalEditCountsTopN(2)
        );

        // And the bottom 4.
        static::assertEquals(95, $this->editCounter->globalEditCountWithoutTopN(2));

        // Grand total.
        static::assertEquals(185, $this->editCounter->globalEditCount());
    }

    /**
     * Ensure parsing of log_params properly works, based on known formats
     * @dataProvider longestBlockProvider
     * @param $blockLog
     * @param $longestDuration
     */
    public function testLongestBlockSeconds(array $blockLog, int $longestDuration): void
    {
        $this->editCounterRepo->expects(static::once())
            ->method('getBlocksReceived')
            ->with($this->project, $this->user)
            ->willReturn($blockLog);
        static::assertEquals($this->editCounter->getLongestBlockSeconds(), $longestDuration);
    }

    /**
     * Data for self::testLongestBlockSeconds().
     * @return string[]
     */
    public function longestBlockProvider(): array
    {
        return [
            // Blocks that don't overlap, longest was 31 days.
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
                2678400, // 31 days in seconds.
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
                864000, // 10 days in seconds.
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
                864000, // 10 days in seconds.
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
                -1, // Indefinite
            ],
            // Block that's active, with an explicit expiry set.
            [
                [[
                    'log_timestamp' => '20170927203624',
                    'log_params' => 'a:2:{s:11:"5::duration";s:29:"Sat, 06 Oct 2026 12:36:00 GMT"' .
                        ';s:8:"6::flags";s:11:"noautoblock";}',
                    'log_action' => 'block',
                ]],
                285091176,
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
                -1,
            ],
        ];
    }

    /**
     * Parsing block log entries.
     * @dataProvider blockLogProvider
     * @param $logEntry
     * @param $assertion
     */
    public function testParseBlockLogEntry(array $logEntry, array $assertion): void
    {
        $editCounter = new EditCounter($this->project, $this->user, $this->i18n);
        static::assertEquals(
            $editCounter->parseBlockLogEntry($logEntry),
            $assertion
        );
    }

    /**
     * Data for self::testParseBlockLogEntry().
     * @return array
     */
    public function blockLogProvider(): array
    {
        return [
            [
                [
                    'log_timestamp' => '20170701000000',
                    'log_params' => 'a:2:{s:11:"5::duration";s:7:"60 days";' .
                        's:8:"6::flags";s:8:"nocreate";}',
                ],
                [1498867200, 5184000],
            ],
            [
                [
                    'log_timestamp' => '20170101000000',
                    'log_params' => "9 weeks\nnoautoblock",
                ],
                [1483228800, 5443200],
            ],
            [
                [
                    'log_timestamp' => '20170101000000',
                    'log_params' => "invalid format",
                ],
                [1483228800, null],
            ],
            [
                [
                    'log_timestamp' => '20170101000000',
                    'log_params' => "infinity\nnocreate",
                ],
                [1483228800, -1],
            ],
            [
                [
                    'log_timestamp' => '20170927203205',
                    'log_params' => 'a:2:{s:11:"5::duration";s:19:"2017-09-30 12:36 PM";' .
                        's:8:"6::flags";s:11:"noautoblock";}',
                ],
                [1506544325, 230635],
            ],
        ];
    }

    /**
     * User rights changes.
     */
    public function testUserRightsChanges(): void
    {
        $this->editCounterRepo->expects(static::once())
            ->method('getRightsChanges')
            ->willReturn([[
                    // Added: interface-admin, temporary.
                    'log_id' => '92769185',
                    'log_timestamp' => '20180826173045',
                    'log_params' => 'a:4:{s:12:"4::oldgroups";a:3:{i:0;s:11:"abusefilter";i:1;s:9:"checkuser";i:2;s:5:'.
                        '"sysop";}s:12:"5::newgroups";a:4:{i:0;s:11:"abusefilter";i:1;s:9:"checkuser";i:2;s:5:"sysop";'.
                        'i:3;s:15:"interface-admin";}s:11:"oldmetadata";a:3:{i:0;a:1:{s:6:"expiry";N;}i:1;a:1:{s:6:"'.
                        'expiry";N;}i:2;a:1:{s:6:"expiry";N;}}s:11:"newmetadata";a:4:{i:0;a:1:{s:6:"expiry";N;}i:1;a:1'.
                        ':{s:6:"expiry";N;}i:2;a:1:{s:6:"expiry";N;}i:3;a:1:{s:6:"expiry";s:14:"20181025000000";}}}',
                    'log_action' => 'rights',
                    'log_user_text' => 'Worm That Turned',
                    'log_comment' => 'per [[Special:Diff/856641107]]',
                    'type' => 'local',
                ], [
                    // Removed: ipblock-exempt, filemover.
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
                    'type' => 'local',
                ], [
                    // Added: ipblock-exempt, filemover, templateeditor.
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
                    'type' => 'local',
                ], [
                    // Added: bureaucrat; Removed: rollbacker.
                    'log_id' => '155321',
                    'log_timestamp' => '20150716002614',
                    'log_comment' => 'Per user request.',
                    'log_params' => 'a:2:{s:12:"4::oldgroups";a:3:{i:0;s:8:"reviewer";i:1;s:10:"rollbacker"' .
                        ';i:2;s:5:"sysop";}s:12:"5::newgroups";a:3:{i:0;s:8:"reviewer";i:1;s:5:"sysop";i:2;' .
                        's:10:"bureaucrat";}}',
                    'log_action' => 'rights',
                    'log_user_text' => 'Cyberpower678',
                    'type' => 'meta',
                ], [
                    // Old-school log entry, adds sysop.
                    'log_id' => '140643',
                    'log_timestamp' => '20141222034127',
                    'log_comment' => 'per request',
                    'log_params' => "\nsysop",
                    'log_action' => 'rights',
                    'log_user_text' => 'Snowolf',
                    'type' => 'meta',
                ],
            ]);

        /** @var \PHPUnit_Framework_MockObject_MockObject|UserRepository $userRepo */
        $userRepo = $this->getMock(UserRepository::class);
        $userRepo->method('getIdAndRegistration')
            ->willReturn([
                'userId' => 5,
                'regDate' => '20180101000000',
            ]);
        $this->user->setRepository($userRepo);

        static::assertEquals([
            20181025000000 => [
                'logId' => '92769185',
                'performer' => 'Worm That Turned',
                'comment' => null,
                'added' => [],
                'removed' => ['interface-admin'],
                'grantType' => 'automatic',
                'type' => 'local',
            ],
            20180826173045 => [
                'logId' => '92769185',
                'performer' => 'Worm That Turned',
                'comment' => 'per [[Special:Diff/856641107]]',
                'added' => ['interface-admin'],
                'removed' => [],
                'grantType' => 'manual',
                'type' => 'local',
            ],
            20180108132858 => [
                'logId' => '210220',
                'performer' => 'MusikAnimal',
                'comment' => null,
                'added' => [],
                'removed' => ['ipblock-exempt', 'filemover'],
                'grantType' => 'automatic',
                'type' => 'local',
            ],
            20180108132810 => [
                'logId' => '210221',
                'performer' => 'MusikAnimal',
                'comment' => '',
                'added' => [],
                'removed' => ['templateeditor'],
                'grantType' => 'manual',
                'type' => 'local',
            ],
            20180108132758 => [
                'logId' => '210220',
                'performer' => 'MusikAnimal',
                'comment' => '',
                'added' => ['ipblock-exempt', 'filemover', 'templateeditor'],
                'removed' => [],
                'grantType' => 'manual',
                'type' => 'local',
            ],
            20150716002614 => [
                'logId' => '155321',
                'performer' => 'Cyberpower678',
                'comment' => 'Per user request.',
                'added' => ['bureaucrat'],
                'removed' => ['rollbacker'],
                'grantType' => 'manual',
                'type' => 'meta',
            ],
            20141222034127 => [
                'logId' => '140643',
                'performer' => 'Snowolf',
                'comment' => 'per request',
                'added' => ['sysop'],
                'removed' => [],
                'grantType' => 'manual',
                'type' => 'meta',
            ],
        ], $this->editCounter->getRightsChanges());

        $this->editCounterRepo->expects(static::once())
            ->method('getGlobalRightsChanges')
            ->willReturn([[
                'log_id' => '140643',
                'log_timestamp' => '20141222034127',
                'log_comment' => 'per request',
                'log_params' => "\nsysop",
                'log_action' => 'gblrights',
                'log_user_text' => 'Snowolf',
                'type' => 'global',
            ]]);

        static::assertEquals([
            20141222034127 => [
                'logId' => '140643',
                'performer' => 'Snowolf',
                'comment' => 'per request',
                'added' => ['sysop'],
                'removed' => [],
                'grantType' => 'manual',
                'type' => 'global',
            ],
        ], $this->editCounter->getGlobalRightsChanges());

        /** @var \PHPUnit_Framework_MockObject_MockObject|UserRepository $userRepo */
        $userRepo = $this->getMock(UserRepository::class);
        $userRepo->expects(static::once())
            ->method('getUserRights')
            ->willReturn(['sysop', 'bureaucrat']);
        $userRepo->expects(static::once())
            ->method('getGlobalUserRights')
            ->willReturn(['sysop']);
        $this->user->setRepository($userRepo);

        // Current rights.
        static::assertEquals(
            ['sysop', 'bureaucrat'],
            $this->editCounter->getRightsStates()['local']['current']
        );

        // Former rights.
        static::assertEquals(
            ['interface-admin', 'ipblock-exempt', 'filemover', 'templateeditor', 'rollbacker'],
            $this->editCounter->getRightsStates()['local']['former']
        );

        // Admin status.
        static::assertEquals('current', $this->editCounter->getAdminStatus());
    }
}
