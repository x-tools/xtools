<?php
/**
 * This file contains only the TopEditsTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\Page;
use AppBundle\Model\Project;
use AppBundle\Model\TopEdits;
use AppBundle\Model\User;
use AppBundle\Repository\ProjectRepository;
use AppBundle\Repository\TopEditsRepository;
use Tests\AppBundle\TestAdapter;

/**
 * Tests of the TopEdits class.
 */
class TopEditsTest extends TestAdapter
{
    /** @var Project The project instance. */
    protected $project;

    /** @var ProjectRepository The project repo instance. */
    protected $projectRepo;

    /** @var TopEditsRepository The TopEdits repo instance. */
    protected $teRepo;

    /** @var User The user instance. */
    protected $user;

    /**
     * Set up class instances and mocks.
     */
    public function setUp(): void
    {
        $this->project = new Project('en.wikipedia.org');
        $this->projectRepo = $this->getMock(ProjectRepository::class);
        $this->projectRepo->method('getMetadata')
            ->willReturn(['namespaces' => [0 => 'Main', 3 => 'User_talk']]);
        $this->projectRepo->method('getOne')
            ->willReturn(['url' => 'https://en.wikipedia.org']);
        $this->project->setRepository($this->projectRepo);
        $this->user = new User('Test user');

        $client = static::createClient();
        $container = $client->getContainer();

        $this->teRepo = $this->getMock(TopEditsRepository::class);
        $this->teRepo->method('getContainer')
            ->willReturn($container);
    }

    /**
     * Test the basic functionality of TopEdits.
     */
    public function testBasic(): void
    {
        // Single namespace, with defaults.
        $te = new TopEdits($this->project, $this->user);
        static::assertEquals(0, $te->getNamespace());
        static::assertEquals(1000, $te->getLimit());

        // Single namespace, explicit configuration.
        $te2 = new TopEdits($this->project, $this->user, null, 5, 50);
        static::assertEquals(5, $te2->getNamespace());
        static::assertEquals(50, $te2->getLimit());

        // All namespaces, so limit set.
        $te3 = new TopEdits($this->project, $this->user, null, 'all');
        static::assertEquals('all', $te3->getNamespace());
        static::assertEquals(20, $te3->getLimit());

        // All namespaces, explicit limit.
        $te4 = new TopEdits($this->project, $this->user, null, 'all', 3);
        static::assertEquals('all', $te4->getNamespace());
        static::assertEquals(3, $te4->getLimit());

        $page = new Page($this->project, 'Test page');
        $te->setPage($page);
        static::assertEquals($page, $te->getPage());
    }

    /**
     * Getting top edited pages across all namespaces.
     */
    public function testTopEditsAllNamespaces(): void
    {
        $te = new TopEdits($this->project, $this->user, null, 'all', 2);
        $this->teRepo->expects($this->once())
            ->method('getTopEditsAllNamespaces')
            ->with($this->project, $this->user, 2)
            ->willReturn(array_merge(
                $this->topEditsNamespaceFactory()[0],
                $this->topEditsNamespaceFactory()[3]
            ));
        $this->teRepo->expects($this->once())
            ->method('getDisplayTitles')
            ->willReturn([
                'Foo_bar' => 'Foo bar',
                '101st_Airborne_Division' => '101st Airborne Division',
                'User_talk:Test_user' => 'User talk:Test user',
                'User_talk:Jimbo_Wales' => '<i>User talk:Jimbo Wales</i>',
            ]);
        $te->setRepository($this->teRepo);
        $te->prepareData();

        $result = $te->getTopEdits();
        static::assertEquals([0, 3], array_keys($result));
        static::assertEquals(2, count($result));
        static::assertEquals(2, count($result[0]));
        static::assertEquals(2, count($result[3]));
        static::assertEquals([
            'page_namespace' => '0',
            'page_title' => 'Foo_bar',
            'page_is_redirect' => '1',
            'count' => '24',
            'pa_class' => 'List',
            'displaytitle' => 'Foo bar',
            'page_title_ns' => 'Foo_bar',
        ], $result[0][0]);

        // Fetching again should use value of class property.
        // The $this->once() above will validate this.
        $result2 = $te->getTopEdits();
        static::assertEquals($result, $result2);
    }

    /**
     * Getting top edited pages within a single namespace.
     */
    public function testTopEditsNamespace(): void
    {
        $te = new TopEdits($this->project, $this->user, null, 3, 2);
        $this->teRepo->expects($this->once())
            ->method('getTopEditsNamespace')
            ->with($this->project, $this->user, 3, 2)
            ->willReturn($this->topEditsNamespaceFactory()[3]);
        $this->teRepo->expects($this->once())
            ->method('getDisplayTitles')
            ->willReturn([
                'User_talk:Test_user' => 'User talk:Test user',
                'User_talk:Jimbo_Wales' => '<i>User talk:Jimbo Wales</i>',
            ]);
        $te->setRepository($this->teRepo);
        $te->prepareData();

        $result = $te->getTopEdits();
        static::assertEquals([3], array_keys($result));
        static::assertEquals(1, count($result));
        static::assertEquals(2, count($result[3]));
        static::assertEquals([
            'page_namespace' => '3',
            'page_title' => 'Jimbo_Wales',
            'page_is_redirect' => '0',
            'count' => '1',
            'displaytitle' => '<i>User talk:Jimbo Wales</i>',
            'page_title_ns' => 'User_talk:Jimbo_Wales',
        ], $result[3][1]);
    }

    /**
     * Data for self::testTopEditsAllNamespaces() and self::testTopEditsNamespace().
     * @return array
     */
    private function topEditsNamespaceFactory(): array
    {
        return [
            0 => [
                [
                  'page_namespace' => '0',
                  'page_title' => 'Foo_bar',
                  'page_is_redirect' => '1',
                  'count' => '24',
                  'pa_class' => 'List',
                  'page_title_ns' => 'Foo_bar',
                ], [
                  'page_namespace' => '0',
                  'page_title' => '101st_Airborne_Division',
                  'page_is_redirect' => '0',
                  'count' => '18',
                  'pa_class' => 'C',
                  'page_title_ns' => '101st_Airborne_Division',
                ],
            ],
            3 => [
                [
                  'page_namespace' => '3',
                  'page_title' => 'Test_user',
                  'page_is_redirect' => '0',
                  'count' => '3',
                  'page_title_ns' => 'User_talk:Test_user',
                ], [
                  'page_namespace' => '3',
                  'page_title' => 'Jimbo_Wales',
                  'page_is_redirect' => '0',
                  'count' => '1',
                  'page_title_ns' => 'User_talk:Jimbo_Wales',
                ],
            ],
        ];
    }

    /**
     * Top edits to a single page.
     */
    public function testTopEditsPage(): void
    {
        $page = new Page($this->project, 'Test page');

        $te = new TopEdits($this->project, $this->user, $page);
        $this->teRepo->expects($this->once())
            ->method('getTopEditsPage')
            ->willReturn($this->topEditsPageFactory());
        $te->setRepository($this->teRepo);

        $te->prepareData();

        static::assertEquals(4, $te->getNumTopEdits());
        static::assertEquals(100, $te->getTotalAdded());
        static::assertEquals(-50, $te->getTotalRemoved());
        static::assertEquals(1, $te->getTotalMinor());
        static::assertEquals(1, $te->getTotalAutomated());
        static::assertEquals(2, $te->getTotalReverted());
        static::assertEquals(10, $te->getTopEdits()[1]->getId());
        static::assertEquals(22.5, $te->getAtbe());
    }

    /**
     * Test data for self::TopEditsPage().
     * @return array
     */
    private function topEditsPageFactory(): array
    {
        return [
            [
                'id' => 0,
                'timestamp' => '20170423000000',
                'minor' => 0,
                'length' => 100,
                'length_change' => 100,
                'reverted' => 0,
                'user_id' => 5,
                'username' => 'Test user',
                'comment' => 'Foo bar',
                'parent_comment' => null,
             ], [
                'id' => 10,
                'timestamp' => '20170313000000',
                'minor' => '1',
                'length' => 200,
                'length_change' => 50,
                'reverted' => 0,
                'user_id' => 5,
                'username' => 'Test user',
                'comment' => 'Weeee (using [[WP:AWB]])',
                'parent_comment' => 'Reverted edits by Test user ([[WP:HG]])',
             ], [
                'id' => 20,
                'timestamp' => '20170223000000',
                'minor' => 0,
                'length' => 500,
                'length_change' => -50,
                'reverted' => 0,
                'user_id' => 5,
                'username' => 'Test user',
                'comment' => 'Boomshakalaka',
                'parent_comment' => 'Just another innocent edit',
             ], [
                'id' => 30,
                'timestamp' => '20170123000000',
                'minor' => 0,
                'length' => 500,
                'length_change' => 100,
                'reverted' => 1,
                'user_id' => 5,
                'username' => 'Test user',
                'comment' => 'Best edit ever',
                'parent_comment' => 'I plead the Fifth',
             ],
        ];
    }
}
