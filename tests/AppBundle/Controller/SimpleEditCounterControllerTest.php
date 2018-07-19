<?php
/**
 * This file contains only the SimpleEditCounterControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * Integration/unit tests for the ArticleInfoController.
 * @group integration
 */
class SimpleEditCounterControllerTest extends WebTestCase
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
     * Test that the Simple Edit Counter index page displays correctly.
     */
    public function testIndex()
    {
        $this->client->request('GET', '/sc/');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
    }
}
