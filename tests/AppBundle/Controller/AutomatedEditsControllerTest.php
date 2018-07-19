<?php
/**
 * This file contains only the AutomatedEditsControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Bundle\FrameworkBundle\Client;

/**
 * Integration tests for the Auto Edits tool.
 * @group integration
 */
class AutomatedEditsControllerTest extends WebTestCase
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
        $this->client->request('GET', '/autoedits');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        // For now...
        if (!$this->container->getParameter('app.is_labs') || $this->container->getParameter('app.single_wiki')) {
            return;
        }

        // Should populate the appropriate fields.
        $crawler = $this->client->request('GET', '/autoedits/de.wikipedia.org?namespace=3&end=2017-01-01');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        static::assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
        static::assertEquals(3, $crawler->filter('#namespace_select option:selected')->attr('value'));
        static::assertEquals('2017-01-01', $crawler->filter('[name=end]')->attr('value'));

        // Legacy URL params.
        $crawler = $this->client->request('GET', '/autoedits/?project=fr.wikipedia.org&namespace=5&begin=2017-02-01');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        static::assertEquals('fr.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
        static::assertEquals(5, $crawler->filter('#namespace_select option:selected')->attr('value'));
        static::assertEquals('2017-02-01', $crawler->filter('[name=start]')->attr('value'));
    }
}
