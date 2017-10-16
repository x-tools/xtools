<?php
/**
 * This file contains only the AdminStatsTest class.
 */

namespace Tests\Xtools;

use PHPUnit_Framework_TestCase;
use Xtools\AdminStats;
use Xtools\AdminStatsRepository;
use Xtools\User;
use Xtools\Project;
use Xtools\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests of the Edit class.
 */
class AdminStatsTest extends PHPUnit_Framework_TestCase
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
    public function setUp()
    {
        $this->project = $this->getMock(Project::class, [], ['testwiki']);
        $this->project->method('getUsersInGroups')
            ->willReturn([
                'Bob' => ['sysop', 'checkuser'],
                'Sarah' => ['epcoordinator'],
                'Julie' => ['sysop'],
            ]);

        $this->asRepo = $this->getMock(AdminStatsRepository::class);
    }

    /**
     * Basic getters.
     */
    public function testBasics()
    {
        $startUTC = strtotime('2017-01-01');
        $endUTC = strtotime('2017-03-01');

        // Single namespace, with defaults.
        $as = new AdminStats($this->project, $startUTC, $endUTC);

        $this->asRepo->expects($this->once())
            ->method('getStats')
            ->willReturn($this->adminStatsFactory());
        $as->setRepository($this->asRepo);

        $as->prepareStats();

        $this->assertEquals('2017-01-01', $as->getStart());
        $this->assertEquals('2017-03-01', $as->getEnd());
        $this->assertEquals(59, $as->numDays());
        $this->assertEquals(3, $as->numUsers());
        $this->assertEquals(2, $as->numAdmins());
        $this->assertEquals(1, $as->getNumAdminsWithActions());
        $this->assertEquals(1, $as->getNumAdminsWithoutActions());
    }

    /**
     * Getting admins and their relevant user groups.
     */
    public function testAdminsAndGroups()
    {
        $as = new AdminStats($this->project);
        $this->asRepo->expects($this->exactly(0))
            ->method('getStats')
            ->willReturn($this->adminStatsFactory());
        $as->setRepository($this->asRepo);

        // Without abbreviations.
        $this->assertEquals(
            [
                'Bob' => ['sysop', 'checkuser'],
                'Julie' => ['sysop'],
                'Sarah' => ['epcoordinator'],
            ],
            $as->getAdminsAndGroups(false)
        );

        // With abbreviations.
        $this->assertEquals(
            [
                'Bob' => 'A/CU',
                'Julie' => 'A',
                'Sarah' => '',
            ],
            $as->getAdminsAndGroups()
        );
    }

    /**
     * Test preparation and getting of actual stats.
     */
    public function testStats()
    {
        $as = new AdminStats($this->project);
        $this->asRepo->expects($this->once())
            ->method('getStats')
            ->willReturn($this->adminStatsFactory());
        $as->setRepository($this->asRepo);
        $ret = $as->prepareStats();

        // Test results.
        $this->assertEquals(
            [
                'Bob' => array_merge(
                    $this->adminStatsFactory()[0],
                    ['groups' => 'A/CU']
                ),
                'Julie' => array_merge(
                    $this->adminStatsFactory()[1],
                    ['groups' => 'A']
                ),
                'Sarah' => array_merge(
                    $this->adminStatsFactory()[1], // empty results
                    ['user_name' => 'Sarah', 'groups' => '']
                ),
            ],
            $ret
        );

        // At this point get stats should be the same.
        $this->assertEquals($ret, $as->getStats());
    }

    /**
     * Factory of what database will return.
     */
    private function adminStatsFactory()
    {
        return [
            [
                'user_name' => 'Bob',
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
                'user_name' => 'Julie',
                'delete' => 0,
                'restore' => 0,
                'block' => 0,
                'unblock' => 0,
                'protect' => 0,
                'unprotect' => 0,
                'rights' => 0,
                'import' => 0,
                'total' => 0,
            ]
        ];
    }
}
