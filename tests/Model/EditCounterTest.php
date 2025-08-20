<?php

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Helper\I18nHelper;
use App\Model\EditCounter;
use App\Model\Project;
use App\Model\User;
use App\Model\UserRights;
use App\Repository\EditCounterRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Tests\SessionHelper;
use App\Tests\TestAdapter;
use DateTime;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

/**
 * Tests for the EditCounter.
 * @covers \App\Model\EditCounter
 */
class EditCounterTest extends TestAdapter
{
    use ArraySubsetAsserts;
    use SessionHelper;

    protected EditCounter $editCounter;
    protected EditCounterRepository $editCounterRepo;
    protected I18nHelper $i18n;
    protected Project $project;
    protected ProjectRepository $projectRepo;
    protected User $user;
    protected UserRepository $userRepo;

    /**
     * Set up shared mocks and class instances.
     */
    public function setUp(): void
    {
        $session = $this->createSession(static::createClient());
        $this->i18n = new I18nHelper(
            $this->getRequestStack($session),
            static::getContainer()->getParameter('kernel.project_dir')
        );

        $this->editCounterRepo = $this->createMock(EditCounterRepository::class);
        $this->projectRepo = $this->getProjectRepo();
        $this->project = new Project('test.example.org');
        $this->project->setRepository($this->projectRepo);

        $this->userRepo = $this->createMock(UserRepository::class);
        $this->user = new User($this->userRepo, 'Testuser');

        $this->editCounter = new EditCounter(
            $this->editCounterRepo,
            $this->i18n,
            $this->createMock(UserRights::class),
            $this->project,
            $this->user
        );
        $this->editCounter->setRepository($this->editCounterRepo);
    }

    /**
     * Reset the EditCounter (notably prevents internal caching)
     */
    private function resetEditCounter(): void
    {
        $this->editCounter = new EditCounter(
            $this->editCounterRepo,
            $this->i18n,
            $this->createMock(UserRights::class),
            $this->project,
            $this->user
        );
        $this->editCounter->setRepository($this->editCounterRepo);
    }

    /**
     * Log counts and associated getters.
     */
    public function testLogCounts(): void
    {
        static::assertInstanceOf(UserRights::class, $this->editCounter->getUserRights());
        $this->editCounterRepo->expects(static::once())
            ->method('getLogCounts')
            ->willReturn([
                'delete-delete' => 0,
                'delete-restore' => 2,
                'move-move' => 1,
                'block-block' => 2,
                'block-reblock' => 3,
                'block-unblock' => 4,
                // intentionally does not include 'protect-protect'
                'protect-modify' => 5,
                'protect-unprotect' => 6,
                'delete-revision' => 7,
                'upload-upload' => 8,
                // intentionally does not include 'delete-event'
                'rights-rights' => 9,
                'abusefilter-modify' => 10,
                'thanks-thank' => 11,
                'patrol-patrol' => 12,
                'merge-merge' => 13,
                // Imports should add up to 6
                'import-import' => 1,
                'import-interwiki' => 2,
                'import-upload' => 3,
                // Content model changes, sum 3
                'contentmodel-new' => 1,
                'contentmodel-change' => 2,
                // Review approvals, sum 10
                'review-approve' => 1,
                'review-approve2' => 2,
                'review-approve-i' => 3,
                'review-approve2-i' => 4,
                // Account creation, sum 3
                'newusers-create2' => 1,
                'newusers-byemail' => 2,
                // PageTriage reviews, sum 9
                'pagetriage-curation-reviewed' => 2,
                'pagetriage-curation-reviewed-article' => 3,
                'pagetriage-curation-reviewed-redirect' => 4,
            ]);
        static::assertEquals(0, $this->editCounter->getLogCounts()['delete-delete']);
        static::assertEquals(0, $this->editCounter->countPagesDeleted());
        static::assertEquals(1, $this->editCounter->countPagesMoved());
        static::assertEquals(2, $this->editCounter->countBlocksSet());
        static::assertEquals(3, $this->editCounter->countReblocksSet());
        static::assertEquals(4, $this->editCounter->countUnblocksSet());
        static::assertEquals(0, $this->editCounter->countPagesProtected());
        static::assertEquals(5, $this->editCounter->countPagesReprotected());
        static::assertEquals(6, $this->editCounter->countPagesUnprotected());
        static::assertEquals(7, $this->editCounter->countEditsDeleted());
        static::assertEquals(8, $this->editCounter->countFilesUploaded());
        static::assertEquals(0, $this->editCounter->countLogsDeleted());
        static::assertEquals(9, $this->editCounter->countRightsModified());
        static::assertEquals(10, $this->editCounter->countAbuseFilterChanges());
        static::assertEquals(11, $this->editCounter->thanks());
        static::assertEquals(12, $this->editCounter->patrols());
        static::assertEquals(13, $this->editCounter->merges());
        static::assertEquals(6, $this->editCounter->countPagesImported());
        static::assertEquals(3, $this->editCounter->countContentModelChanges());
        static::assertEquals(10, $this->editCounter->approvals());
        static::assertEquals(3, $this->editCounter->accountsCreated());
        static::assertEquals(9, $this->editCounter->reviews());
        static::assertEquals(2, $this->editCounter->countPagesRestored());
    }

    /**
     * Test counts of Commons uploads and local/Commons moves
     */
    public function testFileCounts(): void
    {
        $this->editCounterRepo->expects(static::any())
            ->method('getFileCounts')
            ->willReturn([
                'files_moved' => 1,
                'files_moved_commons' => 2,
                'files_uploaded_commons' => 3,
            ]);
        static::assertEquals(3, $this->editCounter->countFilesUploadedCommons());
        static::assertEquals(1, $this->editCounter->countFilesMoved());
        static::assertEquals(2, $this->editCounter->countFilesMovedCommons());
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
                'minor' => 5,
                'day' => 10,
                'week' => 15,
                'edited-live' => 2,
                'edited-deleted' => 2,
            ]);

        static::assertEquals(100, $this->editCounter->countLiveRevisions());
        static::assertEquals(10, $this->editCounter->countDeletedRevisions());
        static::assertEquals(110, $this->editCounter->countAllRevisions());
        static::assertEquals(100, $this->editCounter->countLast5000());
        static::assertEquals(5, $this->editCounter->countMinorRevisions());
        static::assertEquals(10, $this->editCounter->countRevisionsInLast('day'));
        static::assertEquals(15, $this->editCounter->countRevisionsInLast('week'));
        static::assertEquals(27.5, $this->editCounter->averageRevisionsPerPage());
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
            ]);
        static::assertEquals(22, $this->editCounter->averageRevisionsPerDay());
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
     * Test the fallback if one of rev_first or rev_latest doesn't have a timestamp
     */
    public function testMissingTimestamps(): void
    {
        $this->editCounterRepo->expects(static::once())->method('getFirstAndLatestActions')->willReturn([
                'rev_first' => [
                    'id' => 123,
                    'type' => null,
                ],
                'rev_latest' => [
                    'id' => 321,
                    'type' => null,
                ],
            ]);
        static::assertEquals(0, $this->editCounter->getDays());
        static::assertEquals(0, $this->editCounter->averageRevisionsPerDay());
    }

    /**
     * Test that page counts are reported correctly.
     */
    public function testPageCounts(): void
    {
        $this->editCounterRepo->expects(static::once())
            ->method('getPairData')
            ->willReturn([
                'edited-live' => 3,
                'edited-deleted' => 1,
                'created-live' => 6,
                'created-deleted' => 2,
            ]);

        static::assertEquals(3, $this->editCounter->countLivePagesEdited());
        static::assertEquals(1, $this->editCounter->countDeletedPagesEdited());
        static::assertEquals(4, $this->editCounter->countAllPagesEdited());

        static::assertEquals(6, $this->editCounter->countCreatedPagesLive());
        static::assertEquals(2, $this->editCounter->countPagesCreatedDeleted());
        static::assertEquals(8, $this->editCounter->countPagesCreated());
    }

    /**
     * Test fallbacks for when no pages were edited
     */
    public function testNoPages(): void
    {
        $this->editCounterRepo->expects(static::once())
            ->method('getPairData')
            ->willReturn([
                'edited-live' => 0,
                'edited-deleted' => 0,
            ]);
        static::assertEquals(0, $this->editCounter->countAllPagesEdited());
        static::assertEquals(0, $this->editCounter->averageRevisionsPerPage());
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
        static::assertEquals(30, $this->editCounter->liveRevisionsFromNamespaces());
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
                    'namespace' => '0',
                    'count' => '10',
                ],
                [
                    'year' => '2017',
                    'month' => '3',
                    'namespace' => '0',
                    'count' => '20',
                ],
                [
                    'year' => '2017',
                    'month' => '2',
                    'namespace' => '1',
                    'count' => '50',
                ],
            ]);

        // Mock current time by passing it in (dummy parameter, so to speak).
        $monthCounts = $this->editCounter->monthCounts($mockTime);

        // Make sure zeros were filled in for months with no edits, and for each namespace.
        static::assertArraySubset(
            [
                '2017-01' => 0,
                '2017-02' => 0,
                '2017-03' => 20,
                '2017-04' => 0,
            ],
            $monthCounts['totals'][0]
        );
        static::assertArraySubset(
            [
                '2016-12' => 0,
            ],
            $monthCounts['totals'][1]
        );

        // Assert only active months are reported.
        static::assertArrayNotHasKey('2016-11', $monthCounts['totals'][0]);
        static::assertArrayHasKey('2016-12', $monthCounts['totals'][0]);
        static::assertArrayHasKey('2017-04', $monthCounts['totals'][0]);
        static::assertArrayNotHasKey('2017-05', $monthCounts['totals'][0]);

        // Assert that only active namespaces are reported.
        static::assertSame([0, 1], array_keys($monthCounts['totals']));

        // Labels for the months
        static::assertSame(
            ['2016-12', '2017-01', '2017-02', '2017-03', '2017-04'],
            $monthCounts['monthLabels']
        );

        // Labels for the years
        static::assertSame(['2016', '2017'], $monthCounts['yearLabels']);

        // Month counts by namespace.
        $monthsWithNamespaces = $this->editCounter->monthCountsWithNamespaces($mockTime);
        static::assertSame(
            $monthCounts['monthLabels'],
            array_keys($monthsWithNamespaces)
        );
        static::assertSame([0, 1], array_keys($monthsWithNamespaces['2017-03']));

        $yearTotals = $this->editCounter->yearTotals($mockTime);
        static::assertSame(['2016' => 10, '2017' => 70], $yearTotals);
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
                    'namespace' => '1',
                    'count' => '5',
                ],
                [
                    'year' => '2016',
                    'month' => '12',
                    'namespace' => '0',
                    'count' => '10',
                ],
                [
                    'year' => '2017',
                    'month' => '3',
                    'namespace' => '0',
                    'count' => '20',
                ],
                [
                    'year' => '2017',
                    'month' => '2',
                    'namespace' => '1',
                    'count' => '50',
                ],
            ]);

        // Mock current time by passing it in (dummy parameter, so to speak).
        $yearCounts = $this->editCounter->yearCounts(new DateTime('2017-04-30 23:59:59'));

        // Make sure zeros were filled in for years with no edits, and for each namespace.
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

        // Mock current time by passing it in (dummy parameter, so to speak).
        $yearCountsWithNamespaces = $this->editCounter->yearCountsWithNamespaces(new DateTime('2017-04-30 23:59:59'));

        // Make sure zeros were filled in for years with no edits, and for each namespace.
        static::assertArraySubset(
            [
                0 => 10,
                1 => 0,
            ],
            $yearCountsWithNamespaces[2016]
        );

        // Assert that only active years are reported
        static::assertEquals([2015, 2016, 2017], array_keys($yearCountsWithNamespaces));

        // Assert that only active namespaces are reported.
        static::assertEquals([0, 1], array_keys($yearCountsWithNamespaces[2016]));
    }

    /**
     * Test reordering and filling of timecard values
     */
    public function testTimeCard(): void
    {
        $this->editCounterRepo->expects(static::once())
            ->method("getTimeCard")
            ->willReturn([
                [
                    'day_of_week' => 1, // Sunday
                    'hour' => 2, // 2 AM
                    'value' => 42,
                ],
                [
                    'day_of_week' => 4, // Wednesday
                    'hour' => 15, // 3 PM
                    'value' => 33,
                ],
            ]);
        $results = $this->editCounter->timecard();
        $hours = range(0, 23);
        $days = range(1, 7);
        // The hours are seven cycles from 0 to 23
        static::assertEquals(
            array_merge(...array_fill(0, 7, $hours)),
            array_map(fn($row) => $row['hour'], $results)
        );
        // The days are 24 of each of the seven, in order
        static::assertEquals(
            array_merge(...array_map(fn($day) => array_fill(0, 24, $day), $days)),
            array_map(fn($row) => $row['day_of_week'], $results)
        );
        // All values are positive
        static::assertEmpty(
            array_filter($results, fn($row) => (int)$row['value'] < 0)
        );
    }

    /**
     * Test block logic
     * @dataProvider longestBlockProvider
     * @param array $blockLog
     * @param int $longestDuration
     * @param int $blockCount
     */
    public function testBlocks(array $blockLog, int $longestDuration, int $blockCount): void
    {
        $this->resetEditCounter();
        $this->editCounterRepo->expects(static::once())
            ->method('getBlocksReceived')
            ->with($this->project, $this->user)
            ->willReturn($blockLog);
        static::assertEquals($longestDuration, $this->editCounter->getLongestBlockSeconds());
        static::assertEmpty(array_filter(
            $this->editCounter->getBlocks('received'),
            fn($block) => !in_array($block['log_action'], ['block', 'reblock'])
        ));
        static::assertEquals($blockCount, $this->editCounter->countBlocksReceived());
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
                2,
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
                2,
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
                2,
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
                2,
            ],
            // Block that's active, with an explicit expiry set.
            [
                [[
                    'log_timestamp' => '20170927203624',
                    'log_params' => 'a:2:{s:11:"5::duration";s:29:"Sat, 06 Oct 9999 12:36:00 GMT"' .
                        ';s:8:"6::flags";s:11:"noautoblock";}',
                    'log_action' => 'block',
                ]],
                251888543976,
                1,
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
                2,
            ],
            // No blocks; 0 seconds
            [
                [],
                0,
                0,
            ],
            // Finite block that was reblocked to infinite
            [
                [[
                    'log_timestamp' => '20160513200200',
                    'log_params' => 'a:2:{s:11:"5::duration";s:10:"24 hours"' .
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
                2,
            ],
        ];
    }

    /**
     * Parsing block log entries.
     * @dataProvider blockLogProvider
     * @param array $logEntry
     * @param array $assertion
     */
    public function testParseBlockLogEntry(array $logEntry, array $assertion): void
    {
        static::assertEquals(
            $this->editCounter->parseBlockLogEntry($logEntry),
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
     * Test counting of edit data
     */
    public function testEditData(): void
    {
        $this->editData = [];
        $this->editCounterRepo->expects(static::any())
            ->method("getEditData")
            ->will($this->returnCallBack(function () {
                return $this->editData;
            }));

        // PRP quality changes
        $this->editData = [
            'tag_lists' => [
                [ 'randomtag', 'proofreadpage-quality1' ],
                [ 'proofreadpage-quality2', 'proofreadpage-quality0' ], // Duplicates are intentionally discarded
                [],
                [ 'proofreadpage-quality2' ],
                [ 'proofreadpage-quality3', 'a' ],
                [ 'proofreadpagequality0', 'proofreadpage-quality3' ],
                [ 'prp-quality0', 'proofreadpage-quality3' ],
            ],
        ];
        static::assertEquals(
            $this->editCounter->countQualityChanges(),
            [ 0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 0, 'total' => 6 ],
        );
        $this->resetEditCounter();

        // Average size rounding and default
        $this->editData = [
            'average_size' => 3.1415926,
        ];
        static::assertEquals(
            $this->editCounter->averageEditSize(),
            3.142
        );
        $this->resetEditCounter();
        $this->editData = [];
        static::assertEquals(
            $this->editCounter->averageEditSize(),
            0
        );
        $this->resetEditCounter();
    }
}
