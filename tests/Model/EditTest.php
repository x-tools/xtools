<?php

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Model\Edit;
use App\Model\Page;
use App\Model\Project;
use App\Model\User;
use App\Repository\EditRepository;
use App\Repository\PageRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Tests\SessionHelper;
use App\Tests\TestAdapter;
use DateTime;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Tests of the Edit class.
 * @covers \App\Model\Edit
 */
class EditTest extends TestAdapter
{
    use ArraySubsetAsserts;
    use SessionHelper;

    protected ContainerInterface $localContainer;
    protected KernelBrowser $client;
    protected Page $page;
    protected PageRepository $pageRepo;
    protected Project $project;
    protected ProjectRepository $projectRepo;
    protected UserRepository $userRepo;

    /** @var string[] Basic attributes for edit factory. */
    protected array $editAttrs;

    /**
     * Set up container, class instances and mocks.
     */
    public function setUp(): void
    {
        $this->client = static::createClient();
        $this->createSession($this->client);
        $this->localContainer = $this->client->getContainer();
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
                'namespaces' => [
                    1 => 'Talk',
                ],
            ]);
        $this->project->setRepository($this->projectRepo);
        $this->pageRepo = $this->createMock(PageRepository::class);
        $this->pageRepo->method('getPageInfo')
            ->willReturn([
                'ns' => 0,
            ]);
        $this->page = new Page($this->pageRepo, $this->project, 'Test_page');

        $this->editAttrs = [
            'id' => '1',
            'timestamp' => '20170101100000',
            'minor' => '0',
            'length' => '12',
            'length_change' => '2',
            'username' => 'Testuser',
            'comment' => 'Test',
            'rev_sha1' => 'abcdef',
            'reverted' => 0,
        ];
    }

    /**
     * Test the basic functionality of Edit.
     */
    public function testBasic(): void
    {
        // Also tests that giving a DateTime works; other tests use the string variant from $this->editAttrs.
        $edit = $this->getEditFactory(['comment' => 'Test', 'timestamp' => new DateTime('20170101100000')]);
        static::assertEquals($this->project, $edit->getProject());
        static::assertInstanceOf(DateTime::class, $edit->getTimestamp());
        static::assertEquals($this->page, $edit->getPage());
        static::assertEquals('1483264800', $edit->getTimestamp()->getTimestamp());
        static::assertEquals(1, $edit->getId());
        static::assertFalse($edit->isMinor());
        static::assertEquals('abcdef', $edit->getSha());
        static::assertEquals('1', $edit->getCacheKey());
        static::assertFalse($edit->isReverted());
        // Test fallback for invalid timestamp
        $edit = $this->getEditFactory(['timestamp' => []]);
        static::assertEquals(new DateTime('1970-01-01T00:00:00Z'), $edit->getTimestamp());
    }

    /**
     * Using that static method.
     */
    public function testGetEditFromRevs(): void
    {
        $editRepo = $this->createMock(EditRepository::class);
        $editRepo->method('getAutoEditsHelper')
            ->willReturn($this->getAutomatedEditsHelper($this->client));
        $userRepo = $this->createMock(UserRepository::class);
        $edit = Edit::getEditsFromRevs(
            $this->pageRepo,
            $editRepo,
            $userRepo,
            $this->project,
            new User($userRepo, 'Foobar'),
            [array_merge($this->editAttrs, ['page_title' => 'Test', 'namespace' => 0])]
        );
        static::assertEquals(1, $edit[0]->getId());
    }

    /**
     * Wikified edit summary
     */
    public function testWikifiedComment(): void
    {
        $edit = $this->getEditFactory([
            'comment' => '<script>alert("XSS baby")</script> [[test page]]',
        ]);
        static::assertEquals(
            "&lt;script&gt;alert(\"XSS baby\")&lt;/script&gt; " .
                "<a target='_blank' href='https://en.wikipedia.org/wiki/Test_page'>test page</a>",
            $edit->getWikifiedSummary()
        );

        $edit = $this->getEditFactory([
            'comment' => 'https://example.org',
        ]);
        static::assertEquals(
            '<a target="_blank" href="https://example.org">https://example.org</a>',
            $edit->getWikifiedSummary()
        );

        $edit = $this->getEditFactory([
            'comment' => '/* Section */',
        ]);
        static::assertEquals(
            "<a target='_blank' href='https://en.wikipedia.org/wiki/Test_page#Section'>&rarr;</a>".
                "<em class='text-muted'>Section:</em>",
            $edit->getWikifiedSummary()
        );
    }

    /**
     * Make sure the right tool is detected
     */
    public function testTool(): void
    {
        $edit = $this->getEditFactory([
            'comment' => 'Level 2 warning re. [[Barack Obama]] ([[WP:HG|HG]]) (3.2.0)',
        ]);
        static::assertArraySubset(
            [ 'name' => 'Huggle' ],
            $edit->getTool()
        );
    }

    /**
     * Was the edit a revert, based on the edit summary?
     */
    public function testIsRevert(): void
    {
        $edit = $this->getEditFactory([
            'comment' => 'You should have reverted this edit using [[WP:HG|Huggle]]',
        ]);
        static::assertFalse($edit->isRevert());

        $edit->setReverted(true);
        static::assertTrue($edit->isReverted());

        $edit2 = $this->getEditFactory([
            'comment' => 'Reverted edits by Mogultalk (talk) ([[WP:HG|HG]]) (3.2.0)',
        ]);
        static::assertTrue($edit2->isRevert());
    }

    /**
     * Tests that given edit summary is properly asserted as a revert
     */
    public function testIsAutomated(): void
    {
        $edit = $this->getEditFactory([
            'comment' => 'You should have reverted this edit using [[WP:HG|Huggle]]',
        ]);
        static::assertFalse($edit->isAutomated());

        $edit2 = $this->getEditFactory([
            'comment' => 'Reverted edits by Mogultalk (talk) ([[WP:HG|HG]]) (3.2.0)',
        ]);
        static::assertTrue($edit2->isAutomated());
    }

    /**
     * Test some basic getters.
     */
    public function testGetters(): void
    {
        $edit = $this->getEditFactory(['tags' => json_encode(['A', 'B'])]);
        static::assertEquals('2017', $edit->getYear());
        static::assertEquals('01', $edit->getMonth());
        static::assertEquals(12, $edit->getLength());
        static::assertEquals(2, $edit->getSize());
        static::assertEquals(2, $edit->getLengthChange());
        static::assertEquals('Testuser', $edit->getUser()->getUsername());
        static::assertContains('A', $edit->getTags());
    }

    /**
     * URL to the diff.
     */
    public function testDiffUrl(): void
    {
        $edit = $this->getEditFactory();
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
        $edit = $this->getEditFactory();
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
        $edit = $this->getEditFactory();
        $project = $this->createMock(Project::class);
        static::assertFalse($edit->isAnon($project));

        $edit = $this->getEditFactory([
            'username' => '192.168.0.1',
        ]);
        static::assertTrue($edit->isAnon($project));
    }

    public function testGetForJson(): void
    {
        $pageRepo = $this->createMock(PageRepository::class);
        $pageRepo->method('getPageInfo')
            ->willReturn([
                'ns' => 1,
            ]);
        $this->page = new Page($pageRepo, $this->project, 'Talk:Test_page');
        $edit = $this->getEditFactory();
        static::assertEquals(
            [
                'project' => 'en.wikipedia.org',
                'username' => 'Testuser',
                'page_title' => 'Test page',
                'namespace' => 1,
                'rev_id' => 1,
                'timestamp' => '2017-01-01T10:00:00Z',
                'minor' => false,
                'length' => 12,
                'length_change' => 2,
                'comment' => 'Test',
                'reverted' => false,
            ],
            $edit->getForJson(true)
        );
    }

    public function testDeleted(): void
    {
        $this->editAttrs['rev_deleted'] = Edit::DELETED_USER;
        $edit = $this->getEditFactory();
        static::assertNull($edit->getUser());
        static::assertEquals(Edit::DELETED_USER, $edit->getDeleted());
        static::assertTrue($edit->deletedUser());
        static::assertFalse($edit->deletedSummary());
    }

    /**
     * @param array $attrs
     * @return Edit
     */
    private function getEditFactory(array $attrs = []): Edit
    {
        $editRepo = $this->createMock(EditRepository::class);
        $editRepo->method('getAutoEditsHelper')
            ->willReturn($this->getAutomatedEditsHelper($this->client));
        $userRepo = $this->createMock(UserRepository::class);
        return new Edit($editRepo, $userRepo, $this->page, array_merge($this->editAttrs, $attrs));
    }
}
