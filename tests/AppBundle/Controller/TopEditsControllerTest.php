<?php
/**
 * This file contains only the TopEditsControllerTest class.
 */

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests for the Top Edits tool.
 * @group integration
 */
class TopEditsControllerTest extends WebTestCase
{

    /**
     * Test that the form can be retrieved.
     */
    public function testIndex()
    {
        $client = static::createClient();

        // Check basics.
        $crawler = $client->request('GET', '/topedits');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }
}
