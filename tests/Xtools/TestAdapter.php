<?php

namespace Tests\Xtools;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parent test adapter to shamelessly reimplement the deprecated PHPUnit_Framework_TestCase::getMock().
 */
class TestAdapter extends WebTestCase
{
    /**
     * Override deprecated method.
     * @param string $originalClassName
     * @param array $methods
     * @param array $arguments
     * @param string $mockClassName
     * @param bool $callOriginalConstructor
     * @param bool $callOriginalClone
     * @param bool $callAutoload
     * @param bool $cloneArguments
     * @param bool $callOriginalMethods
     * @param null $proxyTarget
     * @return \PHPUnit_Framework_MockObject_MockObject
     * @FIXME: This is just a hack to restore the deprecated method.
     */
    public function getMock(
        $originalClassName,
        $methods = [],
        array $arguments = [],
        $mockClassName = '',
        $callOriginalConstructor = true,
        $callOriginalClone = true,
        $callAutoload = true,
        $cloneArguments = false,
        $callOriginalMethods = false,
        $proxyTarget = null
    ) {
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
}
