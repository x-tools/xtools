<?php
/**
 * This file contains only the ControllerTestAdapter class.
 */

declare(strict_types=1);

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * This class sets the container, client and provides some convenience methods.
 * All controller test classes should extend this one.
 */
class ControllerTestAdapter extends WebTestCase
{
    /** @var Client The Symfony client */
    protected $client;

    /**
     * Set up the container and client.
     */
    public function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Check that each given route returns a successful response.
     * @param string[] $routes
     */
    public function assertSuccessfulRoutes(array $routes): void
    {
        foreach ($routes as $route) {
            $this->client->request('GET', $route);
            static::assertTrue($this->client->getResponse()->isSuccessful(), "Failed: $route");
        }
    }
}
