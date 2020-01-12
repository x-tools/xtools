<?php
/**
 * This file contains only the EditCounterControllerTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Controller;

use Symfony\Component\BrowserKit\Cookie;

/**
 * Integration tests for the EditCounterController.
 * @group integration
 */
class EditCounterControllerTest extends ControllerTestAdapter
{
    /**
     * Test that the Edit Counter index pages display correctly.
     */
    public function testIndexPages(): void
    {
        $this->client->request('GET', '/ec');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        // For now...
        if (!self::$container->getParameter('app.is_labs')) {
            return;
        }

        $crawler = $this->client->request('GET', '/ec/de.wikipedia.org');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        // Should populate project input field.
        static::assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));

        $routes = [
            '/ec-generalstats',
            '/ec-namespacetotals',
            '/ec-timecard',
            '/ec-yearcounts',
            '/ec-monthcounts',
            '/ec-rightschanges',
            '/ec-latestglobal',
        ];

        foreach ($routes as $route) {
            $this->client->request('GET', $route);
            static::assertTrue($this->client->getResponse()->isSuccessful(), "Failed: $route");
        }
    }

    /**
     * Test that the Edit Counter index pages and redirects for the subtools are correct.
     */
    public function testSubtools(): void
    {
        // Cookies should not affect the index pages of subtools.
        $cookie = new Cookie('XtoolsEditCounterOptions', 'general-stats');
        $this->client->getCookieJar()->set($cookie);

        $subtools = [
            'general-stats', 'namespace-totals', 'year-counts', 'month-counts', 'timecard', 'rights-changes',
        ];

        foreach ($subtools as $subtool) {
            $crawler = $this->client->request('GET', '/ec-'.str_replace('-', '', $subtool));
            static::assertEquals(200, $this->client->getResponse()->getStatusCode());
            static::assertEquals(1, count($crawler->filter('.checkbox input:checked')));
            static::assertEquals($subtool, $crawler->filter('.checkbox input:checked')->attr('value'));
        }

        // For now...
        if (!self::$container->getParameter('app.is_labs')) {
            return;
        }

        // Requesting only one subtool should redirect to the dedicated route.
        $this->client->request('GET', '/ec/en.wikipedia/Example?sections=rights-changes');
        static::assertTrue($this->client->getResponse()->isRedirect('/ec-rightschanges/en.wikipedia/Example'));
    }

    /**
     * Test setting of section preferences that are stored in a cookie.
     */
    public function testCookies(): void
    {
        // For now...
        if (!self::$container->getParameter('app.is_labs')) {
            return;
        }

        $cookie = new Cookie('XtoolsEditCounterOptions', 'year-counts|rights-changes');
        $this->client->getCookieJar()->set($cookie);

        // Index page should have only the 'general stats' and 'rights changes' options checked.
        $crawler = $this->client->request('GET', '/ec');
        static::assertEquals(
            ['year-counts', 'rights-changes'],
            $crawler->filter('.checkbox input:checked')->extract(['value'])
        );

        // Fill in username and project then submit.
        $form = $crawler->selectButton('Submit')->form();
        $form['project'] = 'en.wikipedia';
        $form['username'] = 'Example';
        $this->client->submit($form);

        // Make sure only the requested sections are shown.
        static::assertEquals(302, $this->client->getResponse()->getStatusCode());
        $crawler = $this->client->followRedirect();
        static::assertCount(2, $crawler->filter('.xt-toc a'));
        static::assertContains('Year counts', $crawler->filter('.xt-toc')->text());
        static::assertContains('Rights changes', $crawler->filter('.xt-toc')->text());
    }

    /**
     * Check that the result pages return successful responses.
     */
    public function testResultPages(): void
    {
        if (!self::$container->getParameter('app.is_labs')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/ec/en.wikipedia/Example',
            '/ec-generalstats/en.wikipedia/Example',
            '/ec-namespacetotals/en.wikipedia/Example',
            '/ec-timecard/en.wikipedia/Example',
            '/ec-yearcounts/en.wikipedia/Example',
            '/ec-monthcounts/en.wikipedia/Example',
            '/ec-rightschanges/en.wikipedia/Example',
            '/ec-latestglobal/en.wikipedia/Example',
        ]);
    }

    /**
     * Test that API endpoints return a successful response.
     */
    public function testApis(): void
    {
        if (!self::$container->getParameter('app.is_labs')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/api/user/log_counts/enwiki/Example',
            '/api/user/namespace_totals/enwiki/Example',
            '/api/user/month_counts/enwiki/Example',
            '/api/user/timecard/enwiki/Example',
        ]);
    }
}
