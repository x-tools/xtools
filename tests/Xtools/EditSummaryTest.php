<?php
/**
 * This file contains only the EditSummaryTest class.
 */

namespace Tests\Xtools;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Xtools\EditSummary;
use Xtools\Edit;
use Xtools\Project;
use Xtools\User;

/**
 * Tests for EditSummary.
 */
class EditSummaryTest extends WebTestCase
{
    /** @var EditSummary The article info instance. */
    protected $editSummary;

    /** @var User The user instance. */
    protected $user;

    /** @var Project The project instance. */
    protected $project;

    /**
     * Set up shared mocks and class instances.
     */
    public function setUp()
    {
        $client = static::createClient();
        $this->project = new Project('TestProject');
        $this->user = new User('Test user');
        $this->editSummary = new EditSummary($this->project, $this->user, 'all', 1);

        // Don't care that private methods "shouldn't" be tested...
        // With EditSummary many are very testworthy and otherwise fragile.
        $this->reflectionClass = new \ReflectionClass($this->editSummary);
    }

    public function testHasSummary()
    {
        $method = $this->reflectionClass->getMethod('hasSummary');
        $method->setAccessible(true);

        $this->assertFalse(
            $method->invoke($this->editSummary, ['rev_comment' => ''])
        );
        $this->assertTrue(
            $method->invoke($this->editSummary, ['rev_comment' => 'Foo'])
        );
        $this->assertFalse(
            $method->invoke($this->editSummary, ['rev_comment' => '/* section title */  '])
        );
        $this->assertTrue(
            $method->invoke($this->editSummary, ['rev_comment' => ' /* section title */'])
        );
        $this->assertTrue(
            $method->invoke($this->editSummary, ['rev_comment' => '/* section title */ Foo'])
        );
    }

    /**
     * Test that the class properties were properly updated after processing rows.
     */
    public function testGetters()
    {
        $method = $this->reflectionClass->getMethod('processRow');
        $method->setAccessible(true);

        foreach ($this->getRevisions() as $revision) {
            $method->invoke($this->editSummary, $revision);
        }

        $this->assertEquals(4, $this->editSummary->getTotalEdits());
        $this->assertEquals(2, $this->editSummary->getTotalEditsMinor());
        $this->assertEquals(2, $this->editSummary->getTotalEditsMajor());

        // In self::setUp() we set the treshold for recent edits to 1.
        $this->assertEquals(1, $this->editSummary->getRecentEditsMinor());
        $this->assertEquals(1, $this->editSummary->getRecentEditsMajor());

        $this->assertEquals(2, $this->editSummary->getTotalSummaries());
        $this->assertEquals(1, $this->editSummary->getTotalSummariesMinor());
        $this->assertEquals(1, $this->editSummary->getTotalSummariesMajor());

        $this->assertEquals(0, $this->editSummary->getRecentSummariesMinor());
        $this->assertEquals(1, $this->editSummary->getRecentSummariesMajor());

        $this->assertEquals([
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
     * @return string[] Rows with keys 'rev_comment', 'rev_timestamp' and 'rev_minor_edit'.
     */
    private function getRevisions()
    {
        // Ordered by rev_timestamp DESC.
        return [
            [
                'rev_comment' => '/* Section title */',
                'rev_timestamp' => '20161103010000',
                'rev_minor_edit' => '1',
            ], [
                'rev_comment' => 'Weeee',
                'rev_timestamp' => '20161003000000',
                'rev_minor_edit' => '0',
            ], [
                'rev_comment' => 'This is an edit summary',
                'rev_timestamp' => '20160705000000',
                'rev_minor_edit' => '1',
            ], [
                'rev_comment' => '',
                'rev_timestamp' => '20160701101205',
                'rev_minor_edit' => '0',
            ],
        ];
    }
}
