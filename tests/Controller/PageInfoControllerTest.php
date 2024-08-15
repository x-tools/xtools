<?php

declare(strict_types = 1);

namespace App\Tests\Controller;

/**
 * Integration/unit tests for the PageInfoController.
 * @group integration
 * @covers \App\Controller\PageInfoController
 */
class PageInfoControllerTest extends ControllerTestAdapter
{
    /**
     * Test that the AdminStats index page displays correctly when given a project.
     */
    public function testProjectIndex(): void
    {
        $crawler = $this->client->request('GET', '/pageinfo/de.wikipedia');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        // should populate project input field
        static::assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
    }

    /**
     * Test the method that sets up a AdminStats instance.
     */
    public function testPageInfoApi(): void
    {
        // For now...
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        $this->client->request('GET', '/api/page/pageinfo/en.wikipedia.org/Main_Page');

        $response = $this->client->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        // Some basic tests that should always hold true.
        static::assertEquals($data['project'], 'en.wikipedia.org');
        static::assertEquals($data['page'], 'Main Page');
        static::assertTrue($data['revisions'] > 4000);
        static::assertTrue($data['editors'] > 400);
        static::assertEquals($data['creator'], 'TwoOneTwo');
        static::assertEquals($data['created_at'], '2002-01-26T15:28:12Z');
        static::assertEquals($data['created_rev_id'], 139992);

        static::assertEquals(
            [
                'warning', 'project', 'page', 'watchers', 'pageviews', 'pageviews_offset',  'revisions',
                'editors', 'ip_edits', 'minor_edits', 'creator', 'creator_editcount', 'created_at',  'created_rev_id',
                'modified_at', 'secs_since_last_edit', 'modified_rev_id', 'assessment', 'last_edit_id',
                'author', 'author_editcount', 'elapsed_time',
            ],
            array_keys($data)
        );
    }

    /**
     * Check response codes of index and result pages.
     */
    public function testHtmlRoutes(): void
    {
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/pageinfo',
            '/pageinfo/en.wikipedia.org/Ravine du Sud',
            '/pageinfo/en.wikipedia.org/Ravine du Sud/2018-01-01',
            '/pageinfo/en.wikipedia.org/Ravine du Sud/2018-01-01?format=wikitext',
        ]);

        // Should redirect because there are no revisions.
        $this->client->request('GET', '/pageinfo/en.wikipedia.org/Ravine du Sud/'.date('Y-m-d'));
        static::assertTrue($this->client->getResponse()->isRedirect());
    }

    /**
     * Check response codes of other API endpoints.
     */
    public function testApis(): void
    {
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/api/page/pageinfo/en.wikipedia/Ravine_du_Sud?format=html',
            '/api/page/prose/en.wikipedia/Ravine_du_Sud',
            '/api/page/assessments/en.wikipedia/Ravine_du_Sud',
            '/api/page/links/en.wikipedia/Ravine_du_Sud',
            '/api/page/top_editors/en.wikipedia/Ravine_du_Sud',
            '/api/page/top_editors/en.wikipedia/Ravine_du_Sud/2018-01-01/2018-02-01',
            '/api/page/bot_data/en.wikipedia/Ravine_du_Sud',
            '/api/page/automated_edits/enwiki/Ravine_du_Sud',
        ]);
    }
}
