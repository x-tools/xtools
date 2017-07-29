<?php
/**
 * This file contains only the AppExtensionTest class.
 */

namespace AppBundle\Twig;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use AppBundle\Twig\AppExtension;
use AppBundle\Twig\Extension;

/**
 * Tests for the AppExtension class.
 */
class AppExtensionTest extends WebTestCase
{
    /** @var Container The Symfony container. */
    protected $container;

    /** @var AppBundle\Twig\AppExtension Instance of class */
    protected $appExtension;

    /**
     * Set class instance.
     */
    public function setUp()
    {
        $this->client = static::createClient();
        $this->container = $this->client->getContainer();
        $stack = new RequestStack();
        $session = new Session();
        $this->appExtension = new AppExtension($this->container, $stack, $session);
    }

    /**
     * Format number as a diff size
     */
    public function testDiffFormat()
    {
        $this->assertEquals(
            "<span class='diff-pos'>3,000</span>",
            $this->appExtension->diffFormat(3000)
        );
        $this->assertEquals(
            "<span class='diff-neg'>-20,000</span>",
            $this->appExtension->diffFormat(-20000)
        );
        $this->assertEquals(
            "<span class='diff-zero'>0</span>",
            $this->appExtension->diffFormat(0)
        );
    }

    /**
     * Format a time duration as humanized string.
     */
    public function testFormatDuration()
    {
        $this->assertEquals(
            [30, 'num-seconds'],
            $this->appExtension->formatDuration(30, false)
        );
        $this->assertEquals(
            [1, 'num-minutes'],
            $this->appExtension->formatDuration(70, false)
        );
        $this->assertEquals(
            [50, 'num-minutes'],
            $this->appExtension->formatDuration(3000, false)
        );
        $this->assertEquals(
            [2, 'num-hours'],
            $this->appExtension->formatDuration(7500, false)
        );
        $this->assertEquals(
            [10, 'num-days'],
            $this->appExtension->formatDuration(864000, false)
        );
    }
}
