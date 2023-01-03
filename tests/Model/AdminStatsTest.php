<?php

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Model\AdminStats;
use App\Model\Project;
use App\Repository\AdminStatsRepository;
use App\Repository\ProjectRepository;
use App\Tests\TestAdapter;

/**
 * Tests of the AdminStats class.
 * @covers \App\Model\AdminStats
 */
class AdminStatsTest extends TestAdapter
{
    protected AdminStatsRepository $asRepo;
    protected Project $project;
    protected ProjectRepository $projectRepo;

    /**
     * Set up container, class instances and mocks.
     */
    public function setUp(): void
    {
        $this->project = $this->createMock(Project::class);
        $this->project->method('getUsersInGroups')
            ->willReturn([
                'Bob' => ['sysop', 'checkuser'],
                'Sarah' => ['epcoordinator'],
            ]);

        $this->asRepo = $this->createMock(AdminStatsRepository::class);

        // This logic is tested with integration tests.
        // Here we just stub empty arrays so AdminStats won't error outl.
        $this->asRepo->method('getUserGroups')
            ->willReturn(['local' => [], 'global' => []]);
    }

    /**
     * Basic getters.
     */
    public function testBasics(): void
    {
        $startUTC = strtotime('2017-01-01');
        $endUTC = strtotime('2017-03-01');

        $this->asRepo->expects(static::once())
            ->method('getStats')
            ->willReturn($this->adminStatsFactory());
        $this->asRepo->method('getRelevantUserGroup')
            ->willReturn('sysop');

        // Single namespace, with defaults.
        $as = new AdminStats($this->asRepo, $this->project, $startUTC, $endUTC, 'admin', []);

        $as->prepareStats();

        static::assertEquals(1483228800, $as->getStart());
        static::assertEquals(1488326400, $as->getEnd());
        static::assertEquals(60, $as->numDays());
        static::assertEquals(1, $as->getNumInRelevantUserGroup());
        static::assertEquals(1, $as->getNumWithActionsNotInGroup());
    }

    /**
     * Getting admins and their relevant user groups.
     */
    public function testAdminsAndGroups(): void
    {
        $as = new AdminStats($this->asRepo, $this->project, 0, 0, 'admin', []);
        $this->asRepo->expects($this->exactly(0))
            ->method('getStats')
            ->willReturn($this->adminStatsFactory());
        $as->setRepository($this->asRepo);

        static::assertEquals(
            [
                'Bob' => ['sysop', 'checkuser'],
                'Sarah' => ['epcoordinator'],
            ],
            $as->getUsersAndGroups()
        );
    }

    /**
     * Test preparation and getting of actual stats.
     */
    public function testStats(): void
    {
        $as = new AdminStats($this->asRepo, $this->project, 0, 0, 'admin', []);
        $this->asRepo->expects($this->once())
            ->method('getStats')
            ->willReturn($this->adminStatsFactory());
        $as->setRepository($this->asRepo);
        $ret = $as->prepareStats();

        // Test results.
        static::assertEquals(
            [
                'Bob' => array_merge(
                    $this->adminStatsFactory()[0],
                    ['user-groups' => ['sysop', 'checkuser']]
                ),
                'Sarah' => array_merge(
                    $this->adminStatsFactory()[1], // empty results
                    ['username' => 'Sarah', 'user-groups' => ['epcoordinator']]
                ),
            ],
            $ret
        );

        // At this point get stats should be the same.
        static::assertEquals($ret, $as->getStats());
    }

    /**
     * Factory of what database will return.
     */
    private function adminStatsFactory(): array
    {
        return [
            [
                'username' => 'Bob',
                'delete' => 5,
                'restore' => 3,
                'block' => 0,
                'unblock' => 1,
                'protect' => 3,
                'unprotect' => 2,
                'rights' => 4,
                'import' => 2,
                'total' => 20,
            ],
            [
                'username' => 'Sarah',
                'delete' => 1,
                'restore' => 0,
                'block' => 0,
                'unblock' => 0,
                'protect' => 0,
                'unprotect' => 0,
                'rights' => 0,
                'import' => 0,
                'total' => 0,
            ],
        ];
    }
}
