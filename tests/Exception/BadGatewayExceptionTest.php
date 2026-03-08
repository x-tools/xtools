<?php

declare( strict_types = 1 );

namespace App\Tests\Exception;

use App\Exception\BadGatewayException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Exception\BadGatewayException
 */
class BadGatewayExceptionTest extends TestCase {
	public function testMsgParams(): void {
		$exception = new BadGatewayException( 'api-error-wikimedia', [ 'REST' ] );
		static::assertEquals( [ 'REST' ], $exception->getMsgParams() );
		static::assertEquals( 'api-error-wikimedia', $exception->getMessage() );
	}
}
