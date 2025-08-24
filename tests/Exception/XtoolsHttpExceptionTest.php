<?php

declare(strict_types = 1);

namespace App\Tests\Exception;

use App\Exception\XtoolsHttpException;
use PHPUnit\Framework\TestCase;

class XtoolsHttpExceptionTest extends TestCase
{
    public function testParams(): void
    {
        $exception = new XtoolsHttpException('Hello!', '/', []);
        static::assertEquals('/', $exception->getRedirectUrl());
        static::assertEquals([], $exception->getParams());
        static::assertFalse($exception->isApi());
    }
}
