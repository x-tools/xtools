<?php
/**
 * This file contains only the EditTest class.
 */

namespace Tests\Xtools;

use DateTime;
use Xtools\Edit;
use Xtools\Page;
use Xtools\Project;
use Xtools\ProjectRepository;
use Symfony\Component\DependencyInjection\Container;
use AppBundle\Helper\AutomatedEditsHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests of the Edit class.
 */
class EditTest extends WebTestCase
{
    /** @var Container The DI container. */
    protected $container;

    /** @var Project The project instance. */
    protected $project;

    /** @var ProjectRepository The project repo instance. */
    protected $projectRepo;

    /** @var Page The page instance. */
    protected $page;

    /** @var string[] Basic attritubes for edit factory. */
    protected $editAttrs;

    /**
     * Set up container, class instances and mocks.
     */
    public function setUp()
    {
        $client = static::createClient();
        $this->container = $client->getContainer();

        $this->project = new Project('TestProject');
        $this->projectRepo = $this->getMock(ProjectRepository::class);
        $this->projectRepo->method('getOne')
            ->willReturn([
                'url' => 'https://test.example.org',
                'dbName' => 'test_wiki',
                'lang' => 'en',
            ]);
        $this->projectRepo->method('getMetadata')
            ->willReturn([
                'general' => [
                    'articlePath' => '/wiki/$1',
                ],
            ]);
        $this->project->setRepository($this->projectRepo);
        $this->page = new Page($this->project, 'Test_page');

        $this->editAttrs = [
            'id' => '1',
            'timestamp' => '20170101100000',
            'minor' => '0',
            'length' => '12',
            'length_change' => '2',
            'username' => 'Testuser',
            'comment' => 'Test',
        ];
    }

    /**
     * Test the basic functionality of Edit.
     */
    public function testBasic()
    {
        $edit = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => 'Test',
        ]));
        $this->assertEquals($this->project, $edit->getProject());
        $this->assertInstanceOf(DateTime::class, $edit->getTimestamp());
        $this->assertEquals($this->page, $edit->getPage());
        $this->assertEquals('1483264800', $edit->getTimestamp()->getTimestamp());
        $this->assertEquals(1, $edit->getId());
        $this->assertFalse($edit->isMinor());
    }

    /**
     * Wikified edit summary
     */
    public function testWikifiedComment()
    {
        $edit = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => '<script>alert("XSS baby")</script> [[test page]]',
        ]));

        $this->assertEquals(
            "&lt;script&gt;alert(\"XSS baby\")&lt;/script&gt; " .
                "<a target='_blank' href='https://test.example.org/wiki/Test_page'>test page</a>",
            $edit->getWikifiedSummary()
        );
    }

    /**
     * Make sure the right tool is detected
     */
    public function testTool()
    {
        $edit = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => 'Level 2 warning re. [[Barack Obama]] ([[WP:HG|HG]]) (3.2.0)',
        ]));

        $this->assertArraySubset(
            [
                'name' => 'Huggle',
            ],
            $edit->getTool($this->container)
        );
    }

    /**
     * Was the edit a revert, based on the edit summary?
     */
    public function testIsRevert()
    {
        $edit = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => 'You should have reverted this edit using [[WP:HG|Huggle]]',
        ]));

        $this->assertFalse($edit->isRevert($this->container));

        $edit2 = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => 'Reverted edits by Mogultalk (talk) ([[WP:HG|HG]]) (3.2.0)',
        ]));

        $this->assertTrue($edit2->isRevert($this->container));
    }

    /**
     * Tests that given edit summary is properly asserted as a revert
     */
    public function testIsAutomated()
    {
        $edit = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => 'You should have reverted this edit using [[WP:HG|Huggle]]',
        ]));

        $this->assertFalse($edit->isAutomated($this->container));

        $edit2 = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => 'Reverted edits by Mogultalk (talk) ([[WP:HG|HG]]) (3.2.0)',
        ]));

        $this->assertTrue($edit2->isAutomated($this->container));
    }

    /**
     * Test some basic getters.
     */
    public function testGetters()
    {
        $edit = new Edit($this->page, $this->editAttrs);
        $this->assertEquals('2017', $edit->getYear());
        $this->assertEquals('1', $edit->getMonth());
        $this->assertEquals(12, $edit->getLength());
        $this->assertEquals(2, $edit->getSize());
        $this->assertEquals(2, $edit->getLengthChange());
        $this->assertEquals('Testuser', $edit->getUser()->getUsername());
    }

    /**
     * URL to the diff.
     */
    public function testDiffUrl()
    {
        $edit = new Edit($this->page, $this->editAttrs);
        $this->assertEquals(
            'https://test.example.org/wiki/Special:Diff/1',
            $edit->getDiffUrl()
        );
    }

    /**
     * URL to the diff.
     */
    public function testPermaUrl()
    {
        $edit = new Edit($this->page, $this->editAttrs);
        $this->assertEquals(
            'https://test.example.org/wiki/Special:PermaLink/1',
            $edit->getPermaUrl()
        );
    }

    /**
     * Was the edit made by a logged out user?
     */
    public function testIsAnon()
    {
        // Edit made by User:Testuser
        $edit = new Edit($this->page, $this->editAttrs);
        $this->assertFalse($edit->isAnon());

        $edit = new Edit($this->page, array_merge($this->editAttrs, [
            'username' => '192.168.0.1'
        ]));
        $this->assertTrue($edit->isAnon());
    }
}
