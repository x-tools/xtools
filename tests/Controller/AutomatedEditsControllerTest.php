<?php

declare(strict_types = 1);

namespace App\Tests\Controller;

/**
 * Integration tests for the Auto Edits tool.
 * @group integration
 * @covers \App\Controller\AutomatedEditsController
 */
class AutomatedEditsControllerTest extends ControllerTestAdapter
{
    /**
     * Test that the form can be retrieved.
     */
    public function testIndex(): void
    {
        // Check basics.
        $this->client->request('GET', '/autoedits');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());

        // For now...
        if (!static::getContainer()->getParameter('app.is_wmf') ||
            static::getContainer()->getParameter('app.single_wiki')
        ) {
            return;
        }

        // Should populate the appropriate fields.
        $crawler = $this->client->request('GET', '/autoedits/de.wikipedia.org?namespace=3&end=2017-01-01');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        static::assertEquals('de.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
        static::assertEquals(3, $crawler->filter('#namespace_select option:selected')->attr('value'));
        static::assertEquals('2017-01-01', $crawler->filter('[name=end]')->attr('value'));

        // Legacy URL params.
        $crawler = $this->client->request('GET', '/autoedits?project=fr.wikipedia.org&namespace=5&begin=2017-02-01');
        static::assertEquals(200, $this->client->getResponse()->getStatusCode());
        static::assertEquals('fr.wikipedia.org', $crawler->filter('#project_input')->attr('value'));
        static::assertEquals(5, $crawler->filter('#namespace_select option:selected')->attr('value'));
        static::assertEquals('2017-02-01', $crawler->filter('[name=start]')->attr('value'));
    }

    /**
     * Check that the result pages return successful responses.
     */
    public function testResultPages(): void
    {
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        $this->assertSuccessfulRoutes([
            '/autoedits/en.wikipedia/Example',
            '/autoedits/en.wikipedia/Example/1/2018-01-01/2018-02-01',
            '/nonautoedits-contributions/en.wikipedia/Example/1/2018-01-01/2018-02-01/2018-01-15T12:00:00',
            '/autoedits-contributions/en.wikipedia/Example/1/2018-01-01/2018-02-01/2018-01-15T12:00:00',
        ]);
    }

    /**
     * Check that the APIs return successful responses.
     */
    public function testApis(): void
    {
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            return;
        }

        // Non-automated edits endpoint is tested in self::testNonautomatedEdits().
        $this->assertSuccessfulRoutes([
            '/api/user/automated_tools/en.wikipedia',
            '/api/user/automated_editcount/en.wikipedia/Example/1/2018-01-01/2018-02-01/2018-01-15T12:00:00',
            '/api/user/automated_edits/en.wikipedia/Example/1/2018-01-01/2018-02-01/2018-01-15T12:00:00',
        ]);
    }

    /**
     * Test automated edit counter endpoint.
     */
    public function testAutomatedEditCount(): void
    {
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            // Untestable :(
            return;
        }

        $url = '/api/user/automated_editcount/en.wikipedia/musikPuppet/all///1';
        $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        static::assertEquals(200, $response->getStatusCode());
        static::assertEquals('application/json', $response->headers->get('content-type'));

        $data = json_decode($response->getContent(), true);
        $toolNames = array_keys($data['automated_tools']);

        static::assertEquals($data['project'], 'en.wikipedia.org');
        static::assertEquals($data['username'], 'musikPuppet');
        static::assertGreaterThan(15, $data['automated_editcount']);
        static::assertGreaterThan(35, $data['nonautomated_editcount']);
        static::assertEquals(
            $data['automated_editcount'] + $data['nonautomated_editcount'],
            $data['total_editcount']
        );
        static::assertContains('Twinkle', $toolNames);
        static::assertContains('Huggle', $toolNames);
    }

    /**
     * Test nonautomated edits endpoint.
     */
    public function testNonautomatedEdits(): void
    {
        if (!static::getContainer()->getParameter('app.is_wmf')) {
            // untestable :(
            return;
        }

        $url = '/api/user/nonautomated_edits/en.wikipedia/ThisIsaTest/all';
        $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        static::assertEquals(200, $response->getStatusCode());
        static::assertEquals('application/json', $response->headers->get('content-type'));

        // This test account *should* never edit again and be safe for testing...
        static::assertCount(1, json_decode($response->getContent(), true)['nonautomated_edits']);
    }
}
