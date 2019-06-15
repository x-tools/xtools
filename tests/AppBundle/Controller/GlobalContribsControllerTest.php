<?php

declare(strict_types=1);

namespace Tests\AppBundle\Controller;

/**
 * Integration tests for GlobalContribsController.
 */
class GlobalContribsControllerTest extends ControllerTestAdapter
{
    /**
     * Test that each route returns a successful response.
     */
    public function testRoutes(): void
    {
        if (!self::$container->getParameter('app.is_labs')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/globalcontribs',
            '/globalcontribs/Example',
        ]);
    }
}
