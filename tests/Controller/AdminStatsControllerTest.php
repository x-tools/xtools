<?php

declare(strict_types = 1);

namespace App\Tests\Controller;

/**
 * Integration tests for the AdminStatsController.
 * @group integration
 * @covers \App\Controller\AdminStatsController
 */
class AdminStatsControllerTest extends ControllerTestAdapter
{
    /**
     * Check response codes of index and result pages.
     */
    public function testHtmlRoutes(): void
    {
        if (!self::$container->getParameter('app.is_wmf')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/adminstats',
            '/adminstats/fr.wikipedia.org',
            '/adminstats/fr.wikipedia.org//2018-01-10',
            '/stewardstats/meta.wikimedia.org/2018-01-01/2018-01-10?actions=global-rights',
        ]);
    }

    /**
     * Check response codes of API endpoints.
     */
    public function testApis(): void
    {
        if (!self::$container->getParameter('app.is_wmf')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/api/project/admins_groups/fr.wikipedia',
            '/api/project/admin_stats/frwiki/2019-01-01',
        ]);
    }
}
