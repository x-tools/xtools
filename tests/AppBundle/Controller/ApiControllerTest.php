<?php

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

class ApiControllerTest extends WebTestCase
{
    /** @var Container */
    protected $container;

    public function setUp()
    {
        $client = static::createClient();
        $this->container = $client->getContainer();
    }

    public function testNamespaces()
    {
        $client = static::createClient();
        $isSingle = $this->container->getParameter('app.single_wiki');

        // Test 404 (for single-wiki setups, that wiki's namespaces are always returned).
        $crawler = $client->request('GET', '/api/namespaces/wiki.that.doesnt.exist.org');
        if ($isSingle) {
            $this->assertEquals(200, $client->getResponse()->getStatusCode());
        } else {
            $this->assertEquals(404, $client->getResponse()->getStatusCode());
        }

        if (!$isSingle && $this->container->getParameter('app.is_labs')) {
            $crawler = $client->request('GET', '/api/namespaces/fr.wikipedia.org');
            $this->assertEquals(200, $client->getResponse()->getStatusCode());

            // Check that a correct namespace value was returned
            $namespaces = (array) json_decode($client->getResponse()->getContent());
            $this->assertEquals('Utilisateur', array_values($namespaces)[2]); // User in French
        }
    }
}
