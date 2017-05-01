<?php

namespace Tests\AppBundle\Helper;

use AppBundle\Helper\ApiHelper;
use AppBundle\Helper\LabsHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

class ApiHelperTest extends WebTestCase
{

    /** @var Container */
    protected $container;

    /** @var ApiHelper */
    protected $apiHelper;

    public function setUp()
    {
        $client = static::createClient();
        $this->container = $client->getContainer();
        $labsHelper = new LabsHelper($this->container);
        $this->apiHelper = new ApiHelper($this->container, $labsHelper);
        $this->cache = $this->container->get('cache.app');
    }

    public function testSiteInfo()
    {
        if ($this->container->getParameter('app.is_labs')) {
            $siteInfo = $this->apiHelper->getSiteInfo('enwiki');
            $this->assertEquals($siteInfo['general']['articlePath'], '/wiki/$1');
        }
    }

    public function testNamespaces()
    {
        if ($this->container->getParameter('app.is_labs')) {
            $namespaces = $this->apiHelper->namespaces('enwiki');
            $this->assertEquals($namespaces['1'], 'Talk');
            $this->assertEquals($namespaces['2'], 'User');
        }
    }
}
