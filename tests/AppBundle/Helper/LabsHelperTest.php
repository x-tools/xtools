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
            $this->assertEquals('_p.logging_userindex', $this->labsHelper->getTable('logging'));
        } else {
            // When using wiki databases directly.
            $this->assertEquals('page', $this->labsHelper->getTable('page'));
            $this->assertEquals('logging', $this->labsHelper->getTable('logging'));
        }
    }

    public function testDatabasePrepare()
    {
        if ($this->container->getParameter('app.is_labs')) {
            // When using Labs.
            $dbVales = $this->labsHelper->databasePrepare('en.wikipedia');
            $this->assertEquals('enwiki', $dbVales['dbName']);
            $this->assertEquals('https://en.wikipedia.org', $dbVales['url']);
        }
    }

    public function testNormalizeProject()
    {
        if ($this->container->getParameter('app.is_labs')) {
            // When using Labs.
            $this->assertEquals('en.wikipedia.org', $this->labsHelper->normalizeProject('enwiki'));
            $this->assertEquals('en.wikipedia.org', $this->labsHelper->normalizeProject('en.wikipedia'));
            $this->assertEquals(false, $this->labsHelper->normalizeProject('invalid.wiki'));
        }
    }
}
