<?php

declare( strict_types=1 );

namespace App\Tests\Controller;

/**
 * Integration tests for the CategoryEditsController.
 * @group integration
 * @covers \App\Controller\CategoryEditsController
 */
class CategoryEditsControllerTest extends ControllerTestAdapter {
	/**
	 * Test that each route returns a successful response.
	 */
	public function testRoutes(): void {
		if ( !static::getContainer()->getParameter( 'app.is_wmf' ) ) {
			return;
		}

		$this->assertSuccessfulRoutes( [
			'/categoryedits',
			'/categoryedits/en.wikipedia',
			'/categoryedits/en.wikipedia/Example/Insects/2018-01-01/2018-02-01',
			'/categoryedits-contributions/en.wikipedia/Example/Insects/2018-01-01/2018-02-01/5',
			'/api/user/category_editcount/en.wikipedia/Example/Insects/2018-01-01/2018-02-01',
		] );
	}
}
