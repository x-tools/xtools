<?php
/**
 * This file contains only the ProjectTest class.
 */

namespace Tests\Xtools;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Xtools\Repository;

/**
 * Tests for the Repository class.
 */
class RepositoryTest extends WebTestCase
{
    /** @var MockRepository Mock of an abstract Repository class. */
    private $stub;

    protected function setUp()
    {
        $this->stub = $this->getMockForAbstractClass('Xtools\Repository');
    }

    /**
     * Test that the table-name transformations are correct.
     */
    public function testGetTableName()
    {
        $client = static::createClient();
        $this->container = $client->getContainer();

        $this->stub->setContainer($this->container);

        if ($this->container->getParameter('app.is_labs')) {
            // When using Labs.
            $this->assertEquals('`testwiki_p`.`page`', $this->stub->getTableName('testwiki', 'page'));
            $this->assertEquals('`testwiki_p`.`logging_userindex`', $this->stub->getTableName('testwiki', 'logging'));
        } else {
            // When using wiki databases directly.
            $this->assertEquals('`page`', $this->stub->getTableName('testwiki', 'page'));
            $this->assertEquals('`logging`', $this->stub->getTableName('testwiki', 'logging'));
        }
    }
}
