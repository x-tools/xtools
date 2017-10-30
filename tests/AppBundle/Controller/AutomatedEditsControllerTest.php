<?php
/**
 * This file contains only the AutomatedEditsControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
        $crawler = $this->client->request('GET', '/autoedits');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        // For now...
        if (!$this->container->getParameter('app.is_labs') || $this->container->getParameter('app.single_wiki')) {
            return;
        }

        // Should populate the appropriate fields.
        $crawler = $this->client->request('GET', '/autoedits/de.wikipedia.org?namespace=3&end=2017-01-01');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
        $this->assertEquals(3, $crawler->filter('#namespace_select option:selected')->attr('value'));
        $this->assertEquals('2017-01-01', $crawler->filter('[name=end]')->attr('value'));

        // Legacy URL params.
        $crawler = $this->client->request('GET', '/autoedits/?project=fr.wikipedia.org&namespace=5&begin=2017-02-01');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('fr.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
        $this->assertEquals(5, $crawler->filter('#namespace_select option:selected')->attr('value'));
        $this->assertEquals('2017-02-01', $crawler->filter('[name=start]')->attr('value'));
    }
}
