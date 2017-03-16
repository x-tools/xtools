<?php

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TopEditsControllerTest extends WebTestCase
{

    public function testIndex()
    {
        $client = static::createClient();

        // Check basics.
        $crawler = $client->request('GET', '/topedits');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }
}
