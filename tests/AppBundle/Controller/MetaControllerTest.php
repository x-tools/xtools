<?php
/**
 * This file contains only the MetaControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use AppBundle\Controller\MetaController;

/**
 * Integration/unit tests for the ArticleInfoController.
 * @group integration
 */
class MetaControllerTest extends WebTestCase
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
        $this->controller = new MetaController();
        $this->controller->setContainer($this->container);
    }

    /**
     * Test that the Meta index page displays correctly.
     */
    public function testIndex()
    {
        $crawler = $this->client->request('GET', '/meta/');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        if (!$this->container->getParameter('app.is_labs')) {
            return;
        }

        // Should redirect since we have supplied all necessary parameters.
        $crawler = $this->client->request('GET', '/meta?start=2017-10-01&end=2017-10-10');
        $this->assertEquals(302, $this->client->getResponse()->getStatusCode());
    }
}
