<?php

namespace Tests\AppBundle\Helper;

use AppBundle\Helper\LabsHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

class LabsHelperTest extends WebTestCase
{

    /** @var Container */
    protected $container;

    /** @var LabsHelper */
    protected $labsHelper;

    public function setUp()
    {
        $client = static::createClient();
        $this->container = $client->getContainer();
        $this->labsHelper = new LabsHelper($this->container);
    }

    public function testGetTable()
    {
        if ($this->container->getParameter('app.is_labs')) {
            // When using Labs.
            $this->assertEquals('_p.page', $this->labsHelper->getTable('page'));
            $this->assertEquals('_p.logging_logindex', $this->labsHelper->getTable('logging'));
        } else {
            // When using wiki databases directly.
            $this->assertEquals('page', $this->labsHelper->getTable('page'));
            $this->assertEquals('logging', $this->labsHelper->getTable('logging'));
        }
    }
}
