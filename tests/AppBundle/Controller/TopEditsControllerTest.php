<?php
/**
 * This file contains only the TopEditsControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * Integration tests for the Top Edits tool.
 * @group integration
 */
class TopEditsControllerTest extends WebTestCase
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
     * Test that the form can be retrieved.
     */
    public function testIndex()
    {
        // Check basics.
        $this->client->request('GET', '/topedits');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        // For now...
        if (!$this->container->getParameter('app.is_labs') || $this->container->getParameter('app.single_wiki')) {
            return;
        }

        // Should populate the appropriate fields.
        $crawler = $this->client->request('GET', '/topedits/de.wikipedia.org?namespace=3&article=Test');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        static::assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
        static::assertEquals(3, $crawler->filter('#namespace_select option:selected')->attr('value'));
        static::assertEquals('Test', $crawler->filter('#article_input')->attr('value'));

        // Legacy URL params.
        $crawler = $this->client->request('GET', '/topedits/?namespace=5&page=Test&wiki=wikipedia&lang=fr');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        static::assertEquals('fr.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
        static::assertEquals(5, $crawler->filter('#namespace_select option:selected')->attr('value'));
        static::assertEquals('Test', $crawler->filter('#article_input')->attr('value'));
    }
}
