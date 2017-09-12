<?php
/**
 * This file contains only the ApiControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * Integration tests for XTools' external API.
 * @group integration
 */
class ApiControllerTest extends WebTestCase
{
    /** @var Container The DI container. */
    protected $container;

    /** @var Symfony\Bundle\FrameworkBundle\Client HTTP client */
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
     * Normalize a project name
     */
    public function testNormalizeProject()
    {
        if (!$this->isSingle && $this->container->getParameter('app.is_labs')) {
            $expectedOutput = [
                'domain' => 'en.wikipedia.org',
                'url' => 'https://en.wikipedia.org/',
                'api' => 'https://en.wikipedia.org/w/api.php',
                'database' => 'enwiki',
            ];

            // from database name
            $crawler = $this->client->request('GET', '/api/project/normalize/enwiki');
            $output = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertEquals($expectedOutput, $output);

            // from domain name (without .org)
            $crawler = $this->client->request('GET', '/api/project/normalize/en.wikipedia');
            $output = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertEquals($expectedOutput, $output);
        }
    }

    /**
     * Test that we can retrieve the namespace information.
     */
    public function testNamespaces()
    {
        // Test 404 (for single-wiki setups, that wiki's namespaces are always returned).
        $crawler = $this->client->request('GET', '/api/project/namespaces/wiki.that.doesnt.exist.org');
        if ($this->isSingle) {
            $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        } else {
            $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
        }

        if (!$this->isSingle && $this->container->getParameter('app.is_labs')) {
            $crawler = $this->client->request('GET', '/api/project/namespaces/fr.wikipedia.org');
            $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

            // Check that a correct namespace value was returned
            $response = (array) json_decode($this->client->getResponse()->getContent());
            $namespaces = (array) $response['namespaces'];
            $this->assertEquals('Utilisateur', array_values($namespaces)[2]); // User in French
        }
    }

    /**
     * Test automated edit counter endpoint.
     */
    public function testAutomatedEditCount()
    {
        if ($this->isSingle || !$this->container->getParameter('app.is_labs')) {
            // untestable :(
            return;
        }

        $url = '/api/user/automated_editcount/en.wikipedia/musikPuppet/all///1';
        $crawler = $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($response->headers->get('content-type'), 'application/json');

        $data = json_decode($response->getContent(), true);
        $toolNames = array_keys($data['automated_tools']);

        $this->assertEquals($data['project'], 'en.wikipedia.org');
        $this->assertEquals($data['username'], 'MusikPuppet');
        $this->assertGreaterThan(10, $data['automated_editcount']);
        $this->assertContains('Twinkle', $toolNames);
        $this->assertContains('Huggle', $toolNames);
    }

    /**
     * Test nonautomated edits endpoint.
     */
    public function testNonautomatedEdits()
    {
        if ($this->isSingle || !$this->container->getParameter('app.is_labs')) {
            // untestable :(
            return;
        }

        $url = '/api/user/nonautomated_edits/en.wikipedia/ThisIsaTest/all///0';
        $crawler = $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($response->headers->get('content-type'), 'application/json');

        // This test account *should* never edit again and be safe for testing...
        $this->assertCount(1, json_decode($response->getContent(), true)['nonautomated_edits']);

        // Test again for HTML
        $crawler = $this->client->request('GET', $url . '?format=html');
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('text/html', $response->headers->get('content-type'));
    }

    /**
     * articleinfo endpoint, used for the XTools gadget
     */
    public function testArticleInfo()
    {
        if (!$this->isSingle && $this->container->getParameter('app.is_labs')) {
            $crawler = $this->client->request('GET', '/api/page/articleinfo/en.wikipedia.org/Main_Page');

            $response = $this->client->getResponse();
            $this->assertEquals(200, $response->getStatusCode());

            $data = json_decode($response->getContent(), true);

            // Some basic tests that should always hold true
            $this->assertEquals($data['project'], 'en.wikipedia.org');
            $this->assertEquals($data['page'], 'Main Page');
            $this->assertTrue($data['revisions'] > 4000);
            $this->assertTrue($data['editors'] > 400);
            $this->assertEquals($data['author'], 'TwoOneTwo');
            $this->assertEquals($data['created_at'], '2002-01-26');
            $this->assertEquals($data['created_rev_id'], 139992);

            $this->assertEquals(
                [
                    'project', 'page', 'revisions', 'editors', 'author', 'author_editcount',
                    'created_at', 'created_rev_id', 'modified_at', 'secs_since_last_edit',
                    'last_edit_id', 'watchers', 'pageviews', 'pageviews_offset',
                ],
                array_keys($data)
            );
        }
    }
}
