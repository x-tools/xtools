<?php
/**
 * This file contains only the AdminStatsControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Bundle\FrameworkBundle\Client;

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
        $this->client->request('GET', '/adminstats');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
    }
}
