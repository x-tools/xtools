<?php
/**
 * This file contains only the ProjectTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\Project;
use AppBundle\Model\User;
use AppBundle\Repository\ProjectRepository;
use Tests\AppBundle\TestAdapter;

/**
 * Tests for the Project class.
 */
class ProjectTest extends TestAdapter
{
    /**
     * A project has its own domain name, database name, URL, script path, and article path.
     */
    public function testBasicMetadata(): void
    {
        /** @var ProjectRepository|\PHPUnit_Framework_MockObject_MockObject $projectRepo */
        $projectRepo = $this->getProjectRepo();
        $projectRepo->expects(static::once())
            ->method('getMetadata')
            ->willReturn([
                'general' => [
                    'articlePath' => '/test_wiki/$1',
                    'scriptPath' => '/test_w',
                ],
            ]);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        static::assertEquals('test.example.org', $project->getDomain());
        static::assertEquals('test_wiki', $project->getDatabaseName());
        static::assertEquals('https://test.example.org/', $project->getUrl());
        static::assertEquals('en', $project->getLang());
        static::assertEquals('/test_w', $project->getScriptPath());
        static::assertEquals('/test_wiki/$1', $project->getArticlePath());
        static::assertTrue($project->exists());
    }

    /**
     * A project has a set of namespaces, comprising integer IDs and string titles.
     */
    public function testNamespaces(): void
    {
        $projectRepo = $this->getProjectRepo();
        $projectRepo->expects(static::once())
            ->method('getMetadata')
            ->willReturn(['namespaces' => [0 => 'Main', 1 => 'Article_talk']]);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        static::assertCount(2, $project->getNamespaces());

        // Tests that getMetadata was in fact called only once and cached afterwards
        static::assertEquals($project->getNamespaces()[0], 'Main');
    }

    /**
     * XTools can be run in single-wiki mode, where there is only one project.
     */
    public function testSingleWiki(): void
    {
        $projectRepo = new ProjectRepository();
        $projectRepo->setSingleBasicInfo([
            'url' => 'https://example.org/a-wiki/',
            'dbName' => 'example_wiki',
            'lang' => 'en',
        ]);
        $project = new Project('disregarded_wiki_name');
        $project->setRepository($projectRepo);
        static::assertEquals('example_wiki', $project->getDatabaseName());
        static::assertEquals('https://example.org/a-wiki/', $project->getUrl());
        static::assertEquals('en', $project->getLang());
    }

    /**
     * A project is considered to exist if it has at least a domain name.
     */
    public function testExists(): void
    {
        /** @var ProjectRepository|\PHPUnit_Framework_MockObject_MockObject $projectRepo */
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects(static::once())
            ->method('getOne')
            ->willReturn([]);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        static::assertFalse($project->exists());
    }

    /**
     * Get the relative URL to the index.php script.
     */
    public function testGetScript(): void
    {
        $projectRepo = $this->getProjectRepo();
        $projectRepo->expects(static::once())
            ->method('getMetadata')
            ->willReturn([
                'general' => [
                    'script' => '/w/index.php',
                ],
            ]);
        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        static::assertEquals('/w/index.php', $project->getScript());

        // No script from API.
        $projectRepo2 = $this->getProjectRepo();
        $projectRepo2->expects(static::once())
            ->method('getMetadata')
            ->willReturn([
                'general' => [
                    'scriptPath' => '/w',
                ],
            ]);
        $project2 = new Project('testWiki');
        $project2->setRepository($projectRepo2);
        static::assertEquals('/w/index.php', $project2->getScript());
    }

    /**
     * A user or a whole project can opt in to displaying restricted statistics.
     * @dataProvider optedInProvider
     * @param string[] $optedInProjects List of projects.
     * @param string $dbName The database name.
     * @param string $domain The domain name.
     * @param bool $hasOptedIn The result to check against.
     */
    public function testOptedIn(array $optedInProjects, string $dbName, string $domain, bool $hasOptedIn): void
    {
        $project = new Project($dbName);
        $globalProject = new Project('metawiki');

        /** @var ProjectRepository|\PHPUnit_Framework_MockObject_MockObject $projectRepo */
        $projectRepo = $this->getMock(ProjectRepository::class);

        /** @var ProjectRepository|\PHPUnit_Framework_MockObject_MockObject $projectRepo */
        $globalProjectRepo = $this->getMock(ProjectRepository::class);

        $projectRepo->expects(static::once())
            ->method('optedIn')
            ->willReturn($optedInProjects);
        $projectRepo->expects(static::once())
            ->method('getOne')
            ->willReturn([
                'dbName' => $dbName,
                'domain' => "https://$domain.org",
            ]);
        $projectRepo->method('getGlobalProject')
            ->willReturn($globalProject);
        $projectRepo->method('pageHasContent')
            ->with($project, 2, 'TestUser/EditCounterOptIn.js')
            ->willReturn(false);
        $project->setRepository($projectRepo);
        $globalProject->setRepository($globalProjectRepo);

        // Check that the user has opted in or not.
        $user = new User('TestUser');
        static::assertEquals($hasOptedIn, $project->userHasOptedIn($user));
    }

    /**
     * Data for self::testOptedIn().
     * @return array
     */
    public function optedInProvider(): array
    {
        $optedInProjects = ['project1'];
        return [
            [$optedInProjects, 'project1', 'test.example.org', true],
            [$optedInProjects, 'project2', 'test2.example.org', false],
            [$optedInProjects, 'project3', 'test3.example.org', false],
        ];
    }

    /**
     * Normalized, quoted table name.
     */
    public function testTableName(): void
    {
        $projectRepo = $this->getProjectRepo();
        $projectRepo->expects(static::once())
            ->method('getTableName')
            ->willReturn('testwiki_p.revision_userindex');
        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        static::assertEquals(
            'testwiki_p.revision_userindex',
            $project->getTableName('testwiki', 'revision')
        );
    }

    /**
     * Getting a list of the users within specific user groups.
     */
    public function testUsersInGroups(): void
    {
        $projectRepo = $this->getProjectRepo();
        $projectRepo->expects(static::once())
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
        static::assertEquals(
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
