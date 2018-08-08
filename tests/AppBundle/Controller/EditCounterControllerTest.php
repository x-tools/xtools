<?php
/**
 * This file contains only the EditCounterControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * Integration tests for the EditCounterController.
 * @group integration
 */
class EditCounterControllerTest extends WebTestCase
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
     * Test that the Edit Counter index page displays correctly.
     */
    public function testIndex()
    {
        $this->client->request('GET', '/ec');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        // For now...
        if (!$this->container->getParameter('app.is_labs') || $this->container->getParameter('app.single_wiki')) {
            return;
        }

        $crawler = $this->client->request('GET', '/ec/de.wikipedia.org');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        // should populate project input field
        static::assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
    }

    /**
     * Test that the Edit Counter index pages for the subtools are shown correctly.
     */
    public function testSubtoolIndexes()
    {
        $subtools = [
            'general-stats', 'namespace-totals', 'year-counts', 'month-counts',
            'timecard', 'rights-changes', 'latest-global-edits'
        ];

        foreach ($subtools as $subtool) {
            $crawler = $this->client->request('GET', '/ec-'.str_replace('-', '', $subtool));
            static::assertEquals(200, $this->client->getResponse()->getStatusCode());
            static::assertEquals(1, count($crawler->filter('.checkbox input:checked')));
            static::assertEquals($subtool, $crawler->filter('.checkbox input:checked')->attr('value'));
        }
    }
}
