<?php

declare(strict_types = 1);

namespace App\Tests\Twig;

use App\Repository\ProjectRepository;
use App\Tests\TestAdapter;
use App\Twig\TopNavExtension;
use Symfony\Component\Routing\Generator\UrlGenerator;

/**
 * Tests for the TopNavExtension class.
 * @covers \App\Twig\TopNavExtension
 */
class TopNavExtensionTest extends TestAdapter
{
    protected TopNavExtension $topNavExtension;

    /**
     * Set class instance.
     */
    public function setUp(): void
    {
        static::createClient();
        $this->topNavExtension = new TopNavExtension(
            static::$container->get('request_stack'),
            static::$container->get('session'),
            static::$container->get('app.i18n_helper'),
            $this->createMock(UrlGenerator::class),
            $this->createMock(ProjectRepository::class),
            static::$container->get('parameter_bag'),
            static::$container->getParameter('app.is_wmf'),
            static::$container->getParameter('app.single_wiki'),
            static::$container->getParameter('app.replag_threshold')
        );
    }

    /**
     * @covers \App\Twig\TopNavExtension::topNavEditCounter()
     */
    public function testTopNavEditCounter(): void
    {
        static::assertEquals([
            'General statistics',
            'Month counts',
            'Namespace Totals',
            'Rights changes',
            'Time card',
            'Top edited pages',
            'Year counts',
        ], array_values($this->topNavExtension->topNavEditCounter()));
    }

    /**
     * @covers \App\Twig\TopNavExtension::topNavUser()
     */
    public function testTopNavUser(): void
    {
        static::assertEquals([
            'Admin Score',
            'Automated Edits',
            'Category Edits',
            'Edit Counter',
            'Edit Summaries',
            'Global Contributions',
            'Pages Created',
            'Simple Counter',
            'Top Edits',
        ], array_values($this->topNavExtension->topNavUser()));
    }

    /**
     * @covers \App\Twig\TopNavExtension::topNavPage()
     */
    public function testTopNavPage(): void
    {
        static::assertEquals([
            'Authorship',
            'Blame',
            'Page History',
        ], array_values($this->topNavExtension->topNavPage()));
    }

    /**
     * @covers \App\Twig\TopNavExtension::topNavProject()
     */
    public function testTopNavProject(): void
    {
        static::assertEquals([
            'Admin Stats',
            'Patroller Stats',
            'Steward Stats',
            'Largest Pages',
        ], array_values($this->topNavExtension->topNavProject()));
    }
}
