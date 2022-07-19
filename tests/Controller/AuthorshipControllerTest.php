<?php
declare(strict_types = 1);

namespace App\Tests\Controller;

/**
 * Integration tests for the ArticleInfoController.
 */
class AuthorshipControllerTest extends ControllerTestAdapter
{
    /**
     * Check response codes of index and result pages.
     */
    public function testHtmlRoutes(): void
    {
        if (!self::$container->getParameter('app.is_labs')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/authorship',
            '/authorship/de.wikipedia.org',
            '/authorship/en.wikipedia.org/Hanksy/2016-01-01',
        ]);
    }
}
