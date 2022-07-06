<?php
/**
 * This file contains only the EditTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\Edit;
use AppBundle\Model\Page;
use AppBundle\Model\Project;
use AppBundle\Repository\PageRepository;
use AppBundle\Repository\ProjectRepository;
use DateTime;
use Symfony\Component\DependencyInjection\Container;
use Tests\AppBundle\TestAdapter;

/**
 * Tests of the Edit class.
 */
class EditTest extends TestAdapter
{
    /** @var Container The Symfony container ($localContainer because we can't override self::$container). */
    protected $localContainer;

    /** @var Project The project instance. */
    protected $project;

    /** @var ProjectRepository The project repo instance. */
    protected $projectRepo;

    /** @var Page The page instance. */
    protected $page;

    /** @var string[] Basic attributes for edit factory. */
    protected $editAttrs;

    /**
     * Set up container, class instances and mocks.
     */
    public function setUp(): void
    {
        $client = static::createClient();
        $this->localContainer = $client->getContainer();
        $this->project = new Project('en.wikipedia.org');
        $this->projectRepo = $this->createMock(ProjectRepository::class);
        $this->projectRepo->method('getOne')
            ->willReturn([
                'url' => 'https://en.wikipedia.org',
                'dbName' => 'enwiki',
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
    public function testBasic(): void
    {
        $edit = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => 'Test',
        ]));
        static::assertEquals($this->project, $edit->getProject());
        static::assertInstanceOf(DateTime::class, $edit->getTimestamp());
        static::assertEquals($this->page, $edit->getPage());
        static::assertEquals('1483264800', $edit->getTimestamp()->getTimestamp());
        static::assertEquals(1, $edit->getId());
        static::assertFalse($edit->isMinor());
    }

    /**
     * Wikified edit summary
     */
    public function testWikifiedComment(): void
    {
        $edit = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => '<script>alert("XSS baby")</script> [[test page]]',
        ]));
        static::assertEquals(
            "&lt;script&gt;alert(\"XSS baby\")&lt;/script&gt; " .
                "<a target='_blank' href='https://en.wikipedia.org/wiki/Test_page'>test page</a>",
            $edit->getWikifiedSummary()
        );

        $edit = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => 'https://google.com',
        ]));
        static::assertEquals(
            '<a target="_blank" href="https://google.com">https://google.com</a>',
            $edit->getWikifiedSummary()
        );
    }

    /**
     * Make sure the right tool is detected
     */
    public function testTool(): void
    {
        $edit = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => 'Level 2 warning re. [[Barack Obama]] ([[WP:HG|HG]]) (3.2.0)',
        ]));

        static::assertArraySubset(
            [
                'name' => 'Huggle',
            ],
            $edit->getTool($this->localContainer)
        );
    }

    /**
     * Was the edit a revert, based on the edit summary?
     */
    public function testIsRevert(): void
    {
        $edit = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => 'You should have reverted this edit using [[WP:HG|Huggle]]',
        ]));

        static::assertFalse($edit->isRevert($this->localContainer));

        $edit2 = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => 'Reverted edits by Mogultalk (talk) ([[WP:HG|HG]]) (3.2.0)',
        ]));

        static::assertTrue($edit2->isRevert($this->localContainer));
    }

    /**
     * Tests that given edit summary is properly asserted as a revert
     */
    public function testIsAutomated(): void
    {
        $edit = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => 'You should have reverted this edit using [[WP:HG|Huggle]]',
        ]));

        static::assertFalse($edit->isAutomated($this->localContainer));

        $edit2 = new Edit($this->page, array_merge($this->editAttrs, [
            'comment' => 'Reverted edits by Mogultalk (talk) ([[WP:HG|HG]]) (3.2.0)',
        ]));

        static::assertTrue($edit2->isAutomated($this->localContainer));
    }

    /**
     * Test some basic getters.
     */
    public function testGetters(): void
    {
        $edit = new Edit($this->page, $this->editAttrs);
        static::assertEquals('2017', $edit->getYear());
        static::assertEquals('01', $edit->getMonth());
        static::assertEquals(12, $edit->getLength());
        static::assertEquals(2, $edit->getSize());
        static::assertEquals(2, $edit->getLengthChange());
        static::assertEquals('Testuser', $edit->getUser()->getUsername());
    }

    /**
     * URL to the diff.
     */
    public function testDiffUrl(): void
    {
        $edit = new Edit($this->page, $this->editAttrs);
        static::assertEquals(
            'https://en.wikipedia.org/wiki/Special:Diff/1',
            $edit->getDiffUrl()
        );
    }

    /**
     * URL to the diff.
     */
    public function testPermaUrl(): void
    {
        $edit = new Edit($this->page, $this->editAttrs);
        static::assertEquals(
            'https://en.wikipedia.org/wiki/Special:PermaLink/1',
            $edit->getPermaUrl()
        );
    }

    /**
     * Was the edit made by a logged out user?
     */
    public function testIsAnon(): void
    {
        // Edit made by User:Testuser
        $edit = new Edit($this->page, $this->editAttrs);
        static::assertFalse($edit->isAnon());

        $edit = new Edit($this->page, array_merge($this->editAttrs, [
            'username' => '192.168.0.1',
        ]));
        static::assertTrue($edit->isAnon());
    }

    /**
     * @covers Edit::getForJson()
     */
    public function testGetForJson(): void
    {
        $pageRepo = $this->createMock(PageRepository::class);
        $this->page->setRepository($pageRepo);
        $edit = new Edit($this->page, array_merge($this->editAttrs));

        static::assertEquals(
            [
                'username' => 'Testuser',
                'page_title' => 'Test_page',
                'page_namespace' => $this->page->getNamespace(),
                'rev_id' => 1,
                'timestamp' => '2017-01-01T10:00:00',
                'minor' => false,
                'length' => 12,
                'length_change' => 2,
                'comment' => 'Test',
            ],
            $edit->getForJson(true)
        );
    }
}
