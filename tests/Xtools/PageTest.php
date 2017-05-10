<?php

namespace Xtools;

use PHPUnit_Framework_TestCase;

class PageTest extends PHPUnit_Framework_TestCase
{

    /**
     * A page has a title and an HTML display title.
     */
    public function testTitles()
    {
        $project = new Project('TestProject');
        $pageRepo = $this->getMock(PagesRepository::class, ['getPageInfo']);
        $data = [
            [$project, 'Test_Page_1', true, ['title' => 'Test_Page_1']],
            [$project, 'Test_Page_2', true, ['title' => 'Test_Page_2', 'displaytitle' => '<em>Test</em> page 2']],
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
            [$project, 'Existing_page', true, []],
            [$project, 'Missing_page', true, ['missing' => '']],
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
     * A page has an integer ID on a given project.
     */
    public function testId()
    {
        $pageRepo = $this->getMock(PagesRepository::class, ['getPageInfo']);
        $pageRepo->expects($this->once())
            ->method('getPageInfo')
            ->willReturn(['pageid' => '42']);

        $page = new Page(new Project('TestProject'), 'Test_Page');
        $page->setRepository($pageRepo);
        $this->assertEquals(42, $page->getId());
    }

    /**
     * A page has a URL.
     */
    public function testUrls()
    {
        $pageRepo = $this->getMock(PagesRepository::class, ['getPageInfo']);
        $pageRepo->expects($this->once())
            ->method('getPageInfo')
            ->willReturn(['fullurl' => 'https://example.org/Page']);

        $page = new Page(new Project('exampleWiki'), 'Page');
        $page->setRepository($pageRepo);
        $this->assertEquals('https://example.org/Page', $page->getUrl());
    }
}
