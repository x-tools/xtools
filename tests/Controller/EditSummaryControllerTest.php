<?php

declare(strict_types = 1);

namespace App\Tests\Controller;

/**
 * Integration/unit tests for the ArticleInfoController.
 * @group integration
 * @covers \App\Controller\EditSummaryController
 */
class EditSummaryControllerTest extends ControllerTestAdapter
{
    /**
     * Test that the Edit Summaries index page displays correctly.
     */
    public function testIndex(): void
    {
        $crawler = $this->client->request('GET', '/editsummary/de.wikipedia');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        // should populate project input field
        static::assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
    }

    /**
     * Test all other routes return successful responses.
     */
    public function testRoutes(): void
    {
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/editsummary/en.wikipedia/Example',
            '/editsummary/en.wikipedia/Example/1',
            '/api/user/edit_summaries/en.wikipedia/Example/1',
        ]);
    }
}
