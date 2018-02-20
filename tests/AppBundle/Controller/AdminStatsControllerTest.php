<?php
/**
 * This file contains only the AdminStatsControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use AppBundle\Controller\AdminStatsController;

/**
 * Integration/unit tests for the AdminStatsController.
 * @group integration
 */
class AdminStatsControllerTest extends WebTestCase
{
    /** @var Container The DI container. */
    protected $container;

    /** @var Client The Symfony client */
    protected $client;

    /**
     * Set up the tests.
     */
    public function setUp()
    {
        $this->client = static::createClient();
        $this->container = $this->client->getContainer();
    }

    /**
     * Test that the AdminStats index page displays correctly.
     */
    public function testIndex()
    {
        $crawler = $this->client->request('GET', '/adminstats');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Test the method that sets up a AdminStats instance.
     */
    public function testSetupAdminStats()
    {
        $controller = new AdminStatsController();
        $controller->setContainer($this->container);

        // For now...
        if (!$this->container->getParameter('app.is_labs') || $this->container->getParameter('app.single_wiki')) {
            return;
        }

        $ret = $controller->setUpAdminStats('invalid.wiki.org', '2017-01-01', '2017-03-01');
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $ret);

        $controller2 = new AdminStatsController();
        $controller2->setContainer($this->container);

        $adminStats = $controller2->setUpAdminStats('frwiki', '2017-01-01', '2017-03-01');
        $this->assertInstanceOf('Xtools\AdminStats', $adminStats);
        $this->assertEquals('2017-01-01', $adminStats->getStart());
        $this->assertEquals('2017-03-01', $adminStats->getEnd());
    }
}
