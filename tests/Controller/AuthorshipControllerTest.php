<?php

declare(strict_types = 1);

namespace App\Tests\Controller;

/**
 * Integration tests for the ArticleInfoController.
 * @group integration
 * @covers \App\Controller\AuthorshipController
 */
class AuthorshipControllerTest extends ControllerTestAdapter
{
    /**
     * Check response codes of index and result pages.
     */
    public function testHtmlRoutes(): void
    {
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/authorship',
            '/authorship/de.wikipedia.org',
            '/authorship/en.wikipedia.org/Hanksy/2016-01-01',
        ]);
    }
}
