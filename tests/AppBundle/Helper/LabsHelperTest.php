<?php
/**
 * This file contains only the LabsHelperTest class.
 */

namespace Tests\AppBundle\Helper;

use AppBundle\Helper\LabsHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * The Labs helper provides information relating to the WMF Labs installation of XTools.
 */
class LabsHelperTest extends WebTestCase
{

    /** @var Container The DI container. */
    protected $container;

    /** @var LabsHelper The  */
    protected $labsHelper;

    /**
     * Set up the tests.
     */
    public function setUp()
    {
        $client = static::createClient();
        $this->container = $client->getContainer();
        $this->labsHelper = new LabsHelper($this->container);
    }

    /**
     * Test that the table-name transformations are correct.
     */
    public function testGetTable()
    {
        if ($this->container->getParameter('app.is_labs')) {
            // When using Labs.
            $this->assertEquals('_p.page', $this->labsHelper->getTable('page'));
            $this->assertEquals('_p.logging_userindex', $this->labsHelper->getTable('logging'));
        } else {
            // When using wiki databases directly.
            $this->assertEquals('page', $this->labsHelper->getTable('page'));
            $this->assertEquals('logging', $this->labsHelper->getTable('logging'));
        }
    }
}
