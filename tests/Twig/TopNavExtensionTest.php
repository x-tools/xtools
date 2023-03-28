<?php

declare(strict_types = 1);

namespace App\Tests\Twig;

use App\Helper\I18nHelper;
use App\Repository\ProjectRepository;
use App\Tests\SessionHelper;
use App\Tests\TestAdapter;
use App\Twig\TopNavExtension;
use Symfony\Component\Routing\Generator\UrlGenerator;

/**
 * Tests for the TopNavExtension class.
 * @covers \App\Twig\TopNavExtension
 */
class TopNavExtensionTest extends TestAdapter
{
    use SessionHelper;

    protected TopNavExtension $topNavExtension;

    /**
     * Set class instance.
     */
    public function setUp(): void
    {
        $session = $this->createSession(static::createClient());
        $requestStack = $this->getRequestStack($session);
        $i18nHelper = new I18nHelper($requestStack, static::getContainer()->getParameter('kernel.project_dir'));
        $this->topNavExtension = new TopNavExtension(
            $requestStack,
            $i18nHelper,
            $this->createMock(UrlGenerator::class),
            $this->createMock(ProjectRepository::class),
            static::getContainer()->get('parameter_bag'),
            static::getContainer()->getParameter('app.is_wmf'),
            static::getContainer()->getParameter('app.single_wiki'),
            static::getContainer()->getParameter('app.replag_threshold')
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
