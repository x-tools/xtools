<?php
/**
 * This file contains only the ProjectTest class.
 */

namespace Tests\Xtools;

use PHPUnit_Framework_TestCase;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;

/**
 * Tests for the Project class.
 */
class ProjectTest extends PHPUnit_Framework_TestCase
{

    /**
     * A project has its own domain name, database name, URL, script path, and article path.
     */
    public function testBasicMetadata()
    {
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getOne')
            ->willReturn(['url' => 'https://test.example.org', 'dbname' => 'test_wiki']);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        $this->assertEquals('test.example.org', $project->getDomain());
        $this->assertEquals('test_wiki', $project->getDatabaseName());
        $this->assertEquals('https://test.example.org/', $project->getUrl());
        $this->assertEquals('/w', $project->getScriptPath());
        $this->assertEquals('/wiki/$1', $project->getArticlePath());
        $this->assertTrue($project->exists());
    }

    /**
     * Make sure there's an error when trying to get project metadata without a Repository.
     * @expectedException \Exception
     * @expectedExceptionMessage Repository for Xtools\Project must be set before using.
     */
    public function testNoRepository()
    {
        $project2 = new Project('test.example.wiki');
        $project2->getTitle();
    }

    /**
     * A project has a set of namespaces, comprising integer IDs and string titles.
     */
    public function testNamespaces()
    {
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getMetadata')
            ->willReturn(['namespaces' => [0 => 'Article', 1 => 'Article_talk']]);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        $this->assertCount(2, $project->getNamespaces());
    }

    /**
     * XTools can be run in single-wiki mode, where there is only one project.
     */
    public function testSingleWiki()
    {
        $projectRepo = new ProjectRepository();
        $projectRepo->setSingleMetadata([
            'url' => 'https://example.org/a-wiki/',
            'dbname' => 'example_wiki',
        ]);
        $project = new Project('disregarded_wiki_name');
        $project->setRepository($projectRepo);
        $this->assertEquals('example_wiki', $project->getDatabaseName());
        $this->assertEquals('https://example.org/a-wiki/', $project->getUrl());
    }

    /**
     * A project is considered to exist if it has at least a domain name.
     */
    public function testExists()
    {
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getOne')
            ->willReturn([]);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        $this->assertFalse($project->exists());
    }

    /**
     * A user or a whole project can opt in to displaying restricted statistics.
     * @dataProvider optedInProvider
     * @param string[] $optedInProjects List of projects.
     * @param string $dbname The database name.
     * @param bool $hasOptedIn The result to check against.
     */
    public function testOptedIn($optedInProjects, $dbname, $hasOptedIn)
    {
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('optedIn')
            ->willReturn($optedInProjects);
        $projectRepo->expects($this->once())
            ->method('getOne')
            ->willReturn(['dbname' => $dbname]);

        $project = new Project($dbname);
        $project->setRepository($projectRepo);

        // Check that the user has opted in or not.
        $user = new User('TestUser');
        $this->assertEquals($hasOptedIn, $project->userHasOptedIn($user));
    }

    /**
     * Whether page assessments are supported for the project
     */
    public function testHasPageAssessments()
    {
        $projectRepo = $this->getMock(ProjectRepository::class);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);

        // Unconfigured project
        $this->assertEquals(false, $project->hasPageAssessments());

        // Mock and re-test
        $projectRepo
            ->method('getAssessmentsConfig')
            ->with()
            ->willReturn([
                [
                    'wikiproject_prefix' => 'Wikipedia:WikiProject_',
                ],
            ]);

        $this->assertEquals(true, $project->hasPageAssessments());
    }

    /**
     * Data for self::testOptedIn().
     * @return array
     */
    public function optedInProvider()
    {
        $optedInProjects = ['project1'];
        return [
            [$optedInProjects, 'project1', true],
            [$optedInProjects, 'project2', false],
            [$optedInProjects, 'project3', false],
        ];
    }
}
