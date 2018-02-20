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
        // For now...
        if (!$this->container->getParameter('app.is_labs') || $this->container->getParameter('app.single_wiki')) {
            return;
        }

        $crawler = $this->client->request('GET', '/adminstats/invalid.wiki.org');
        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());

        $crawler = $this->client->request('GET', '/adminstats/frwiki/2017-01-01/2017-01-10');
        $statList = $crawler->filter('.stat-list')->text();
        $this->assertContains('2017-01-01', $statList);
        $this->assertContains('2017-01-10', $statList);

        $this->assertCount(
            170,
            $crawler->filter('.top-editors-table tbody tr')
        );
    }
}
