<?php

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Model\AutoEdits;
use App\Model\Edit;
use App\Model\Page;
use App\Model\Project;
use App\Model\User;
use App\Repository\AutoEditsRepository;
use App\Repository\EditRepository;
use App\Repository\PageRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for the AutoEdits class.
 * @covers \App\Model\AutoEdits
 */
class AutoEditsTest extends TestAdapter
{
    use ArraySubsetAsserts;

    protected AutoEditsRepository $aeRepo;
    protected EditRepository $editRepo;
    protected PageRepository $pageRepo;
    protected Project $project;
    protected ProjectRepository $projectRepo;
    protected User $user;
    protected UserRepository $userRepo;

    /**
     * Set up class instances and mocks.
     */
    public function setUp(): void
    {
        $this->aeRepo = $this->createMock(AutoEditsRepository::class);
        $this->pageRepo = $this->createMock(PageRepository::class);
        $this->editRepo = $this->createMock(EditRepository::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->project = new Project('test.example.org');
        $this->projectRepo = $this->getProjectRepo();
        $this->projectRepo->method('getMetadata')
            ->willReturn(['namespaces' => [
                '0' => '',
                '1' => 'Talk',
            ]]);
        $this->project->setRepository($this->projectRepo);
        $this->user = new User($this->userRepo, 'Test user');
    }

    /**
     * The constructor.
     */
    public function testConstructor(): void
    {
        $autoEdits = $this->getAutoEdits(
            1,
            strtotime('2017-01-01'),
            strtotime('2018-01-01'),
            'Twinkle',
            50
        );

        static::assertEquals(1, $autoEdits->getNamespace());
        static::assertEquals('2017-01-01', $autoEdits->getStartDate());
        static::assertEquals('2018-01-01', $autoEdits->getEndDate());
        static::assertEquals('Twinkle', $autoEdits->getTool());
        static::assertEquals(50, $autoEdits->getOffset());
    }

    /**
     * User's non-automated edits
     */
    public function testGetNonAutomatedEdits(): void
    {
        $rev = [
            'page_title' => 'Test_page',
            'namespace' => '0',
            'rev_id' => '123',
            'timestamp' => '20170101000000',
            'minor' => '0',
            'length' => '5',
            'length_change' => '-5',
            'comment' => 'Test',
        ];

        $this->aeRepo->expects(static::exactly(2))
            ->method('getNonAutomatedEdits')
            ->willReturn([$rev]);

        $autoEdits = $this->getAutoEdits();
        $rawEdits = $autoEdits->getNonAutomatedEdits(true);
        static::assertArraySubset($rev, $rawEdits[0]);

        $page = Page::newFromRow($this->pageRepo, $this->project, [
            'page_title' => 'Test_page',
            'namespace' => 0,
            'length' => 5,
        ]);
        $edit = new Edit(
            $this->editRepo,
            $this->userRepo,
            $page,
            array_merge($rev, ['user' => $this->user])
        );
        static::assertEquals($edit, $autoEdits->getNonAutomatedEdits()[0]);

        // One more time to ensure things are re-queried.
        static::assertEquals($edit, $autoEdits->getNonAutomatedEdits()[0]);
    }

    /**
     * Test fetching the tools and counts.
     */
    public function testToolCounts(): void
    {
        $toolCounts = [
            'Twinkle' => [
                'link' => 'Project:Twinkle',
                'label' => 'Twinkle',
                'count' => '13',
            ],
            'HotCat' => [
                'link' => 'Special:MyLanguage/Project:HotCat',
                'label' => 'HotCat',
                'count' => '5',
            ],
        ];

        $this->aeRepo->expects(static::once())
            ->method('getToolCounts')
            ->willReturn($toolCounts);
        $autoEdits = $this->getAutoEdits();

        static::assertEquals($toolCounts, $autoEdits->getToolCounts());
        static::assertEquals(18, $autoEdits->getToolsTotal());
    }

    /**
     * User's (semi-)automated edits
     */
    public function testGetAutomatedEdits(): void
    {
        $rev = [
            'page_title' => 'Test_page',
            'namespace' => '1',
            'rev_id' => '123',
            'timestamp' => '20170101000000',
            'minor' => '0',
            'length' => '5',
            'length_change' => '-5',
            'comment' => 'Test ([[WP:TW|TW]])',
        ];

        $this->aeRepo->expects(static::exactly(2))
            ->method('getAutomatedEdits')
            ->willReturn([$rev]);

        $autoEdits = $this->getAutoEdits();
        $rawEdits = $autoEdits->getAutomatedEdits(true);
        static::assertArraySubset($rev, $rawEdits[0]);

        $page = Page::newFromRow($this->pageRepo, $this->project, [
            'page_title' => 'Test_page',
            'namespace' => 1,
            'length' => 5,
        ]);
        $edit = new Edit(
            $this->editRepo,
            $this->userRepo,
            $page,
            array_merge($rev, ['user' => $this->user])
        );
        static::assertEquals($edit, $autoEdits->getAutomatedEdits()[0]);

        // One more time to ensure things are re-queried.
        static::assertEquals($edit, $autoEdits->getAutomatedEdits()[0]);
    }

    /**
     * Counting non-automated edits.
     */
    public function testCounts(): void
    {
        $this->aeRepo->expects(static::once())
            ->method('countAutomatedEdits')
            ->willReturn(50);
        /** @var MockObject|UserRepository $userRepo */
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects(static::once())
            ->method('countEdits')
            ->willReturn(200);
        $this->user->setRepository($userRepo);

        $autoEdits = $this->getAutoEdits();
        $autoEdits->setRepository($this->aeRepo);
        static::assertEquals(50, $autoEdits->getAutomatedCount());
        static::assertEquals(200, $autoEdits->getEditCount());
        static::assertEquals(25, $autoEdits->getAutomatedPercentage());

        // Again to ensure they're not re-queried.
        static::assertEquals(50, $autoEdits->getAutomatedCount());
        static::assertEquals(200, $autoEdits->getEditCount());
        static::assertEquals(25, $autoEdits->getAutomatedPercentage());
    }

    /**
     * @param int|string $namespace Namespace ID or 'all'
     * @param false|int $start Start date as Unix timestamp.
     * @param false|int $end End date as Unix timestamp.
     * @param null $tool The tool we're searching for when fetching (semi-)automated edits.
     * @param false|int $offset Unix timestamp. Used for pagination.
     * @param int|null $limit Number of results to return.
     * @return AutoEdits
     */
    private function getAutoEdits(
        $namespace = 1,
        $start = false,
        $end = false,
        $tool = null,
        $offset = false,
        ?int $limit = null
    ): AutoEdits {
        return new AutoEdits(
            $this->aeRepo,
            $this->editRepo,
            $this->pageRepo,
            $this->userRepo,
            $this->project,
            $this->user,
            $namespace,
            $start,
            $end,
            $tool,
            $offset,
            $limit
        );
    }

    /**
     * Tests the sandbox functionality, bypassing the cache.
     * @todo Find a way to actually test that it bypasses the cache!
     */
    public function testUseSandbox(): void
    {
        $this->aeRepo->expects(static::once())
            ->method('getUseSandbox')
            ->willReturn(true);
        $this->aeRepo->expects(static::never())
            ->method('setCache');
        $autoEdits = $this->getAutoEdits();
        $autoEdits->setRepository($this->aeRepo);

        static::assertTrue($autoEdits->getUseSandbox());
    }
}
