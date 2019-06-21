<?php
declare(strict_types = 1);

namespace Tests\AppBundle\Twig;

use AppBundle\Helper\I18nHelper;
use AppBundle\Twig\TopNavExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Client;
use Tests\AppBundle\TestAdapter;

/**
 * Tests for the TopNavExtension class.
 */
class TopNavExtensionTest extends TestAdapter
{
    /** @var TopNavExtension Instance of class. */
    protected $topNavExtension;

    /** @var Client HTTP client. */
    private $client;

    /**
     * Set class instance.
     */
    public function setUp(): void
    {
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $stack = new RequestStack();
        $session = new Session();
        $i18nHelper = new I18nHelper($container, $stack, $session);
        $this->topNavExtension = new TopNavExtension($container, $stack, $session, $i18nHelper);
    }

    /**
     * @covers \AppBundle\Twig\TopNavExtension::topNavEditCounter()
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
     * @covers \AppBundle\Twig\TopNavExtension::topNavUser()
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
     * @covers \AppBundle\Twig\TopNavExtension::topNavPage()
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
     * @covers \AppBundle\Twig\TopNavExtension::topNavProject()
     */
    public function testTopNavProject(): void
    {
        static::assertEquals([
            'Admin Stats',
            'Patroller Stats',
            'Steward Stats',
        ], array_values($this->topNavExtension->topNavProject()));
    }
}
