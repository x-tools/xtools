<?php

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Model\Project;
use App\Model\User;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for the Project class.
 * @covers \App\Model\Project
 */
class ProjectTest extends TestAdapter
{
    protected ProjectRepository $projectRepo;
    protected UserRepository $userRepo;

    public function setUp(): void
    {
        parent::setUp();
        $this->projectRepo = $this->getProjectRepo();
        $this->userRepo = $this->createMock(UserRepository::class);
    }

    /**
     * A project has its own domain name, database name, URL, script path, and article path.
     */
    public function testBasicMetadata(): void
    {
        $this->projectRepo->expects(static::once())
            ->method('getMetadata')
            ->willReturn([
                'general' => [
                    'articlePath' => '/test_wiki/$1',
                    'scriptPath' => '/test_w',
                ],
            ]);

        $project = new Project('testWiki');
        $project->setRepository($this->projectRepo);
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
            ->willReturn([
                'namespaces' => [0 => 'Main', 1 => 'Talk'],
            ]);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        static::assertCount(2, $project->getNamespaces());

        // Tests that getMetadata was in fact called only once and cached afterwards
        static::assertEquals('Main', $project->getNamespaces()[0]);
    }

    /**
     * XTools can be run in single-wiki mode, where there is only one project.
     */
    public function testSingleWiki(): void
    {
        $this->markTestSkipped('No single-wiki support, currently.');

        $this->projectRepo->setSingleBasicInfo([
            'url' => 'https://example.org/a-wiki/',
            'dbName' => 'example_wiki',
            'lang' => 'en',
        ]);
        $project = new Project('disregarded_wiki_name');
        $project->setRepository($this->projectRepo);
        static::assertEquals('example_wiki', $project->getDatabaseName());
        static::assertEquals('https://example.org/a-wiki/', $project->getUrl());
        static::assertEquals('en', $project->getLang());
    }

    /**
     * A project is considered to exist if it has at least a domain name.
     */
    public function testExists(): void
    {
        /** @var ProjectRepository|MockObject $projectRepo */
        $projectRepo = $this->createMock(ProjectRepository::class);
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

        /** @var ProjectRepository|MockObject $globalProjectRepo */
        $globalProjectRepo = $this->createMock(ProjectRepository::class);

        $this->projectRepo->expects(static::once())
            ->method('optedIn')
            ->willReturn($optedInProjects);
        $this->projectRepo->expects(static::once())
            ->method('getOne')
            ->willReturn([
                'dbName' => $dbName,
                'domain' => "https://$domain.org",
            ]);
        $this->projectRepo->method('getGlobalProject')
            ->willReturn($globalProject);
        $this->projectRepo->method('pageHasContent')
            ->with($project, 2, 'TestUser/EditCounterOptIn.js')
            ->willReturn($hasOptedIn);
        $project->setRepository($this->projectRepo);
        $globalProject->setRepository($globalProjectRepo);

        // Check that the user has opted in or not.
        $user = new User($this->userRepo, 'TestUser');
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
                ['user_name' => 'Bob', 'user_group' => 'sysop'],
                ['user_name' => 'Bob', 'user_group' => 'checkuser'],
                ['user_name' => 'Julie', 'user_group' => 'sysop'],
                ['user_name' => 'Herald', 'user_group' => 'suppress'],
                ['user_name' => 'Isosceles', 'user_group' => 'suppress'],
                ['user_name' => 'Isosceles', 'user_group' => 'sysop'],
            ]);
        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        static::assertEquals(
            [
                'Bob' => ['sysop', 'checkuser'],
                'Julie' => ['sysop'],
                'Herald' => ['suppress'],
                'Isosceles' => ['suppress', 'sysop'],
            ],
            $project->getUsersInGroups(['sysop', 'checkuser'], [])
        );
    }

    public function testGetUrlForPage(): void
    {
        $projectRepo = $this->getProjectRepo();
        $projectRepo->expects(static::once())->method('getMetadata');
        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        static::assertEquals(
            "https://test.example.org/wiki/Foobar",
            $project->getUrlForPage('Foobar')
        );
    }
}
