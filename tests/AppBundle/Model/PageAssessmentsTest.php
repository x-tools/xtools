<?php
/**
 * This file contains only the PageAssessmentsTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\Page;
use AppBundle\Model\PageAssessments;
use AppBundle\Model\Project;
use AppBundle\Repository\PageAssessmentsRepository;
use AppBundle\Repository\PageRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tests\AppBundle\TestAdapter;

/**
 * Tests for the PageAssessments class.
 */
class PageAssessmentsTest extends TestAdapter
{
    /** @var ContainerInterface The Symfony localContainer ($localContainer to not override self::$container). */
    protected $localContainer;

    /** @var PageAssessmentsRepository The repository for page assessments. */
    protected $paRepo;

    /** @var Project The project we're working with. */
    protected $project;

    /**
     * Set up client and set container, and PageAssessmentsRepository mock.
     */
    public function setUp(): void
    {
        $client = static::createClient();
        $this->localContainer = $client->getContainer();

        $this->paRepo = $this->getMock(PageAssessmentsRepository::class, ['getConfig', 'getAssessments']);
        $this->paRepo->method('getConfig')
            ->willReturn($this->localContainer->getParameter('assessments')['en.wikipedia.org']);

        $this->project = $this->getMock(Project::class, [], ['testwiki']);
        $this->project->method('getPageAssessments')
            ->willReturn($this->paRepo);
    }

    /**
     * Some of the basics.
     */
    public function testBasics(): void
    {
        $pa = new PageAssessments($this->project);
        $pa->setRepository($this->paRepo);

        $this->assertEquals(
            $this->localContainer->getParameter('assessments')['en.wikipedia.org'],
            $pa->getConfig()
        );
        $this->assertTrue($pa->isEnabled());
        $this->assertTrue($pa->hasImportanceRatings());
        $this->assertTrue($pa->isSupportedNamespace(6));
    }

    /**
     * Badges
     */
    public function testBadges(): void
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
    public function testGetAssessments(): void
    {
        $pageRepo = $this->getMock(PageRepository::class, ['getPageInfo']);
        $pageRepo->method('getPageInfo')->willReturn([
            'title' => 'Test Page',
            'ns' => 0,
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
