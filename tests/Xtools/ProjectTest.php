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
            ->willReturn([
                'url' => 'https://test.example.org',
                'dbName' => 'test_wiki',
                'lang' => 'en',
            ]);
        $projectRepo->expects($this->once())
            ->method('getMetadata')
            ->willReturn([
                'general' => [
                    'articlePath' => '/test_wiki/$1',
                    'scriptPath' => '/test_w',
                ],
            ]);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        $this->assertEquals('test.example.org', $project->getDomain());
        $this->assertEquals('test_wiki', $project->getDatabaseName());
        $this->assertEquals('https://test.example.org/', $project->getUrl());
        $this->assertEquals('en', $project->getLang());
        $this->assertEquals('/test_w', $project->getScriptPath());
        $this->assertEquals('/test_wiki/$1', $project->getArticlePath());
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
            ->willReturn(['namespaces' => [0 => 'Main', 1 => 'Article_talk']]);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        $this->assertCount(2, $project->getNamespaces());

        // Tests that getMetadata was in fact called only once and cached afterwards
        $this->assertEquals($project->getNamespaces()[0], 'Main');
    }

    /**
     * XTools can be run in single-wiki mode, where there is only one project.
     */
    public function testSingleWiki()
    {
        $projectRepo = new ProjectRepository();
        $projectRepo->setSingleBasicInfo([
            'url' => 'https://example.org/a-wiki/',
            'dbName' => 'example_wiki',
            'lang' => 'en',
        ]);
        $project = new Project('disregarded_wiki_name');
        $project->setRepository($projectRepo);
        $this->assertEquals('example_wiki', $project->getDatabaseName());
        $this->assertEquals('https://example.org/a-wiki/', $project->getUrl());
        $this->assertEquals('en', $project->getLang());
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
     * Get the relative URL to the index.php script.
     */
    public function testGetScript()
    {
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getMetadata')
            ->willReturn([
                'general' => [
                    'script' => '/w/index.php'
                ],
            ]);
        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        $this->assertEquals('/w/index.php', $project->getScript());

        // No script from API.
        $projectRepo2 = $this->getMock(ProjectRepository::class);
        $projectRepo2->expects($this->once())
            ->method('getMetadata')
            ->willReturn([
                'general' => [
                    'scriptPath' => '/w'
                ],
            ]);
        $project2 = new Project('testWiki');
        $project2->setRepository($projectRepo2);
        $this->assertEquals('/w/index.php', $project2->getScript());
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
        $project = new Project($dbname);

        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('optedIn')
            ->willReturn($optedInProjects);
        $projectRepo->expects($this->once())
            ->method('getOne')
            ->willReturn(['dbName' => $dbname]);
        $projectRepo
            ->method('pageHasContent')
            ->with($project, 2, 'TestUser/EditCounterOptIn.js')
            ->willReturn(false);

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
     * Get the URL to the assessment badge on Commons.
     */
    public function testAssessmentBadgeURL()
    {
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->exactly(2))
            ->method('getAssessmentsConfig')
            ->willReturn([
                'class' => [
                    'C' => [
                        'badge' => 'e/e6/Symbol_c_class.svg',
                    ],
                    'Unknown' => [
                        'badge' => 'e/e0/Symbol_question.svg',
                    ],
                ],
            ]);
        $project = new Project('testWiki');
        $project->setRepository($projectRepo);

        $this->assertEquals(
            'https://upload.wikimedia.org/wikipedia/commons/e/e6/Symbol_c_class.svg',
            $project->getAssessmentBadgeURL('C')
        );

        // Unknown class.
        $this->assertEquals(
            'https://upload.wikimedia.org/wikipedia/commons/e/e0/Symbol_question.svg',
            $project->getAssessmentBadgeURL('D')
        );
    }

    /**
     * Normalized, quoted table name.
     */
    public function testTableName()
    {
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getTableName')
            ->willReturn('testwiki_p.revision_userindex');
        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        $this->assertEquals(
            'testwiki_p.revision_userindex',
            $project->getTableName('testwiki', 'revision')
        );
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

    /**
     * Getting a list of the users within specific user groups.
     */
    public function testUsersInGroups()
    {
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getUsersInGroups')
            ->willReturn([
                ['user_name' => 'Bob', 'ug_group' => 'sysop'],
                ['user_name' => 'Bob', 'ug_group' => 'checkuser'],
                ['user_name' => 'Julie', 'ug_group' => 'sysop'],
                ['user_name' => 'Herald', 'ug_group' => 'oversight'],
                ['user_name' => 'Isosceles', 'ug_group' => 'oversight'],
                ['user_name' => 'Isosceles', 'ug_group' => 'sysop'],
            ]);
        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        $this->assertEquals(
            [
                'Bob' => ['sysop', 'checkuser'],
                'Julie' => ['sysop'],
                'Herald' => ['oversight'],
                'Isosceles' => ['oversight', 'sysop'],
            ],
            $project->getUsersInGroups(['sysop', 'oversight'])
        );
    }
}
