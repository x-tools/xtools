<?php
/**
 * This file contains only the RepositoryTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Repository;

use AppBundle\Model\Project;
use AppBundle\Model\User;
use AppBundle\Repository\Repository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tests\AppBundle\TestAdapter;

/**
 * Tests for the Repository class.
 */
class RepositoryTest extends TestAdapter
{
    /** @var ContainerInterface The DI container. */
    protected $localContainer;

    /** @var \PHPUnit_Framework_MockObject_MockObject|Repository Mock of an abstract Repository class. */
    private $stub;

    protected function setUp(): void
    {
        $this->stub = $this->getMockForAbstractClass('AppBundle\Repository\Repository');

        $client = static::createClient();
        $this->localContainer = $client->getContainer();
        $this->stub->setContainer($this->localContainer);
    }

    /**
     * Test that the table-name transformations are correct.
     */
    public function testGetTableName(): void
    {
        if ($this->localContainer->getParameter('app.is_labs')) {
            // When using Labs.
            static::assertEquals('`testwiki_p`.`page`', $this->stub->getTableName('testwiki', 'page'));
            static::assertEquals('`testwiki_p`.`logging_userindex`', $this->stub->getTableName('testwiki', 'logging'));
        } else {
            // When using wiki databases directly.
            static::assertEquals('`testwiki`.`page`', $this->stub->getTableName('testwiki', 'page'));
            static::assertEquals('`testwiki`.`logging`', $this->stub->getTableName('testwiki', 'logging'));
        }
    }

    /**
     * Test getting a unique cache key for a given set of arguments.
     */
    public function testCacheKey(): void
    {
        // Set up example Models that we'll pass to Repository::getCacheKey().
        $project = $this->getMock(Project::class, ['getCacheKey'], ['enwiki']);
        $project->method('getCacheKey')->willReturn('enwiki');
        $user = new User('Test user (WMF)');

        // Given explicit cache prefix.
        static::assertEquals(
            'cachePrefix.enwiki.f475a8ac7f25e162bba0eb1b4b245027.a84e19e5268bf01623c8a130883df668.123',
            $this->stub->getCacheKey(
                [$project, $user, '20170101', '', null, [1, 2, 3]],
                'cachePrefix'
            )
        );

        // It will use the name of the caller, in this case testCacheKey.
        static::assertEquals(
            // The `false` argument generates the trailing `.`
            'testCacheKey.enwiki.f475a8ac7f25e162bba0eb1b4b245027.' .
                'a84e19e5268bf01623c8a130883df668.d41d8cd98f00b204e9800998ecf8427e',
            $this->stub->getCacheKey([$project, $user, '20170101', '', false, null])
        );

        // Single argument, no prefix.
        static::assertEquals(
            'testCacheKey.838763cbdc764f1740370a8ee1000c65',
            $this->stub->getCacheKey('mycache')
        );
    }

    /**
     * SQL date conditions helper.
     */
    public function testDateConditions(): void
    {
        $start = strtotime('20170101');
        $end = strtotime('20170201');
        static::assertEquals(
            " AND alias.rev_timestamp >= '20170101000000' AND alias.rev_timestamp <= '20170201235959'",
            $this->stub->getDateConditions($start, $end, 'alias.')
        );
    }
}
