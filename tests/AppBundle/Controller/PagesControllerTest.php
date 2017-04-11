<?php

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PagesControllerTest extends WebTestCase
{

    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/pages/de.wikipedia.org');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // should populate project input field
        $this->assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));

        // assert that the namespaces were correctly loaded from API
        $namespaceOptions = $crawler->filter('#namespace_select option');
        $this->assertEquals('Diskussion', trim($namespaceOptions->eq(2)->text())); // Talk in German
    }
}
