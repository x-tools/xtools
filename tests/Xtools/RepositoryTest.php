<?php
/**
 * This file contains only the RepositoryTest class.
 */

namespace Tests\Xtools;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Xtools\Repository;
use Xtools\Project;
use Xtools\User;
use Xtools\Page;
use Xtools\Edit;

/**
 * Tests for the Repository class.
 */
class RepositoryTest extends WebTestCase
{
    /** @var Container The DI container. */
    protected $container;

    /** @var MockRepository Mock of an abstract Repository class. */
    private $stub;

    protected function setUp()
    {
        $this->stub = $this->getMockForAbstractClass('Xtools\Repository');

        $client = static::createClient();
        $this->container = $client->getContainer();
        $this->stub->setContainer($this->container);
    }

    /**
     * Test that the table-name transformations are correct.
     */
    public function testGetTableName()
    {
        if ($this->container->getParameter('app.is_labs')) {
            // When using Labs.
            $this->assertEquals('`testwiki_p`.`page`', $this->stub->getTableName('testwiki', 'page'));
            $this->assertEquals('`testwiki_p`.`logging_userindex`', $this->stub->getTableName('testwiki', 'logging'));
        } else {
            // When using wiki databases directly.
            $this->assertEquals('`testwiki`.`page`', $this->stub->getTableName('testwiki', 'page'));
            $this->assertEquals('`testwiki`.`logging`', $this->stub->getTableName('testwiki', 'logging'));
        }
    }

    /**
     * Make sure the logger was set and is accessible.
     */
    public function testLogger()
    {
        $this->assertInstanceOf(\Symfony\Bridge\Monolog\Logger::class, $this->stub->getLog());
    }

    /**
     * Ensure the we're able to query the XTools API, and the correct type of class is returned.
     */
    public function testQueryXToolsApi()
    {
        if (!$this->container->getParameter('app.is_labs') || !$this->container->getParameter('app.multithread')) {
            return;
        }

        $apiObj = $this->stub->queryXToolsApi('ec/monthcounts/en.wikipedia.org/Example');
        $this->assertInstanceOf(\GuzzleHttp\Psr7\Response::class, $apiObj);
        $this->assertEquals(200, $apiObj->getStatusCode());

        $apiObj2 = $this->stub->queryXToolsApi('ec/monthcounts/en.wikipedia.org/Example', true);
        $this->assertInstanceOf(\GuzzleHttp\Promise\Promise::class, $apiObj2);
    }

    /**
     * Test getting a unique cache key for a given set of arguments.
     */
    public function testCacheKey()
    {
        // Set up example Models that we'll pass to Repository::getCacheKey().
        $project = $this->getMock(Project::class, ['getCacheKey'], ['enwiki']);
        $project->method('getCacheKey')->willReturn('enwiki');
        $user = $this->getMock(User::class, ['getCacheKey'], ['Test user']);
        $user->method('getCacheKey')->willReturn('Test_user');

        // Given explicit cache prefix.
        $this->assertEquals(
            'cachePrefix.enwiki.Test_user.20170101.123',
            $this->stub->getCacheKey(
                [$project, $user, '20170101', '', null, [1, 2, 3]],
                'cachePrefix'
            )
        );

        // It will use the name of the caller, in this case testCacheKey.
        $this->assertEquals(
            // The `false` argument generates the trailing `.`
            'testCacheKey.enwiki.Test_user.20170101.',
            $this->stub->getCacheKey([$project, $user, '20170101', '', false, null])
        );

        // Single argument, no prefix.
        $this->assertEquals(
            'testCacheKey.mycache',
            $this->stub->getCacheKey('mycache')
        );
    }
}
