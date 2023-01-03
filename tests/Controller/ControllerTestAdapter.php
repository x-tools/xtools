<?php

declare(strict_types=1);

namespace App\Tests\Controller;

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
        date_default_timezone_set('UTC');
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

    /**
     * Check that each given route returns a successful response.
     * @param string[] $routes
     * @param int|null $statusCode
     */
    public function assertUnsuccessfulRoutes(array $routes, ?int $statusCode = null): void
    {
        foreach ($routes as $route) {
            $this->client->request('GET', $route);
            static::assertEquals($statusCode, $this->client->getResponse()->getStatusCode(), "Failed: $route");
        }
    }

    /**
     * PHPUnit 6+ warns when there are no assertions in a test.
     * Tests that connect to the replicas don't run in CI, so here we fake that assertions were made.
     */
    public function tearDown(): void
    {
        if (!self::$container->getParameter('app.is_wmf')) {
            $this->addToAssertionCount(1);
        }
        parent::tearDown();
    }
}
