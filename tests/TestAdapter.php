<?php
declare(strict_types = 1);

namespace App\Tests;

use App\Repository\ProjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parent test adapter to shamelessly reimplement the deprecated PHPUnit_Framework_TestCase::getMock().
 */
class TestAdapter extends WebTestCase
{

    /**
     * Get a mocked ProjectRepository with some dummy data.
     * @return MockObject|ProjectRepository
     */
    public function getProjectRepo(): MockObject
    {
        /** @var MockObject|ProjectRepository $repo */
        $repo = $this->createMock(ProjectRepository::class);
        $repo->method('getOne')
            ->willReturn([
                'url' => 'https://test.example.org',
                'dbName' => 'test_wiki',
                'lang' => 'en',
            ]);
        return $repo;
    }
}
