<?php

declare( strict_types = 1 );

namespace App\Tests\Controller;

/**
 * Integration/unit tests for the MetaController.
 * @group integration
 * @covers \App\Controller\MetaController
 */
class MetaControllerTest extends ControllerTestAdapter {
	/**
	 * Test that the Meta index page displays correctly.
	 */
	public function testIndex(): void {
		$this->client->request( 'GET', '/meta' );
		static::assertEquals( 200, $this->client->getResponse()->getStatusCode() );

		if ( !static::getContainer()->getParameter( 'app.is_wmf' ) ) {
			return;
		}

		// Should redirect since we have supplied all necessary parameters.
		$this->client->request( 'GET', '/meta?start=2017-10-01&end=2017-10-10' );
		static::assertEquals( 302, $this->client->getResponse()->getStatusCode() );
	}
}
