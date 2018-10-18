<?php
/**
 * This file contains only the TopEditsControllerTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Controller;

/**
 * Integration tests for the Top Edits tool.
 * @group integration
 */
class TopEditsControllerTest extends ControllerTestAdapter
{
    /**
     * Test that the form can be retrieved.
     */
    public function testIndex(): void
    {
        // Check basics.
        $this->client->request('GET', '/topedits');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        // For now...
        if (!self::$container->getParameter('app.is_labs')) {
            return;
        }

        // Should populate the appropriate fields.
        $crawler = $this->client->request('GET', '/topedits/de.wikipedia.org?namespace=3&article=Test');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        static::assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
        static::assertEquals(3, $crawler->filter('#namespace_select option:selected')->attr('value'));
        static::assertEquals('Test', $crawler->filter('#article_input')->attr('value'));

        // Legacy URL params.
        $crawler = $this->client->request('GET', '/topedits/?namespace=5&page=Test&wiki=wikipedia&lang=fr');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        static::assertEquals('fr.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
        static::assertEquals(5, $crawler->filter('#namespace_select option:selected')->attr('value'));
        static::assertEquals('Test', $crawler->filter('#article_input')->attr('value'));
    }

    /**
     * Test all other routes.
     */
    public function testRoutes(): void
    {
        if (!self::$container->getParameter('app.is_labs')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/topedits/enwiki/Example',
            '/topedits/enwiki/Example/1',
            '/topedits/enwiki/Example/1/Main Page',
            '/api/user/topedits/en.wikipedia/Example/1',
            '/api/user/topedits/en.wikipedia/Example/1/Main_Page',

            // Former but now nonexistent namespace.
            '/topedits/en.wikipedia/L235/447',
        ]);
    }
}
