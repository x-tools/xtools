<?php
/**
 * This file contains only the ApiHelperTest class.
 */

namespace Tests\AppBundle\Helper;

use AppBundle\Helper\ApiHelper;
use AppBundle\Helper\LabsHelper;
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
        $labsHelper = new LabsHelper($this->container);
        $this->apiHelper = new ApiHelper($this->container, $labsHelper);
        $this->cache = $this->container->get('cache.app');
    }
}
