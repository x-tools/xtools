<?php

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{

    public function testIndex()
    {
        $client = static::createClient();

        // Check basics.
        $crawler = $client->request('GET', '/');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertContains('Welcome to XTools!', $crawler->filter('#content h2')->text());

        // Change language.
        $crawler = $client->request('GET', '/?uselang=es');
        $this->assertContains(
            'Te damos la bienvenida a las herramientas de X!',
            $crawler->filter('#content h2')->text()
        );

        // Make sure all active tools are listed.
        $this->assertCount(7, $crawler->filter('#tool-list li'));
    }
}
