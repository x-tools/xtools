<?php
/**
 * This file contains only the ArticleInfoTest class.
 */

namespace Tests\Xtools;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Xtools\ArticleInfo;
use Xtools\Project;
use Xtools\Page;
use Xtools\PagesRepository;
use DateTime;
use Doctrine\DBAL\Driver\PDOStatement;

/**
 * Tests for ArticleInfo.
 */
class ArticleInfoTest extends WebTestCase
{
    /** @var Container The Symfony container. */
    protected $container;

    /** @var ArticleInfo The article info instance. */
    protected $articleInfo;

    /** @var Page The page instance. */
    protected $page;

    /** @var Project The project instance. */
    protected $project;

    /**
     * Set up shared mocks and class instances.
     */
    public function setUp()
    {
        $client = static::createClient();
        $this->container = $client->getContainer();
        // $this->projectRepo = $this->getMock(ProjectRepository::class);
        $this->project = new Project('TestProject');
        // $this->project->setRepository($this->projectRepo);
        $this->page = new Page($this->project, 'Test page');
        $this->articleInfo = new ArticleInfo($this->page, $this->container);
    }

    /**
     * Number of revisions
     */
    public function testNumRevisions()
    {
        $pagesRepo = $this->getMock(PagesRepository::class);
        $pagesRepo->expects($this->once())
            ->method('getNumRevisions')
            ->willReturn(10);
        $this->page->setRepository($pagesRepo);
        $this->assertEquals(10, $this->articleInfo->getNumRevisions());
        // Should be cached (will error out if repo's getNumRevisions is called again).
        $this->assertEquals(10, $this->articleInfo->getNumRevisions());
    }

    /**
     * Number of revisions processed, based on app.max_page_revisions
     * @dataProvider revisionsProcessedProvider
     */
    public function testRevisionsProcessed($numRevisions, $assertion)
    {
        $pagesRepo = $this->getMock(PagesRepository::class);
        $pagesRepo->method('getNumRevisions')->willReturn($numRevisions);
        $this->page->setRepository($pagesRepo);
        $this->assertEquals(
            $this->articleInfo->getNumRevisionsProcessed(),
            $assertion
        );
    }

    /**
     * Data for self::testRevisionsProcessed().
     * @return int[]
     */
    public function revisionsProcessedProvider()
    {
        return [
            [1000000, 50000],
            [10, 10],
        ];
    }

    public function testTooManyRevisions()
    {
        $pagesRepo = $this->getMock(PagesRepository::class);
        $pagesRepo->expects($this->once())
            ->method('getNumRevisions')
            ->willReturn(1000000);
        $this->page->setRepository($pagesRepo);
        $this->assertTrue($this->articleInfo->tooManyRevisions());
    }
}
