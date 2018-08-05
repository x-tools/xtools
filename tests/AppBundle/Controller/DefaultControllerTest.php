<?php
/**
 * This file contains only the DefaultControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Integration tests for the homepage and user authentication.
 * @group integration
 */
class DefaultControllerTest extends WebTestCase
{
    /** @var Container The DI container. */
    protected $container;

    /** @var Client HTTP client */
    protected $client;

    /** @var bool Whether we're testing a single-wiki setup */
    protected $isSingle;

    /**
     * Create the HTTP client and get the DI container.
     */
    public function setUp()
    {
        $this->client = static::createClient();
        $this->container = $this->client->getContainer();
        $this->isSingle = $this->container->getParameter('app.single_wiki');
    }

    /**
     * Test that the homepage is served, including in multiple languages.
     */
    public function testIndex()
    {
        $client = static::createClient();

        // Check basics.
        $crawler = $client->request('GET', '/');
        static::assertEquals(200, $client->getResponse()->getStatusCode());
        static::assertContains('XTools', $crawler->filter('.splash-logo')->attr('alt'));

        // Change language.
        $crawler = $client->request('GET', '/?uselang=es');
        static::assertContains(
            'Herramientas de X!',
            $crawler->filter('.splash-logo')->attr('alt')
        );

        // Make sure all active tools are listed.
        static::assertCount(10, $crawler->filter('.tool-list a.btn'));
    }

    /**
     * Test that the about page is served.
     */
    public function testAbout()
    {
        $client = static::createClient();
        $client->request('GET', '/about');
        static::assertEquals(200, $client->getResponse()->getStatusCode());
    }

    /**
     * OAuth callback action.
     */
    public function testOAuthCallback()
    {
        $client = static::createClient();
        static::setExpectedException(NotFoundHttpException::class);
        $client->request('GET', '/oauth_callback');

        // Callback should 404 since we didn't give it anything.
        static::assertEquals(404, $client->getResponse()->getStatusCode());
    }

    /**
     * Logout action.
     */
    public function testLogout()
    {
        $client = static::createClient();
        $client->request('GET', '/logout');
        static::assertEquals(302, $client->getResponse()->getStatusCode());
    }

    /**
     * Normalize a project name
     */
    public function testNormalizeProject()
    {
        if (!$this->isSingle && $this->container->getParameter('app.is_labs')) {
            $expectedOutput = [
                'project' => 'en.wikipedia.org',
                'domain' => 'en.wikipedia.org',
                'url' => 'https://en.wikipedia.org/',
                'api' => 'https://en.wikipedia.org/w/api.php',
                'database' => 'enwiki',
            ];

            // from database name
            $this->client->request('GET', '/api/project/normalize/enwiki');
            $output = json_decode($this->client->getResponse()->getContent(), true);
            // Removed elapsed_time from the output, since we don't know what the value will be.
            unset($output['elapsed_time']);
            static::assertEquals($expectedOutput, $output);

            // from domain name (without .org)
            $this->client->request('GET', '/api/project/normalize/en.wikipedia');
            $output = json_decode($this->client->getResponse()->getContent(), true);
            unset($output['elapsed_time']);
            static::assertEquals($expectedOutput, $output);
        }
    }

    /**
     * Test that we can retrieve the namespace information.
     */
    public function testNamespaces()
    {
        // Test 404 (for single-wiki setups, that wiki's namespaces are always returned).
        $this->client->request('GET', '/api/project/namespaces/wiki.that.doesnt.exist.org');
        if ($this->isSingle) {
            static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        } else {
            static::assertEquals(404, $this->client->getResponse()->getStatusCode());
        }

        if (!$this->isSingle && $this->container->getParameter('app.is_labs')) {
            $this->client->request('GET', '/api/project/namespaces/fr.wikipedia.org');
            static::assertEquals(200, $this->client->getResponse()->getStatusCode());

            // Check that a correct namespace value was returned
            $response = (array) json_decode($this->client->getResponse()->getContent());
            $namespaces = (array) $response['namespaces'];
            static::assertEquals('Utilisateur', array_values($namespaces)[2]); // User in French
        }
    }

    /**
     * Test page assessments.
     */
    public function testAssessments()
    {
        // Test 404 (for single-wiki setups, that wiki's namespaces are always returned).
        $this->client->request('GET', '/api/project/assessments/wiki.that.doesnt.exist.org');
        if ($this->isSingle) {
            static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        } else {
            static::assertEquals(404, $this->client->getResponse()->getStatusCode());
        }

        if ($this->container->getParameter('app.is_labs')) {
            $this->client->request('GET', '/api/project/assessments/en.wikipedia.org');
            static::assertEquals(200, $this->client->getResponse()->getStatusCode());

            $response = (array)json_decode($this->client->getResponse()->getContent(), true);
            static::assertEquals('en.wikipedia.org', $response['project']);
            static::assertArraySubset(
                ['FA', 'A', 'GA', 'bplus', 'B', 'C', 'Start'],
                array_keys($response['assessments']['class'])
            );
        }
    }
}
