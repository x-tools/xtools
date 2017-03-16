<?php

namespace Tests\AppBundle\Helper;

use AppBundle\Helper\LabsHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LabsHelperTest extends WebTestCase
{

    public function testGetTable()
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $lh = new LabsHelper($container);

        if ($container->getParameter('app.is_labs')) {
            // When using Labs.
            $this->assertEquals('page', $lh->getTable('page'));
            $this->assertEquals('logging_userindex', $lh->getTable('logging'));
        } else {
            // When using wiki databases directly.
            $this->assertEquals('page', $lh->getTable('page'));
            $this->assertEquals('logging', $lh->getTable('logging'));
        }
    }
}
