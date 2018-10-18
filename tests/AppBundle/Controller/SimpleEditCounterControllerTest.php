<?php
/**
 * This file contains only the SimpleEditCounterControllerTest class.
 */

declare(strict_types = 1);

namespace Tests\AppBundle\Controller;

/**
 * Integration/unit tests for the ArticleInfoController.
 * @group integration
 */
class SimpleEditCounterControllerTest extends ControllerTestAdapter
{
    /**
     * Test that all routes return successful responses.
     */
    public function testRoutes(): void
    {
        if (!self::$container->getParameter('app.is_labs')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/sc',
            '/sc/enwiki',
            '/sc/en.wikipedia/Example',
            '/sc/en.wikipedia/Example/1/2018-01-01/2018-02-01',
            '/api/user/simple_editcount/en.wikipedia.org/Example/1/2018-01-01/2018-02-01',
        ]);
    }
}
