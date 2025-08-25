<?php

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Model\CategoryEdits;
use App\Model\Edit;
use App\Model\Page;
use App\Model\Project;
use App\Model\User;
use App\Repository\CategoryEditsRepository;
use App\Repository\EditRepository;
use App\Repository\PageRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;

/**
 * Tests of the CategoryEdits class.
 * @covers \App\Model\CategoryEdits
 */
class CategoryEditsTest extends TestAdapter
{
    protected CategoryEdits $ce;
    protected CategoryEditsRepository $ceRepo;
    protected EditRepository $editRepo;
    protected PageRepository $pageRepo;
    protected Project $project;
    protected User $user;
    protected UserRepository $userRepo;

    /**
     * Set up class instances and mocks.
     */
    public function setUp(): void
    {
        $this->project = $this->createMock(Project::class);
        $this->project->method('getNamespaces')
            ->willReturn([
                0 => '',
                1 => 'Talk',
            ]);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->userRepo->method('countEdits')
            ->willReturn(500);
        $this->user = new User($this->userRepo, 'Test user');

        $this->ceRepo = $this->createMock(CategoryEditsRepository::class);
        $this->editRepo = $this->createMock(EditRepository::class);
        $this->pageRepo = $this->createMock(PageRepository::class);
        $this->ce = new CategoryEdits(
            $this->ceRepo,
            $this->project,
            $this->user,
            ['Living_people', 'Musicians_from_New_York_City'],
            strtotime('2017-01-01'),
            strtotime('2017-02-01'),
            50
        );
    }

    /**
     * Basic getters.
     */
    public function testBasics(): void
    {
        static::assertEquals('2017-01-01', $this->ce->getStartDate());
        static::assertEquals('2017-02-01', $this->ce->getEndDate());
        static::assertEquals(50, $this->ce->getOffset());
        static::assertEquals(
            ['Living_people', 'Musicians_from_New_York_City'],
            $this->ce->getCategories()
        );
        static::assertEquals(
            'Living_people|Musicians_from_New_York_City',
            $this->ce->getCategoriesPiped()
        );
        static::assertEquals(
            ['Living people', 'Musicians from New York City'],
            $this->ce->getCategoriesNormalized()
        );
    }

    /**
     * Methods around counting edits in category.
     */
    public function testCategoryCounts(): void
    {
        $counts = [
            'Living_people' => ['editCount' => 150, 'pageCount' => 10],
            'Musicians_from_New_York_City' => ['editCount' => 50, 'pageCount' => 1],
        ];
        $this->ceRepo->expects($this->once())
            ->method('countCategoryEdits')
            ->willReturn(200);
        $this->ceRepo->expects($this->once())
            ->method('getCategoryCounts')
            ->willReturn($counts);
        $this->ce->setRepository($this->ceRepo);

        static::assertEquals(500, $this->ce->getEditCount());
        static::assertEquals(200, $this->ce->getCategoryEditCount());
        static::assertEquals(11, $this->ce->getCategoryPageCount());
        static::assertEquals(40.0, $this->ce->getCategoryPercentage());
        static::assertEquals(
            $counts,
            $this->ce->getCategoryCounts()
        );

        // Shouldn't call the repo method again (asserted by the $this->once() above).
        $this->ce->getCategoryCounts();
    }

    /**
     * Category edits.
     */
    public function testCategoryEdits(): void
    {
        $revs = [
            [
                'page_title' => 'Test_page',
                'namespace' => '1',
                'rev_id' => '123',
                'timestamp' => '20170103000000',
                'minor' => '0',
                'length' => '5',
                'length_change' => '-5',
                'comment' => 'Test',
            ],
            [
                'page_title' => 'Foo_bar',
                'namespace' => '0',
                'rev_id' => '321',
                'timestamp' => '20170115000000',
                'minor' => '1',
                'length' => '10',
                'length_change' => '5',
                'comment' => 'Weeee',
            ],
        ];

        $pages = [
            Page::newFromRow($this->pageRepo, $this->project, [
                'page_title' => 'Test_page',
                'namespace' => 1,
            ]),
            Page::newFromRow($this->pageRepo, $this->project, [
                'page_title' => 'Foo_bar',
                'namespace' => 0,
            ]),
        ];

        $edits = [
            new Edit($this->editRepo, $this->userRepo, $pages[0], array_merge($revs[0], ['user' => $this->user])),
            new Edit($this->editRepo, $this->userRepo, $pages[1], array_merge($revs[1], ['user' => $this->user])),
        ];

        $this->ceRepo->expects($this->exactly(2))
            ->method('getCategoryEdits')
            ->willReturn($revs);
        $this->ceRepo->expects($this->once())
            ->method('getEditsFromRevs')
            ->willReturn($edits);

        static::assertEquals($revs, $this->ce->getCategoryEdits(true));
        static::assertEquals($edits, $this->ce->getCategoryEdits());

        // Shouldn't call the repo method again (asserted by the ->exactly(2) above).
        $this->ce->getCategoryEdits();
    }
}
