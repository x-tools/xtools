<?php
/**
 * This file contains only the AdminStatsTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\AdminStats;
use AppBundle\Model\Project;
use AppBundle\Repository\AdminStatsRepository;
use AppBundle\Repository\ProjectRepository;
use Tests\AppBundle\TestAdapter;

/**
 * Tests of the AdminStats class.
 */
class AdminStatsTest extends TestAdapter
{
    /** @var Project The project instance. */
    protected $project;

    /** @var ProjectRepository The project repo instance. */
    protected $projectRepo;

    /** @var AdminStatsRepository The AdminStats repo instance. */
    protected $asRepo;

    /**
     * Set up container, class instances and mocks.
     */
    public function setUp(): void
    {
        $this->project = $this->getMock(Project::class, [], ['testwiki']);
        $this->project->method('getUsersInGroups')
            ->willReturn([
                'Bob' => ['sysop', 'checkuser'],
                'Sarah' => ['epcoordinator'],
            ]);

        $this->asRepo = $this->getMock(AdminStatsRepository::class);
    }

    /**
     * Basic getters.
     */
    public function testBasics(): void
    {
        $startUTC = strtotime('2017-01-01');
        $endUTC = strtotime('2017-03-01');

        // Single namespace, with defaults.
        $as = new AdminStats($this->project, $startUTC, $endUTC, 'admin', []);

        $this->asRepo->expects(static::once())
            ->method('getStats')
            ->willReturn($this->adminStatsFactory());
        $this->asRepo->method('getRelevantUserGroup')
            ->willReturn('sysop');
        $as->setRepository($this->asRepo);

        $as->prepareStats();

        static::assertEquals(1483228800, $as->getStart());
        static::assertEquals(1488326400, $as->getEnd());
        static::assertEquals(59, $as->numDays());
        static::assertEquals(1, $as->getNumInRelevantUserGroup());
        static::assertEquals(1, $as->getNumWithActionsNotInGroup());
    }

    /**
     * Getting admins and their relevant user groups.
     */
    public function testAdminsAndGroups(): void
    {
        $as = new AdminStats($this->project, 0, 0, 'admin', []);
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
        $as = new AdminStats($this->project, 0, 0, 'admin', []);
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
