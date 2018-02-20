<?php
/**
 * This file contains only the SimpleEditCounterControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use AppBundle\Controller\SimpleEditCounterController;

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
        $this->controller = new SimpleEditCounterController();
        $this->controller->setContainer($this->container);
    }

    /**
     * Test that the Simple Edit Counter index page displays correctly.
     */
    public function testIndex()
    {
        $crawler = $this->client->request('GET', '/sc/');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
    }
}
