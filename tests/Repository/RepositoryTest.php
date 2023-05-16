<?php

declare(strict_types = 1);

namespace App\Tests\Repository;

use App\Model\Project;
use App\Model\User;
use App\Repository\Repository;
use App\Repository\SimpleEditCounterRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;

/**
 * Tests for the Repository class.
 * @covers \App\Repository\Repository
 */
class RepositoryTest extends TestAdapter
{
    protected SimpleEditCounterRepository $repository;
    protected UserRepository $userRepo;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->repository = static::getContainer()->get(SimpleEditCounterRepository::class);
        $this->userRepo = static::getContainer()->get(UserRepository::class);
    }

    /**
     * Test that the table-name transformations are correct.
     */
    public function testGetTableName(): void
    {
        if (static::getContainer()->getParameter('app.is_wmf')) {
            // When using Labs.
            static::assertEquals('`testwiki_p`.`page`', $this->repository->getTableName('testwiki', 'page'));
            static::assertEquals(
                '`testwiki_p`.`logging_userindex`',
                $this->repository->getTableName('testwiki', 'logging')
            );
        } else {
            // When using wiki databases directly.
            static::assertEquals('`testwiki`.`page`', $this->repository->getTableName('testwiki', 'page'));
            static::assertEquals('`testwiki`.`logging`', $this->repository->getTableName('testwiki', 'logging'));
        }
    }

    /**
     * Test getting a unique cache key for a given set of arguments.
     */
    public function testCacheKey(): void
    {
        // Set up example Models that we'll pass to Repository::getCacheKey().
        $project = $this->createMock(Project::class);
        $project->method('getCacheKey')->willReturn('enwiki');
        $user = new User($this->userRepo, 'Test user (WMF)');

        // Given explicit cache prefix.
        static::assertEquals(
            'cachePrefix.enwiki.f475a8ac7f25e162bba0eb1b4b245027.'.
                'a84e19e5268bf01623c8a130883df668.202cb962ac59075b964b07152d234b70',
            $this->repository->getCacheKey(
                [$project, $user, '20170101', '', null, [1, 2, 3]],
                'cachePrefix'
            )
        );

        // It will use the name of the caller, in this case testCacheKey.
        static::assertEquals(
            // The `false` argument generates the trailing `.`
            'testCacheKey.enwiki.f475a8ac7f25e162bba0eb1b4b245027.' .
                'a84e19e5268bf01623c8a130883df668.d41d8cd98f00b204e9800998ecf8427e',
            $this->repository->getCacheKey([$project, $user, '20170101', '', false, null])
        );

        // Single argument, no prefix.
        static::assertEquals(
            'testCacheKey.838763cbdc764f1740370a8ee1000c65',
            $this->repository->getCacheKey('mycache')
        );
    }

    /**
     * SQL date conditions helper.
     */
    public function testDateConditions(): void
    {
        $start = strtotime('20170101');
        $end = strtotime('20190201');
        $offset = strtotime('20180201235959');

        static::assertEquals(
            " AND alias.rev_timestamp >= '20170101000000' AND alias.rev_timestamp <= '20190201235959'",
            $this->repository->getDateConditions($start, $end, false, 'alias.')
        );

        static::assertEquals(
            " AND rev_timestamp >= '20170101000000' AND rev_timestamp <= '20180201235959'",
            $this->repository->getDateConditions($start, $end, $offset)
        );
    }
}
