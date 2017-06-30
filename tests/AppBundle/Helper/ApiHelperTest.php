<?php
/**
 * This file contains only the ApiHelperTest class.
 */

namespace Tests\AppBundle\Helper;

use AppBundle\Helper\ApiHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * Tests of the ApiHelper class.
 * @group integration
 */
class ApiHelperTest extends WebTestCase
{

    /** @var Container The DI container. */
    protected $container;

    /** @var ApiHelper The API Helper object to test. */
    protected $apiHelper;

    /**
     * Set up the ApiHelper object for testing.
     */
    public function setUp()
    {
        $client = static::createClient();
        $this->container = $client->getContainer();
        $this->apiHelper = new ApiHelper($this->container);
        $this->cache = $this->container->get('cache.app');
    }

    public function testGroups()
    {
        // placeholder
    }
}
