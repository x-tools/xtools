<?php

declare(strict_types = 1);

namespace App\Tests\Twig;

use App\Helper\I18nHelper;
use App\Repository\ProjectRepository;
use App\Tests\TestAdapter;
use App\Twig\TopNavExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
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
        $container = static::createClient()->getContainer();
        $stack = new RequestStack();
        $session = new Session();
        $i18nHelper = new I18nHelper($container, $stack, $session);
        $urlGenerator = $this->createMock(UrlGenerator::class);
        $this->topNavExtension = new TopNavExtension(
            $container,
            $stack,
            $session,
            $i18nHelper,
            $urlGenerator,
            $this->createMock(ProjectRepository::class),
            false
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
