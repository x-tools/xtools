<?php

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

class PagesControllerTest extends WebTestCase
{
    /** @var Container */
    protected $container;

    public function setUp()
    {
        $client = static::createClient();
        $this->container = $client->getContainer();
    }

    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/pages');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        if ($this->container->getParameter('app.is_labs')) {
            $crawler = $client->request('GET', '/pages/de.wikipedia.org');
            $this->assertEquals(200, $client->getResponse()->getStatusCode());

            // should populate project input field
            $this->assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));

            // assert that the namespaces were correctly loaded from API
            $namespaceOptions = $crawler->filter('#namespace_select option');
            $this->assertEquals('Diskussion', trim($namespaceOptions->eq(2)->text())); // Talk in German
        }
    }
}
