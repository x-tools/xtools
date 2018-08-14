<?php
/**
 * This file contains only the EditCounterControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DependencyInjection\Container;

/**
 * Integration tests for the EditCounterController.
 * @group integration
 */
class EditCounterControllerTest extends WebTestCase
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
    }

    /**
     * Test that the Edit Counter index page displays correctly.
     */
    public function testIndex()
    {
        $this->client->request('GET', '/ec');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        // For now...
        if (!$this->container->getParameter('app.is_labs') || $this->container->getParameter('app.single_wiki')) {
            return;
        }

        $crawler = $this->client->request('GET', '/ec/de.wikipedia.org');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        // should populate project input field
        static::assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
    }

    /**
     * Test that the Edit Counter index pages for the subtools are shown correctly.
     */
    public function testSubtoolIndexes()
    {
        // Cookies should not affect the index pages of subtools.
        $cookie = new Cookie('XtoolsEditCounterOptions', 'general-stats');
        $this->client->getCookieJar()->set($cookie);

        $subtools = [
            'general-stats', 'namespace-totals', 'year-counts', 'month-counts',
            'timecard', 'rights-changes', 'latest-global-edits'
        ];

        foreach ($subtools as $subtool) {
            $crawler = $this->client->request('GET', '/ec-'.str_replace('-', '', $subtool));
            static::assertEquals(200, $this->client->getResponse()->getStatusCode());
            static::assertEquals(1, count($crawler->filter('.checkbox input:checked')));
            static::assertEquals($subtool, $crawler->filter('.checkbox input:checked')->attr('value'));
        }
    }

    /**
     * Test setting of section preferences that are stored in a cookie.
     */
    public function testCookies()
    {
        // For now...
        if (!$this->container->getParameter('app.is_labs')) {
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
}
