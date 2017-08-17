<?php
/**
 * This file contains only the PageTest class.
 */

namespace Tests\Xtools;

// use PHPUnit_Framework_TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Xtools\Page;
use Xtools\PagesRepository;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;

/**
 * Tests for the Page class.
 */
class PageTest extends KernelTestCase
{
    /** @var Container The Symfony container. */
    protected $container;

    /**
     * A page has a title and an HTML display title.
     */
    public function testTitles()
    {
        $project = new Project('TestProject');
        $pageRepo = $this->getMock(PagesRepository::class, ['getPageInfo']);
        $data = [
            [$project, 'Test_Page_1', ['title' => 'Test_Page_1']],
            [$project, 'Test_Page_2', ['title' => 'Test_Page_2', 'displaytitle' => '<em>Test</em> page 2']],
        ];
        $pageRepo->method('getPageInfo')->will($this->returnValueMap($data));

        // Page with no display title.
        $page = new Page($project, 'Test_Page_1');
        $page->setRepository($pageRepo);
        $this->assertEquals('Test_Page_1', $page->getTitle());
        $this->assertEquals('Test_Page_1', $page->getDisplayTitle());

        // Page with a display title.
        $page = new Page($project, 'Test_Page_2');
        $page->setRepository($pageRepo);
        $this->assertEquals('Test_Page_2', $page->getTitle());
        $this->assertEquals('<em>Test</em> page 2', $page->getDisplayTitle());
    }

    /**
     * A page either exists or doesn't.
     */
    public function testExists()
    {
        $pageRepo = $this->getMock(PagesRepository::class, ['getPageInfo']);
        $project = new Project('TestProject');
        // Mock data (last element of each array is the return value).
        $data = [
            [$project, 'Existing_page', []],
            [$project, 'Missing_page', ['missing' => '']],
        ];
        $pageRepo //->expects($this->exactly(2))
            ->method('getPageInfo')
            ->will($this->returnValueMap($data));

        // Existing page.
        $page1 = new Page($project, 'Existing_page');
        $page1->setRepository($pageRepo);
        $this->assertTrue($page1->exists());

        // Missing page.
        $page2 = new Page($project, 'Missing_page');
        $page2->setRepository($pageRepo);
        $this->assertFalse($page2->exists());
    }

    /**
     * Test basic getters
     */
    public function testBasicGetters()
    {
        $project = $this->getMock(Project::class, ['getNamespaces'], ['TestProject']);
        $project->method('getNamespaces')
            ->willReturn([
                '',
                'Talk',
                'User',
            ]);

        $pageRepo = $this->getMock(PagesRepository::class, ['getPageInfo']);
        $pageRepo->expects($this->once())
            ->method('getPageInfo')
            ->willReturn([
                'pageid' => '42',
                'fullurl' => 'https://example.org/User:Test',
                'watchers' => 5000,
                'ns' => 2,
                'length' => 300,
                'pageprops' => [
                    'wikibase_item' => 'Q95',
                ],
            ]);
        $page = new Page($project, 'User:Test');
        $page->setRepository($pageRepo);

        $this->assertEquals(42, $page->getId());
        $this->assertEquals('https://example.org/User:Test', $page->getUrl());
        $this->assertEquals(5000, $page->getWatchers());
        $this->assertEquals(300, $page->getLength());
        $this->assertEquals(2, $page->getNamespace());
        $this->assertEquals('User', $page->getNamespaceName());
        $this->assertEquals('Q95', $page->getWikidataId());
        $this->assertEquals('Test', $page->getTitleWithoutNamespace());
    }

    /**
     * Tests wikidata item getter.
     */
    public function testWikidataItems()
    {
        $wikidataItems = [
            [
                'ips_site_id' => 'enwiki',
                'ips_site_page' => 'Google',
            ],
            [
                'ips_site_id' => 'arwiki',
                'ips_site_page' => 'جوجل',
            ],
        ];

        $pageRepo = $this->getMock(PagesRepository::class, ['getPageInfo', 'getWikidataItems']);
        $pageRepo->method('getPageInfo')
            ->willReturn([
                'pageprops' => [
                    'wikibase_item' => 'Q95',
                ],
            ]);
        $pageRepo->expects($this->once())
            ->method('getWikidataItems')
            ->willReturn($wikidataItems);
        $page = new Page(new Project('TestProject'), 'Test_Page');
        $page->setRepository($pageRepo);

        $this->assertArraySubset($wikidataItems, $page->getWikidataItems());

        // If no wikidata item...
        $pageRepo2 = $this->getMock(PagesRepository::class, ['getPageInfo']);
        $pageRepo2->expects($this->once())
            ->method('getPageInfo')
            ->willReturn([
                'pageprops' => [],
            ]);
        $page->setRepository($pageRepo2);
        $this->assertNull($page->getWikidataId());
        $this->assertEquals(0, $page->countWikidataItems());
    }

    /**
     * Tests wikidata item counter.
     */
    public function testCountWikidataItems()
    {
        $pageRepo = $this->getMock(PagesRepository::class, ['getWikidataItems']);
        $page = new Page(new Project('TestProject'), 'Test_Page');
        $pageRepo->method('getWikidataItems')
            ->with($page, true)
            ->willReturn(2);
        $page->setRepository($pageRepo);

        $this->assertEquals(2, $page->countWikidataItems());
    }

    /**
     * A list of a single user's edits on this page can be retrieved, along with the count of
     * these revisions, and the total bytes added and removed.
     * @TODO this is not finished yet
     */
    public function testUsersEdits()
    {
        $pageRepo = $this->getMock(PagesRepository::class, ['getRevisions']);
        $pageRepo
            ->method('getRevisions')
            ->with()
            ->willReturn([
                [
                    'id' => '1',
                    'timestamp' => '20170505100000',
                    'length_change' => '1',
                    'comment' => 'One'
                ],
            ]);

        $page = new Page(new Project('exampleWiki'), 'Page');
        $page->setRepository($pageRepo);
        $user = new User('Testuser');
        //$this->assertCount(3, $page->getRevisions($user)->getCount());
    }

    /**
     * Wikidata errors. With this test getWikidataInfo doesn't return a Description,
     *     so getWikidataErrors should complain accordingly
     */
    public function testWikidataErrors()
    {
        $pageRepo = $this->getMock(PagesRepository::class, ['getWikidataInfo', 'getPageInfo']);

        $pageRepo
            ->method('getWikidataInfo')
            ->with()
            ->willReturn([
                [
                    'term' => 'label',
                    'term_text' => 'My article',
                ],
            ]);
        $pageRepo
            ->method('getPageInfo')
            ->with()
            ->willReturn([
                'pagelanguage' => 'en',
                'pageprops' => [
                    'wikibase_item' => 'Q123',
                ],
            ]);

        $page = new Page(new Project('exampleWiki'), 'Page');
        $page->setRepository($pageRepo);

        $wikidataErrors = $page->getWikidataErrors();

        $this->assertArraySubset(
            [
                'prio' => 3,
                'name' => 'Wikidata',
            ],
            $wikidataErrors[0]
        );
        $this->assertContains(
            'Description',
            $wikidataErrors[0]['notice']
        );
    }

    /**
     * Tests for pageviews-related functions
     */
    public function testPageviews()
    {
        $pageviewsData = [
            'items' => [
                ['views' => 2500],
                ['views' => 1000],
            ],
        ];

        $pageRepo = $this->getMock(PagesRepository::class, ['getPageviews']);
        $pageRepo->method('getPageviews')->willReturn($pageviewsData);
        $page = new Page(new Project('exampleWiki'), 'Page');
        $page->setRepository($pageRepo);

        $this->assertEquals(
            3500,
            $page->getPageviews('20160101', '20160201')
        );

        $this->assertEquals(3500, $page->getLastPageviews(30));
    }

    // public function testPageAssessments()
    // {
    //     $projectRepo = $this->getMock(ProjectRepository::class, ['getAssessmentsConfig']);
    //     $projectRepo
    //         ->method('getAssessmentsConfig')
    //         ->willReturn([
    //             'wikiproject_prefix' => 'Wikipedia:WikiProject_'
    //         ]);

    //     $project = $this->getMock(Project::class, ['getDomain']);
    //     $project
    //         ->method('getDomain')
    //         ->willReturn('test.wiki.org');
    //     $project->setRepository($projectRepo);

    //     $pageRepo = $this->getMock(PagesRepository::class, ['getAssessments', 'getPageInfo']);
    //     $pageRepo
    //         ->method('getAssessments')
    //         ->with($project)
    //         ->willReturn([
    //             [
    //                 'wikiproject' => 'Military history',
    //                 'class' => 'Start',
    //                 'importance' => 'Low',
    //             ],
    //             [
    //                 'wikiproject' => 'Firearms',
    //                 'class' => 'C',
    //                 'importance' => 'High',
    //             ],
    //         ]);
    //     $pageRepo
    //         ->method('getPageInfo')
    //         ->with($project, 'Test_page')
    //         ->willReturn([
    //             'pageid' => 5,
    //         ]);

    //     $page = new Page($project, 'Test_page');
    //     $page->setRepository($pageRepo);

    //     $assessments = $page->getAssessments();

    //     $this->assertEquals('C', $assessments['assessment']);
    // }
}
