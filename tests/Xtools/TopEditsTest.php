<?php
/**
 * This file contains only the TopEdits class.
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
 * Tests of the Edit class.
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
     * Main getTopEdits method.
     */
    public function testTopEdits()
    {
        $te = new TopEdits($this->project, $this->user, 'all', 2);
        $this->teRepo->expects($this->exactly(2))
            ->method('getTopEdits')
            ->withConsecutive(
                [$this->project, $this->user, 0, 2],
                [$this->project, $this->user, 3, 2]
            )
            ->willReturnOnConsecutiveCalls(
                $this->topEditsRepoFactory()[0],
                $this->topEditsRepoFactory()[3]
            );
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
            'displaytitle' => null,
            'page_title_ns' => 'Foo_bar',
        ], $result[0][0]);
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
                  'displaytitle' => 'Foo bar',
                  'page_title_ns' => 'Foo_bar',
                ], [
                  'page_namespace' => '0',
                  'page_title' => '101st_Airborne_Division',
                  'page_is_redirect' => '0',
                  'count' => '18',
                  'pa_class' => 'C',
                  'displaytitle' => '101st Airborne Division',
                  'page_title_ns' => '101st_Airborne_Division',
                ],
            ],
            3 => [
                [
                  'page_namespace' => '3',
                  'page_title' => 'Test_user_1',
                  'page_is_redirect' => '0',
                  'count' => '3',
                  'displaytitle' => 'User talk:Test user 1',
                  'page_title_ns' => 'User talk:Test_user_1',
                ], [
                  'page_namespace' => '3',
                  'page_title' => 'Jimbo_Wales',
                  'page_is_redirect' => '0',
                  'count' => '1',
                  'displaytitle' => '<i>User talk:Jimbo Wales</i>',
                  'page_title_ns' => 'User talk:Jimbo_Wales',
                ],
            ],
        ];
    }
}
