<?php
/**
 * This file contains only the DefaultControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests for the homepage and user authentication.
 * @group integration
 */
class DefaultControllerTest extends WebTestCase
{

    /**
     * Test that the homepage is served, including in multiple languages.
     */
    public function testIndex()
    {
        $client = static::createClient();

        // Check basics.
        $crawler = $client->request('GET', '/');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertContains('XTools', $crawler->filter('.splash-logo')->attr('alt'));

        // Change language.
        $crawler = $client->request('GET', '/?uselang=es');
        $this->assertContains(
            'Herramientas de X!',
            $crawler->filter('.splash-logo')->attr('alt')
        );

        // Make sure all active tools are listed.
        $this->assertCount(9, $crawler->filter('.tool-list a.btn'));
    }

    /**
     * Test that the about page is served.
     */
    public function testAbout()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/about');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    /**
     * OAuth callback action.
     */
    public function testOAuthCallback()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/oauth_callback');

        // Callback should 404 since we didn't give it anything.
        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }

    /**
     * Logout action.
     */
    public function testLogout()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/logout');
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }
}
