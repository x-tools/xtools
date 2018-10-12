<?php
/**
 * This file contains only the PageTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\Page;
use AppBundle\Model\Project;
use AppBundle\Repository\PageRepository;
use AppBundle\Repository\ProjectRepository;
use Symfony\Component\DependencyInjection\Container;
use Tests\AppBundle\TestAdapter;

/**
 * Tests for the Page class.
 */
class PageTest extends TestAdapter
{
    /** @var Container The Symfony container. */
    protected $container;

    /**
     * Set up client and set container.
     */
    public function setUp(): void
    {
        $client = static::createClient();
        $this->container = $client->getContainer();
    }

    /**
     * A page has a title and an HTML display title.
     */
    public function testTitles(): void
    {
        $project = new Project('TestProject');
        $pageRepo = $this->getMock(PageRepository::class, ['getPageInfo']);
        $data = [
            [$project, 'Test_Page_1', ['title' => 'Test_Page_1']],
            [$project, 'Test_Page_2', ['title' => 'Test_Page_2', 'displaytitle' => '<em>Test</em> page 2']],
        ];
        $pageRepo->method('getPageInfo')->will($this->returnValueMap($data));

        // Page with no display title.
        $page = new Page($project, 'Test_Page_1');
        $page->setRepository($pageRepo);
        static::assertEquals('Test_Page_1', $page->getTitle());
        static::assertEquals('Test_Page_1', $page->getDisplayTitle());

        // Page with a display title.
        $page = new Page($project, 'Test_Page_2');
        $page->setRepository($pageRepo);
        static::assertEquals('Test_Page_2', $page->getTitle());
        static::assertEquals('<em>Test</em> page 2', $page->getDisplayTitle());

        // Getting the unnormalized title should not call getPageInfo.
        $page = new Page($project, 'talk:Test Page_3');
        $page->setRepository($pageRepo);
        $pageRepo->expects($this->never())->method('getPageInfo');
        static::assertEquals('talk:Test Page_3', $page->getTitle(true));
    }

    /**
     * A page either exists or doesn't.
     */
    public function testExists(): void
    {
        $pageRepo = $this->getMock(PageRepository::class, ['getPageInfo']);
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
        static::assertTrue($page1->exists());

        // Missing page.
        $page2 = new Page($project, 'Missing_page');
        $page2->setRepository($pageRepo);
        static::assertFalse($page2->exists());
    }

    /**
     * Test basic getters
     */
    public function testBasicGetters(): void
    {
        $project = $this->getMock(Project::class, ['getNamespaces'], ['TestProject']);
        $project->method('getNamespaces')
            ->willReturn([
                '',
                'Talk',
                'User',
            ]);

        $pageRepo = $this->getMock(PageRepository::class, ['getPageInfo']);
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

        static::assertEquals(42, $page->getId());
        static::assertEquals('https://example.org/User:Test', $page->getUrl());
        static::assertEquals(5000, $page->getWatchers());
        static::assertEquals(300, $page->getLength());
        static::assertEquals(2, $page->getNamespace());
        static::assertEquals('User', $page->getNamespaceName());
        static::assertEquals('Q95', $page->getWikidataId());
        static::assertEquals('Test', $page->getTitleWithoutNamespace());
    }

    /**
     * Test fetching of wikitext
     */
    public function testWikitext(): void
    {
        $pageRepo = new PageRepository();
        $pageRepo->setContainer($this->container);
        $project = ProjectRepository::getProject('en.wikipedia.org', $this->container);
        $page = new Page($project, 'Main Page');
        $page->setRepository($pageRepo);

        // We want to do a real-world test. enwiki's Main Page does not change much,
        // and {{Main Page banner}} in particular should be there indefinitely, hopefully :)
        $content = $page->getWikitext();
        static::assertContains('{{Main Page banner}}', $content);
    }

    /**
     * Tests wikidata item getter.
     */
    public function testWikidataItems(): void
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

        $pageRepo = $this->getMock(PageRepository::class, ['getPageInfo', 'getWikidataItems']);
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

        static::assertArraySubset($wikidataItems, $page->getWikidataItems());

        // If no wikidata item...
        $pageRepo2 = $this->getMock(PageRepository::class, ['getPageInfo']);
        $pageRepo2->expects($this->once())
            ->method('getPageInfo')
            ->willReturn([
                'pageprops' => [],
            ]);
        $page2 = new Page(new Project('TestProject'), 'Test_Page');
        $page2->setRepository($pageRepo2);
        static::assertNull($page2->getWikidataId());
        static::assertEquals(0, $page2->countWikidataItems());
    }

    /**
     * Tests wikidata item counter.
     */
    public function testCountWikidataItems(): void
    {
        $pageRepo = $this->getMock(PageRepository::class, ['getWikidataItems']);
        $page = new Page(new Project('TestProject'), 'Test_Page');
        $pageRepo->method('getWikidataItems')
            ->with($page, true)
            ->willReturn(2);
        $page->setRepository($pageRepo);

        static::assertEquals(2, $page->countWikidataItems());
    }

    /**
     * A list of a single user's edits on this page can be retrieved, along with the count of
     * these revisions, and the total bytes added and removed.
     * @TODO this is not finished yet
     */
    public function testUsersEdits(): void
    {
        $pageRepo = $this->getMock(PageRepository::class, ['getRevisions']);
        $pageRepo
            ->method('getRevisions')
            ->with()
            ->willReturn([
                [
                    'id' => '1',
                    'timestamp' => '20170505100000',
                    'length_change' => '1',
                    'comment' => 'One',
                ],
            ]);

        $page = new Page(new Project('exampleWiki'), 'Page');
        $page->setRepository($pageRepo);
        //$user = new User('Testuser');
        //static::assertCount(3, $page->getRevisions($user)->getCount());
    }

    /**
     * Wikidata errors. With this test getWikidataInfo doesn't return a Description,
     *     so getWikidataErrors should complain accordingly
     */
    public function testWikidataErrors(): void
    {
        $pageRepo = $this->getMock(PageRepository::class, ['getWikidataInfo', 'getPageInfo']);

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

        static::assertArraySubset(
            [
                'prio' => 3,
                'name' => 'Wikidata',
            ],
            $wikidataErrors[0]
        );
        static::assertContains(
            'Description',
            $wikidataErrors[0]['notice']
        );
    }

    /**
     * Test getErros and getCheckWikiErrors.
     */
    public function testErrors(): void
    {
        $pageRepo = $this->getMock(PageRepository::class, [
            'getWikidataInfo', 'getCheckWikiErrors', 'getPageInfo',
        ]);
        $checkWikiErrors = [
            [
                'error' => '61',
                'notice' => 'This is where the error is',
                'found' => '2017-08-09 00:05:09',
                'name' => 'Reference before punctuation',
                'prio' => '3',
                'explanation' => 'This is how to fix the error',
            ],
        ];
        $wikidataErrors = [
            [
                'prio' => 3,
                'name' => 'Wikidata',
                'notice' => 'Description for language <em>en</em> is missing',
                'explanation' => "See: <a target='_blank' " .
                    "href='//www.wikidata.org/wiki/Help:Description'>Help:Description</a>",
            ],
        ];

        $pageRepo->method('getCheckWikiErrors')
            ->willReturn($checkWikiErrors);
        $pageRepo->method('getWikidataInfo')
            ->willReturn([[
                'term' => 'label',
                'term_text' => 'My article',
            ]]);
        $pageRepo->method('getPageInfo')
            ->willReturn([
                'pagelanguage' => 'en',
                'pageprops' => [
                    'wikibase_item' => 'Q123',
                ],
            ]);
        $page = new Page(new Project('exampleWiki'), 'Page');
        $page->setRepository($pageRepo);

        static::assertEquals($checkWikiErrors, $page->getCheckWikiErrors());
        static::assertEquals(
            array_merge($wikidataErrors, $checkWikiErrors),
            $page->getErrors()
        );
    }

    /**
     * Tests for pageviews-related functions
     */
    public function testPageviews(): void
    {
        $pageviewsData = [
            'items' => [
                ['views' => 2500],
                ['views' => 1000],
            ],
        ];

        $pageRepo = $this->getMock(PageRepository::class, ['getPageviews']);
        $pageRepo->method('getPageviews')->willReturn($pageviewsData);
        $page = new Page(new Project('exampleWiki'), 'Page');
        $page->setRepository($pageRepo);

        static::assertEquals(
            3500,
            $page->getPageviews('20160101', '20160201')
        );

        static::assertEquals(3500, $page->getLastPageviews(30));
    }

    /**
     * Is the page the Main Page?
     */
    public function testIsMainPage(): void
    {
        $pageRepo = new PageRepository();
        $pageRepo->setContainer($this->container);
        $project = ProjectRepository::getProject('en.wikipedia.org', $this->container);
        $page = new Page($project, 'Main Page');
        $page->setRepository($pageRepo);
        static::assertTrue($page->isMainPage());
    }

    /**
     * Links and redirects.
     */
    public function testLinksAndRedirects(): void
    {
        $data = [
            'links_ext_count' => '418',
            'links_out_count' => '1085',
            'links_in_count' => '33300',
            'redirects_count' => '61',
        ];
        $pageRepo = $this->getMock(PageRepository::class, ['countLinksAndRedirects']);
        $pageRepo->method('countLinksAndRedirects')->willReturn($data);
        $page = new Page(new Project('exampleWiki'), 'Page');
        $page->setRepository($pageRepo);

        static::assertEquals($data, $page->countLinksAndRedirects());
    }
}
