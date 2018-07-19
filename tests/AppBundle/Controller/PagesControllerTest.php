<?php
/**
 * This file contains only the PagesControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * Integration tests for the PagesController.
 * @group integration
 */
class PagesControllerTest extends WebTestCase
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
     * Test that the Pages tool index page displays correctly.
     */
    public function testIndex()
    {
        $this->client->request('GET', '/pages');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        if ($this->container->getParameter('app.is_labs') && !$this->container->getParameter('app.single_wiki')) {
            $crawler = $this->client->request('GET', '/pages/de.wikipedia.org');
            static::assertEquals(200, $this->client->getResponse()->getStatusCode());

            // should populate project input field
            static::assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));

            // assert that the namespaces were correctly loaded from API
            $namespaceOptions = $crawler->filter('#namespace_select option');
            static::assertEquals('Diskussion', trim($namespaceOptions->eq(2)->text())); // Talk in German
        }
    }
}
