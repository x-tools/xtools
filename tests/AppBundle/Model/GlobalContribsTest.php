<?php
declare(strict_types = 1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\GlobalContribs;
use AppBundle\Model\Project;
use AppBundle\Model\User;
use AppBundle\Repository\GlobalContribsRepository;
use AppBundle\Repository\ProjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\AppBundle\TestAdapter;

class GlobalContribsTest extends TestAdapter
{
    /** @var GlobalContribsRepository Repository for Global Contribs tool. */
    protected $globalContribsRepo;

    /**
     * Set up shared mocks and class instances.
     */
    public function setUp(): void
    {
        $this->globalContribsRepo = $this->createMock(GlobalContribsRepository::class);
    }

    /**
     * Get all global edit counts, or just the top N, or the overall grand total.
     */
    public function testGlobalEditCounts(): void
    {
        $user = new User('Test user');
        $globalContribs = new GlobalContribs($user);
        $globalContribs->setRepository($this->globalContribsRepo);
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
        $this->globalContribsRepo->expects(static::once())
            ->method('globalEditCounts')
            ->willReturn($editCounts);

        // Get the top 2.
        static::assertEquals(
            [
                ['project' => $wiki1, 'total' => 50],
                ['project' => $wiki2, 'total' => 40],
            ],
            $globalContribs->globalEditCountsTopN(2)
        );

        // And the bottom 4.
        static::assertEquals(95, $globalContribs->globalEditCountWithoutTopN(2));

        // Grand total.
        static::assertEquals(185, $globalContribs->globalEditCount());
    }

    /**
     * Test global edits.
     */
    public function testGlobalEdits(): void
    {
        $user = new User('Test user');
        $globalContribs = new GlobalContribs($user);
        $globalContribs->setRepository($this->globalContribsRepo);

        /** @var ProjectRepository|MockObject $wiki1Repo */
        $wiki1Repo = $this->createMock(ProjectRepository::class);
        $wiki1Repo->expects(static::once())
            ->method('getMetadata')
            ->willReturn(['namespaces' => [2 => 'User']]);
        $wiki1Repo->expects(static::once())
            ->method('getOne')
            ->willReturn([
                'dbName' => 'wiki1',
                'url' => 'https://wiki1.example.org',
            ]);
        $wiki1 = new Project('wiki1');
        $wiki1->setRepository($wiki1Repo);

        $contribs = [[
            'dbName' => 'wiki1',
            'id' => 1,
            'timestamp' => '20180101000000',
            'unix_timestamp' => '1514764800',
            'minor' => 0,
            'deleted' => 0,
            'length' => 5,
            'length_change' => 10,
            'parent_id' => 0,
            'username' => 'Test user',
            'page_title' => 'Foo bar',
            'page_namespace' => '2',
            'comment' => 'My user page',
        ]];

        $this->globalContribsRepo->expects(static::once())
            ->method('getProjectsWithEdits')
            ->willReturn([
                'wiki1' => $wiki1,
            ]);
        $this->globalContribsRepo->expects(static::once())
            ->method('getRevisions')
            ->willReturn($contribs);

        $edits = $globalContribs->globalEdits();

        static::assertCount(1, $edits);
        static::assertEquals('My user page', $edits['1514764800-1']->getComment());
    }
}
