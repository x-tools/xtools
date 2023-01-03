<?php

declare(strict_types=1);

namespace App\Tests\Controller;

/**
 * Integration tests for GlobalContribsController.
 * @group integration
 * @covers \App\Controller\GlobalContribsController
 */
class GlobalContribsControllerTest extends ControllerTestAdapter
{
    /**
     * Test that each route returns a successful response.
     */
    public function testRoutes(): void
    {
        if (!self::$container->getParameter('app.is_wmf')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/globalcontribs',
            '/globalcontribs/Example',
            '/api/user/globalcontribs/Example',
        ]);
    }
}
