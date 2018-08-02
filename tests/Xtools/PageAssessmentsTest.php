<?php
/**
 * This file contains only the PageAssessmentsTest class.
 */

namespace Tests\Xtools;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Xtools\Page;
use Xtools\PageAssessments;
use Xtools\PageAssessmentsRepository;
use Xtools\PageRepository;
use Xtools\Project;
use Xtools\ProjectRepository;

/**
 * Tests for the PageAssessments class.
 */
class PageAssessmentsTest extends TestAdapter
{
    /** @var Container The Symfony container. */
    protected $container;

    /** @var PageAssessmentsRepository The repository for page assessments. */
    protected $paRepo;

    /** @var Project The project we're working with. */
    protected $project;

    /**
     * Set up client and set container, and PageAssessmentsRepository mock.
     */
    public function setUp()
    {
        $client = static::createClient();
        $this->container = $client->getContainer();

        $this->paRepo = $this->getMock(PageAssessmentsRepository::class, ['getConfig', 'getAssessments']);
        $this->paRepo->method('getConfig')
            ->willReturn($this->container->getParameter('assessments')['en.wikipedia.org']);

        $this->project = $this->getMock(Project::class, [], ['testwiki']);
        $this->project->method('getPageAssessments')
            ->willReturn($this->paRepo);
    }

    /**
     * Some of the basics.
     */
    public function testBasics()
    {
        $pa = new PageAssessments($this->project);
        $pa->setRepository($this->paRepo);

        $this->assertEquals(
            $this->container->getParameter('assessments')['en.wikipedia.org'],
            $pa->getConfig()
        );
        $this->assertTrue($pa->isEnabled());
        $this->assertTrue($pa->hasImportanceRatings());
        $this->assertTrue($pa->isSupportedNamespace(6));
    }

    /**
     * Badges
     */
    public function testBadges()
    {
        $pa = new PageAssessments($this->project);
        $pa->setRepository($this->paRepo);

        $this->assertEquals(
            'https://upload.wikimedia.org/wikipedia/commons/b/bc/Featured_article_star.svg',
            $pa->getBadgeURL('FA')
        );

        $this->assertEquals(
            'Featured_article_star.svg',
            $pa->getBadgeURL('FA', true)
        );
    }

    /**
     * Page assements.
     */
    public function testGetAssessments()
    {
        $pageRepo = $this->getMock(PageRepository::class, ['getPageInfo']);
        $pageRepo->method('getPageInfo')->willReturn([
            'title' => 'Test Page',
            'ns' => 0
        ]);
        $page = new Page($this->project, 'Test_page');
        $page->setRepository($pageRepo);

        $this->paRepo->expects($this->once())
            ->method('getAssessments')
            ->with($page)
            ->willReturn([
                [
                    'wikiproject' => 'Military history',
                    'class' => 'Start',
                    'importance' => 'Low',
                ],
                [
                    'wikiproject' => 'Firearms',
                    'class' => 'C',
                    'importance' => 'High',
                ],
            ]);

        $pa = new PageAssessments($this->project);
        $pa->setRepository($this->paRepo);

        $assessments = $pa->getAssessments($page);

        // Picks the first assessment.
        $this->assertEquals([
            'value' => 'Start',
            'color' => '#ffaa66',
            'category' => 'Category:Start-Class articles',
            'badge' => 'https://upload.wikimedia.org/wikipedia/commons/a/a4/Symbol_start_class.svg',
        ], $assessments['assessment']);

        $this->assertEquals(2, count($assessments['wikiprojects']));
    }
}
