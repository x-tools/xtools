<?php

declare(strict_types = 1);

namespace App\Tests\Controller;

/**
 * Integration/unit tests for the ArticleInfoController.
 * @group integration
 * @covers \App\Controller\SimpleEditCounterController
 */
class SimpleEditCounterControllerTest extends ControllerTestAdapter
{
    /**
     * Test that all routes return successful responses.
     */
    public function testRoutes(): void
    {
        if (!self::$container->getParameter('app.is_wmf')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/sc',
            '/sc/enwiki',
            '/sc/en.wikipedia/Example',
            '/sc/en.wikipedia/Example/1/2018-01-01/2018-02-01',
            '/sc/en.wikipedia/ipr-174.197.128.0/1/2018-01-01/2018-02-01',
            '/api/user/simple_editcount/en.wikipedia.org/Example/1/2018-01-01/2018-02-01',
        ]);
    }
}
