<?php
/**
 * This file contains only the AutoEditsTest class.
 */

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Model\AutoEdits;
use App\Model\Edit;
use App\Model\Page;
use App\Model\Project;
use App\Model\User;
use App\Repository\AutoEditsRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for the AutoEdits class.
 */
class AutoEditsTest extends TestAdapter
{
    use ArraySubsetAsserts;

    /** @var Project The project instance. */
    protected $project;

    /** @var MockObject|ProjectRepository The project repo instance. */
    protected $projectRepo;

    /** @var MockObject|AutoEditsRepository The AutoEdits repo instance. */
    protected $aeRepo;

    /** @var User The user instance. */
    protected $user;

    /**
     * Set up class instances and mocks.
     */
    public function setUp(): void
    {
        $this->project = new Project('test.example.org');
        $this->projectRepo = $this->getProjectRepo();
        $this->projectRepo->method('getMetadata')
            ->willReturn(['namespaces' => [
                '0' => '',
                '1' => 'Talk',
            ]]);
        $this->project->setRepository($this->projectRepo);
        $this->user = new User('Test user');
        $this->aeRepo = $this->createMock(AutoEditsRepository::class);
    }

    /**
     * The constructor.
     */
    public function testConstructor(): void
    {
        $autoEdits = new AutoEdits(
            $this->project,
            $this->user,
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
            'page_namespace' => '0',
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

        $autoEdits = new AutoEdits($this->project, $this->user, 1);
        $autoEdits->setRepository($this->aeRepo);

        $rawEdits = $autoEdits->getNonAutomatedEdits(true);
        static::assertArraySubset($rev, $rawEdits[0]);

        $page = Page::newFromRow($this->project, [
            'page_title' => 'Test_page',
            'page_namespace' => 0,
        ]);
        $edit = new Edit(
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
        $autoEdits = new AutoEdits($this->project, $this->user, 1);
        $autoEdits->setRepository($this->aeRepo);

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
            'page_namespace' => '1',
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

        $autoEdits = new AutoEdits($this->project, $this->user, 1);
        $autoEdits->setRepository($this->aeRepo);

        $rawEdits = $autoEdits->getAutomatedEdits(true);
        static::assertArraySubset($rev, $rawEdits[0]);

        $page = Page::newFromRow($this->project, [
            'page_title' => 'Test_page',
            'page_namespace' => 1,
        ]);
        $edit = new Edit(
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

        $autoEdits = new AutoEdits($this->project, $this->user, 1);
        $autoEdits->setRepository($this->aeRepo);
        static::assertEquals(50, $autoEdits->getAutomatedCount());
        static::assertEquals(200, $autoEdits->getEditCount());
        static::assertEquals(25, $autoEdits->getAutomatedPercentage());

        // Again to ensure they're not re-queried.
        static::assertEquals(50, $autoEdits->getAutomatedCount());
        static::assertEquals(200, $autoEdits->getEditCount());
        static::assertEquals(25, $autoEdits->getAutomatedPercentage());
    }
}
