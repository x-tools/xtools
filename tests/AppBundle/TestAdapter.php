<?php
declare(strict_types = 1);

namespace Tests\AppBundle;

use AppBundle\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parent test adapter to shamelessly reimplement the deprecated PHPUnit_Framework_TestCase::getMock().
 */
class TestAdapter extends WebTestCase
{
    /**
     * Override deprecated method.
     * @param array|string $originalClassName
     * @param array|null $methods
     * @param array $arguments
     * @param string $mockClassName
     * @param bool $callOriginalConstructor
     * @param bool $callOriginalClone
     * @param bool $callAutoload
     * @param bool $cloneArguments
     * @param bool $callOriginalMethods
     * @param null $proxyTarget
     * @param bool $allowMockingUnknownTypes
     * @return \PHPUnit_Framework_MockObject_MockObject
     * @FIXME: This is just a hack to restore the deprecated method.
     */
    public function getMock(
        $originalClassName,
        $methods = [],
        $arguments = [],
        $mockClassName = '',
        $callOriginalConstructor = true,
        $callOriginalClone = true,
        $callAutoload = true,
        $cloneArguments = false,
        $callOriginalMethods = false,
        $proxyTarget = null,
        bool $allowMockingUnknownTypes = true
    ): \PHPUnit_Framework_MockObject_MockObject {
        $mockObject = $this->getMockObjectGenerator()->getMock(
            $originalClassName,
            $methods,
            $arguments,
            $mockClassName,
            $callOriginalConstructor,
            $callOriginalClone,
            $callAutoload,
            $cloneArguments,
            $callOriginalMethods,
            $proxyTarget
        );

        $this->registerMockObject($mockObject);

        return $mockObject;
    }

    /**
     * Get a mocked ProjectRepository with some dummy data.
     * @return \PHPUnit_Framework_MockObject_MockObject|ProjectRepository
     */
    public function getProjectRepo(): \PHPUnit_Framework_MockObject_MockObject
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|ProjectRepository $repo */
        $repo = $this->getMock(ProjectRepository::class);
        $repo->method('getOne')
            ->willReturn([
                'url' => 'https://test.example.org',
                'dbName' => 'test_wiki',
                'lang' => 'en',
            ]);
        return $repo;
    }
}
