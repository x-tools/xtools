<?php
/**
 * This file contains only the PagesControllerTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Controller;

/**
 * Integration tests for the PagesController.
 * @group integration
 */
class PagesControllerTest extends ControllerTestAdapter
{
    /**
     * Test that the Pages tool index page displays correctly.
     */
    public function testIndex(): void
    {
        $this->client->request('GET', '/pages');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        if (self::$container->getParameter('app.is_labs')) {
            $crawler = $this->client->request('GET', '/pages/de.wikipedia.org');
            static::assertEquals(200, $this->client->getResponse()->getStatusCode());

            // should populate project input field
            static::assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));

            // assert that the namespaces were correctly loaded from API
            $namespaceOptions = $crawler->filter('#namespace_select option');
            static::assertEquals('Diskussion', trim($namespaceOptions->eq(2)->text())); // Talk in German
        }
    }

    /**
     * Test that all other routes return successful responses.
     */
    public function testRoutes(): void
    {
        if (!self::$container->getParameter('app.is_labs')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/pages/en.wikipedia/Example',
            '/pages/en.wikipedia/Example/0',
            '/pages/en.wikipedia/Example/0/noredirects/all/5',
            '/pages/en.wikipedia/Foobar/0/noredirects/all/5?format=wikitext',
            '/pages/en.wikipedia/Foobar/0/noredirects/all/5?format=csv',
            '/pages/en.wikipedia/Foobar/0/noredirects/all/5?format=tsv',
            '/api/user/pages_count/en.wikipedia/Example/0/noredirects/deleted',
        ]);
    }
}
