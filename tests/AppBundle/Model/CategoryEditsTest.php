<?php
/**
 * This file contains only the CategoryEditsTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\CategoryEdits;
use AppBundle\Model\Edit;
use AppBundle\Model\Page;
use AppBundle\Model\Project;
use AppBundle\Model\User;
use AppBundle\Repository\CategoryEditsRepository;
use AppBundle\Repository\UserRepository;
use Tests\AppBundle\TestAdapter;

/**
 * Tests of the CategoryEdits class.
 */
class CategoryEditsTest extends TestAdapter
{
    /** @var Project The project instance. */
    protected $project;

    /** @var CategoryEdits The CategoryEdits instance. */
    protected $ce;

    /** @var CategoryEditsRepository The AdminStats repo instance. */
    protected $ceRepo;

    /** @var User The user instance. */
    protected $user;

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
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('countEdits')
            ->willReturn(500);
        $this->user = new User('Test user');
        $this->user->setRepository($userRepo);

        $this->ceRepo = $this->createMock(CategoryEditsRepository::class);
        $this->ce = new CategoryEdits(
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
        static::assertEquals('2017-01-01', $this->ce->getStart());
        static::assertEquals('2017-02-01', $this->ce->getEnd());
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
        $this->ceRepo->expects($this->once())
            ->method('countCategoryEdits')
            ->willReturn(200);
        $this->ceRepo->expects($this->once())
            ->method('getCategoryCounts')
            ->willReturn([
                'Living_people' => 150,
                'Musicians_from_New_York_City' => 50,
            ]);
        $this->ce->setRepository($this->ceRepo);

        static::assertEquals(500, $this->ce->getEditCount());
        static::assertEquals(200, $this->ce->getCategoryEditCount());
        static::assertEquals(40.0, $this->ce->getCategoryPercentage());
        static::assertEquals(
            ['Living_people' => 150, 'Musicians_from_New_York_City' => 50],
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
                'page_namespace' => '1',
                'rev_id' => '123',
                'timestamp' => '20170103000000',
                'minor' => '0',
                'length' => '5',
                'length_change' => '-5',
                'comment' => 'Test',
            ],
            [
                'page_title' => 'Foo_bar',
                'page_namespace' => '0',
                'rev_id' => '321',
                'timestamp' => '20170115000000',
                'minor' => '1',
                'length' => '10',
                'length_change' => '5',
                'comment' => 'Weeee',
            ],
        ];

        $pages = [
            new Page($this->project, 'Talk:Test_page'),
            new Page($this->project, 'Foo_bar'),
        ];

        $edits = [
            new Edit($pages[0], array_merge($revs[0], ['user' => $this->user])),
            new Edit($pages[1], array_merge($revs[1], ['user' => $this->user])),
        ];

        $this->ceRepo->expects($this->exactly(2))
            ->method('getCategoryEdits')
            ->willReturn($revs);
        $this->ce->setRepository($this->ceRepo);

        static::assertEquals($revs, $this->ce->getCategoryEdits(true));
        static::assertEquals($edits, $this->ce->getCategoryEdits());

        // Shouldn't call the repo method again (asserted by the ->exactly(2) above).
        $this->ce->getCategoryEdits();
    }
}
