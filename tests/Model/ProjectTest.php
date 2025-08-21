<?php

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Model\Page;
use App\Model\Project;
use App\Model\User;
use App\Repository\PageRepository;
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
                    'wikiName' => 'Test Wiki',
                    'mainpage' => 'Test Main Page',
                ],
            ]);
        $this->projectRepo->expects(static::once())
            ->method('getApiPath')
            ->willReturn('/w/api.php');

        $project = new Project('testWiki');
        $project->setRepository($this->projectRepo);
        static::assertEquals('test.example.org', $project->getDomain());
        static::assertEquals('test_wiki', $project->getDatabaseName());
        static::assertEquals('https://test.example.org/', $project->getUrl());
        static::assertEquals('en', $project->getLang());
        static::assertEquals('/test_w', $project->getScriptPath());
        static::assertEquals('/test_wiki/$1', $project->getArticlePath());
        static::assertEquals('https://test.example.org/w/api.php', $project->getApiUrl());
        static::assertEquals('Test Wiki (test.example.org)', $project->getTitle());
        static::assertEquals('Test Main Page', $project->getMainPage());
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
     * Each namespace has a language-independent canonical name.
     */
    public function testCanonicalNamespaces(): void
    {
        $projectRepo = $this->getProjectRepo();
        $projectRepo->expects(static::once())
            ->method('getMetadata')
            ->willReturn([
                'canonical_namespaces' => [0 => '', 1 => 'Talk', 104 => 'Page'],
            ]);
        $projectRepo->expects(static::once())
            ->method('getInstalledExtensions')
            ->willReturn(['ProofreadPage']);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        static::assertTrue($project->isPrpPage(104));

        // Tests that getMetadata was in fact called only once and cached afterwards
        static::assertEquals('', $project->getCanonicalNamespace(0));
    }

    /**
     * A project has a list of installed extensions
     */
    public function testExtensions(): void
    {
        $projectRepo = $this->getProjectRepo();
        $projectRepo->expects(static::once())
            ->method('getInstalledExtensions')
            ->willReturn(['NoThing', 'ProofreadPage']);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        static::assertTrue($project->hasProofreadPage());
        static::assertFalse($project->hasVisualEditor());
        static::assertFalse($project->hasPageTriage());
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
        static::assertEquals('example_wiki', $project->getCacheKey());
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
     * Projects can have varying temporary account config.
     */
    public function testTempAccounts(): void
    {
        $projectRepo = $this->getProjectRepo();
        $projectRepo->expects(static::once())
            ->method('getMetadata')
            ->willReturn([
                'tempAccountPatterns' => [
                    "*$1",
                    "~2$1",
                ],
            ]);
        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        static::assertTrue($project->hasTempAccounts());
        static::assertEquals(["*$1", "~2$1"], $project->getTempAccountPatterns());
    }

    /**
     * A user or a whole project can opt in to displaying restricted statistics.
     * @dataProvider optedInProvider
     * @param string[] $optedInProjects List of projects.
     * @param string $dbName The database name.
     * @param string $domain The domain name.
     * @param array|null $ident Identification information.
     * @param bool $globalExists
     * @param bool $hasOptedIn The result to check against.
     */
    public function testOptedIn(
        array $optedInProjects,
        string $dbName,
        string $domain,
        ?array $ident,
        bool $globalExists,
        bool $hasOptedIn
    ): void {
        $project = new Project($dbName);
        $globalProject = new Project('metawiki');

        /** @var ProjectRepository|MockObject $globalProjectRepo */
        $globalProjectRepo = $this->createMock(ProjectRepository::class);
        $globalProjectRepo->expects(static::any())
            ->method('pageHasContent')
            ->willReturn($globalExists);

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
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects(static::any())
            ->method('getXtoolsUserInfo')
            ->willReturn($ident);
        $user = new User($userRepo, 'TestUser');
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
            [$optedInProjects, 'project1', 'test.example.org', null, false, true],
            [$optedInProjects, 'project2', 'test2.example.org', null, false, false],
            [$optedInProjects, 'project3', 'test3.example.org', null, false, false],
            [$optedInProjects, 'project4', 'test4.example.org', [ 'username' => 'TestUser'], false, true],
            [$optedInProjects, 'project5', 'test5.example.org', null, true, true],
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
        $projectRepo->expects(static::exactly(2))->method('getMetadata');
        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        static::assertEquals(
            "https://test.example.org/wiki/Foobar",
            $project->getUrlForPage('Foobar')
        );
        $pageRepo = $this->createMock(PageRepository::class);
        $page = new Page($pageRepo, $project, 'Foobar');
        static::assertEquals(
            "https://test.example.org/wiki/Foobar",
            $project->getUrlForPage($page)
        );
    }
}
