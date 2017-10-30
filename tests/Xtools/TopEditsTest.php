<?php
/**
 * This file contains only the TopEditsTest class.
 */

namespace Tests\Xtools;

use PHPUnit_Framework_TestCase;
use Xtools\TopEdits;
use Xtools\TopEditsRepository;
use Xtools\User;
use Xtools\Project;
use Xtools\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests of the TopEdits class.
 */
class TopEditsTest extends PHPUnit_Framework_TestCase
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
     * Set up container, class instances and mocks.
     */
    public function setUp()
    {
        $this->project = new Project('TestProject');
        $this->projectRepo = $this->getMock(ProjectRepository::class);
        $this->projectRepo->method('getMetadata')
            ->willReturn(['namespaces' => [0 => 'Main', 3 => 'User_talk']]);
        $this->project->setRepository($this->projectRepo);
        $this->user = new User('Test user');

        $this->teRepo = $this->getMock(TopEditsRepository::class);
    }

    /**
     * Test the basic functionality of Edit.
     */
    public function testBasic()
    {
        // Single namespace, with defaults.
        $te = new TopEdits($this->project, $this->user);
        $this->assertEquals(0, $te->getNamespace());
        $this->assertEquals(100, $te->getLimit());

        // Single namespace, explicit configuration.
        $te2 = new TopEdits($this->project, $this->user, 5, 50);
        $this->assertEquals(5, $te2->getNamespace());
        $this->assertEquals(50, $te2->getLimit());

        // All namespaces, so limit set.
        $te3 = new TopEdits($this->project, $this->user, 'all');
        $this->assertEquals('all', $te3->getNamespace());
        $this->assertEquals(20, $te3->getLimit());

        // All namespaces, explicit limit.
        $te4 = new TopEdits($this->project, $this->user, 'all', 3);
        $this->assertEquals('all', $te4->getNamespace());
        $this->assertEquals(3, $te4->getLimit());
    }

    /**
     * Getting top edited pages across all namespaces.
     */
    public function testTopEditsAllNamespaces()
    {
        $te = new TopEdits($this->project, $this->user, 'all', 2);
        $this->teRepo->expects($this->once())
            ->method('getTopEditsAllNamespaces')
            ->with($this->project, $this->user, 2)
            ->willReturn(array_merge(
                $this->topEditsRepoFactory()[0],
                $this->topEditsRepoFactory()[3]
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

        $result = $te->getTopEdits();
        $this->assertEquals([0, 3], array_keys($result));
        $this->assertEquals(2, count($result));
        $this->assertEquals(2, count($result[0]));
        $this->assertEquals(2, count($result[3]));
        $this->assertEquals([
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
        $this->assertEquals($result, $result2);
    }

    /**
     * Getting top edited pages within a single namespace.
     */
    public function testTopEditsNamespace()
    {
        $te = new TopEdits($this->project, $this->user, 3, 2);
        $this->teRepo->expects($this->once())
            ->method('getTopEditsNamespace')
            ->with($this->project, $this->user, 3, 2)
            ->willReturn($this->topEditsRepoFactory()[3]);
        $this->teRepo->expects($this->once())
            ->method('getDisplayTitles')
            ->willReturn([
                'User_talk:Test_user' => 'User talk:Test user',
                'User_talk:Jimbo_Wales' => '<i>User talk:Jimbo Wales</i>',
            ]);
        $te->setRepository($this->teRepo);

        $result = $te->getTopEdits();
        $this->assertEquals([3], array_keys($result));
        $this->assertEquals(1, count($result));
        $this->assertEquals(2, count($result[3]));
        $this->assertEquals([
            'page_namespace' => '3',
            'page_title' => 'Jimbo_Wales',
            'page_is_redirect' => '0',
            'count' => '1',
            'displaytitle' => '<i>User talk:Jimbo Wales</i>',
            'page_title_ns' => 'User_talk:Jimbo_Wales',
        ], $result[3][1]);
    }

    /**
     * Data for self::testTopEdits().
     * @return string[]
     */
    public function topEditsRepoFactory()
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
}
