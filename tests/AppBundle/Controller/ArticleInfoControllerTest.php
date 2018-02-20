<?php
/**
 * This file contains only the ArticleInfoControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use AppBundle\Controller\ArticleInfoController;

/**
 * Integration/unit tests for the ArticleInfoController.
 * @group integration
 */
class ArticleInfoControllerTest extends WebTestCase
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
        $this->controller = new ArticleInfoController();
        $this->controller->setContainer($this->container);
    }

    /**
     * Test that the AdminStats index page displays correctly.
     */
    public function testIndex()
    {
        $crawler = $this->client->request('GET', '/articleinfo/de.wikipedia');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        if (!$this->container->getParameter('app.is_labs') || $this->container->getParameter('app.single_wiki')) {
            return;
        }

        // should populate project input field
        $this->assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
    }

    /**
     * Test the method that sets up a AdminStats instance.
     */
    public function testArticleInfoApi()
    {
        // For now...
        if (!$this->container->getParameter('app.is_labs') || $this->container->getParameter('app.single_wiki')) {
            return;
        }

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
                'project', 'page', 'watchers', 'pageviews', 'pageviews_offset',
                'revisions', 'editors', 'author', 'author_editcount', 'created_at',
                'created_rev_id', 'modified_at', 'secs_since_last_edit', 'last_edit_id'
            ],
            array_keys($data)
        );
    }
}
