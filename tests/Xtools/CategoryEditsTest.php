<?php
/**
 * This file contains only the CategoryEditsTest class.
 */

namespace Tests\Xtools;

use PHPUnit_Framework_TestCase;
use Xtools\CategoryEdits;
use Xtools\CategoryEditsRepository;
use Xtools\Edit;
use Xtools\Page;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;

/**
 * Tests of the CategoryEdits class.
 */
class CategoryEditsTest extends PHPUnit_Framework_TestCase
{
    /** @var Project The project instance. */
    protected $project;

    /** @var CategoryEditsRepository The AdminStats repo instance. */
    protected $ceRepo;

    /** @var User The user instance. */
    protected $user;

    /**
     * Set up container, class instances and mocks.
     */
    public function setUp()
    {
        $this->project = $this->getMock(Project::class, [], ['testwiki']);
        $this->project->method('getNamespaces')
            ->willReturn([
                0 => '',
                1 => 'Talk',
            ]);
        $userRepo = $this->getMock(UserRepository::class);
        $userRepo->method('countEdits')
            ->willReturn(500);
        $this->user = new User('Test user');
        $this->user->setRepository($userRepo);

        $this->ceRepo = $this->getMock(CategoryEditsRepository::class);
        $this->ce = new CategoryEdits(
            $this->project,
            $this->user,
            ['Living_people', 'Musicians_from_New_York_City'],
            '2017-01-01',
            '2017-02-01',
            50
        );
    }

    /**
     * Basic getters.
     */
    public function testBasics()
    {
        $this->assertEquals('2017-01-01', $this->ce->getStart());
        $this->assertEquals('2017-02-01', $this->ce->getEnd());
        $this->assertEquals(50, $this->ce->getOffset());
        $this->assertEquals(
            ['Living_people', 'Musicians_from_New_York_City'],
            $this->ce->getCategories()
        );
        $this->assertEquals(
            'Living_people|Musicians_from_New_York_City',
            $this->ce->getCategoriesPiped()
        );
        $this->assertEquals(
            ['Living people', 'Musicians from New York City'],
            $this->ce->getCategoriesNormalized()
        );
    }

    /**
     * Methods around counting edits in category.
     */
    public function testCategoryCounts()
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

        $this->assertEquals(500, $this->ce->getEditCount());
        $this->assertEquals(200, $this->ce->getCategoryEditCount());
        $this->assertEquals(40.0, $this->ce->getCategoryPercentage());
        $this->assertEquals(
            ['Living_people' => 150, 'Musicians_from_New_York_City' => 50],
            $this->ce->getCategoryCounts()
        );

        // Shouldn't call the repo method again (asserted by the $this->once() above).
        $this->ce->getCategoryCounts();
    }

    /**
     * Category edits.
     */
    public function testCategoryEdits()
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

        $this->assertEquals($revs, $this->ce->getCategoryEdits(true));
        $this->assertEquals($edits, $this->ce->getCategoryEdits());

        // Shouldn't call the repo method again (asserted by the ->exactly(2) above).
        $this->ce->getCategoryEdits();
    }
}
