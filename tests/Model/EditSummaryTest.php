<?php

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Model\EditSummary;
use App\Model\Project;
use App\Model\User;
use App\Repository\EditSummaryRepository;
use App\Repository\UserRepository;
use App\Tests\SessionHelper;
use App\Tests\TestAdapter;
use ReflectionClass;

/**
 * Tests for EditSummary.
 * @covers \App\Model\EditSummary
 */
class EditSummaryTest extends TestAdapter
{
    use SessionHelper;

    protected EditSummary $editSummary;
    protected Project $project;
    protected User $user;

    /** @var ReflectionClass So we can test private methods. */
    private ReflectionClass $reflectionClass;

    /**
     * Set up shared mocks and class instances.
     */
    public function setUp(): void
    {
        $this->project = new Project('TestProject');
        $userRepo = $this->createMock(UserRepository::class);
        $this->user = new User($userRepo, 'Test user');
        $editSummaryRepo = $this->createMock(EditSummaryRepository::class);
        $this->editSummary = new EditSummary(
            $editSummaryRepo,
            $this->project,
            $this->user,
            'all',
            false,
            false,
            1
        );

        // Don't care that private methods "shouldn't" be tested...
        // With EditSummary many are very test-worthy and otherwise fragile.
        $this->reflectionClass = new ReflectionClass($this->editSummary);
    }

    public function testHasSummary(): void
    {
        $method = $this->reflectionClass->getMethod('hasSummary');
        $method->setAccessible(true);

        static::assertFalse(
            $method->invoke($this->editSummary, ['comment' => ''])
        );
        static::assertTrue(
            $method->invoke($this->editSummary, ['comment' => 'Foo'])
        );
        static::assertFalse(
            $method->invoke($this->editSummary, ['comment' => '/* section title */  '])
        );
        static::assertTrue(
            $method->invoke($this->editSummary, ['comment' => ' /* section title */'])
        );
        static::assertTrue(
            $method->invoke($this->editSummary, ['comment' => '/* section title */ Foo'])
        );
    }

    /**
     * Test that the class properties were properly updated after processing rows.
     */
    public function testGetters(): void
    {
        $method = $this->reflectionClass->getMethod('processRow');
        $method->setAccessible(true);

        foreach ($this->getRevisions() as $revision) {
            $method->invoke($this->editSummary, $revision);
        }

        static::assertEquals(4, $this->editSummary->getTotalEdits());
        static::assertEquals(2, $this->editSummary->getTotalEditsMinor());
        static::assertEquals(2, $this->editSummary->getTotalEditsMajor());

        // In self::setUp() we set the threshold for recent edits to 1.
        static::assertEquals(1, $this->editSummary->getRecentEditsMinor());
        static::assertEquals(1, $this->editSummary->getRecentEditsMajor());

        static::assertEquals(2, $this->editSummary->getTotalSummaries());
        static::assertEquals(1, $this->editSummary->getTotalSummariesMinor());
        static::assertEquals(1, $this->editSummary->getTotalSummariesMajor());

        static::assertEquals(0, $this->editSummary->getRecentSummariesMinor());
        static::assertEquals(1, $this->editSummary->getRecentSummariesMajor());

        static::assertEquals([
            '2016-07' => [
                'total' => 2,
                'summaries' => 1,
            ],
            '2016-10' => [
                'total' => 1,
                'summaries' => 1,
            ],
            '2016-11' => [
                'total' => 1,
            ],
        ], $this->editSummary->getMonthCounts());
    }

    /**
     * Get test revisions.
     * @return string[] Rows with keys 'comment', 'rev_timestamp' and 'rev_minor_edit'.
     */
    private function getRevisions(): array
    {
        // Ordered by rev_timestamp DESC.
        return [
            [
                'comment' => '/* Section title */',
                'rev_timestamp' => '20161103010000',
                'rev_minor_edit' => '1',
            ], [
                'comment' => 'Weeee',
                'rev_timestamp' => '20161003000000',
                'rev_minor_edit' => '0',
            ], [
                'comment' => 'This is an edit summary',
                'rev_timestamp' => '20160705000000',
                'rev_minor_edit' => '1',
            ], [
                'comment' => '',
                'rev_timestamp' => '20160701101205',
                'rev_minor_edit' => '0',
            ],
        ];
    }
}
