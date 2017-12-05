<?php
/**
 * This file contains only the AutoEditsTest class.
 */

namespace Tests\Xtools;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\AutoEdits;
use Xtools\AutoEditsRepository;
use DateTime;

/**
 * Tests for the AutoEdits class.
 */
class AutoEditsTest extends WebTestCase
{
    /** @var Container The DI container. */
    protected $container;

    /** @var Symfony\Bundle\FrameworkBundle\Client HTTP client */
    protected $client;

    /** @var Project The project instance. */
    protected $project;

    /** @var ProjectRepository The project repo instance. */
    protected $projectRepo;

    /** @var AutoEditsRepository The AutoEdits repo instance. */
    protected $aeRepo;

    /** @var User The user instance. */
    protected $user;

    /** @var bool Whether we're testing a single-wiki setup */
    protected $isSingle;

    /**
     * Set up container, class instances and mocks.
     */
    public function setUp()
    {
        $this->client = static::createClient();
        $this->container = $this->client->getContainer();
        $this->isSingle = $this->container->getParameter('app.single_wiki');
        $this->project = new Project('wiki.example.org');
        $this->projectRepo = $this->getMock(ProjectRepository::class);
        $this->projectRepo->method('getMetadata')
            ->willReturn(['namespaces' => [
                '0' => '',
                '1' => 'Talk',
            ]]);
        $this->project->setRepository($this->projectRepo);
        $this->user = new User('Test user');
        $this->aeRepo = $this->getMock(AutoEditsRepository::class);
    }

    /**
     * User's non-automated edits
     */
    public function testGetNonAutomatedEdits()
    {
        $this->aeRepo->expects($this->once())
            ->method('getNonAutomatedEdits')
            ->willReturn([[
                'full_page_title' => 'Talk:Test_page',
                'page_title' => 'Test_page',
                'page_namespace' => '1',
                'rev_id' => '123',
                'timestamp' => '20170101000000',
                'minor' => '0',
                'length' => '5',
                'length_change' => '-5',
                'comment' => 'Test',
            ]]);

        $autoEdits = new AutoEdits($this->project, $this->user, 1);
        $autoEdits->setRepository($this->aeRepo);

        $edits = $autoEdits->getNonAutomatedEdits($this->project, 1);

        // Asserts type casting and page title normalization worked
        $this->assertArraySubset(
            [
                'full_page_title' => 'Talk:Test_page',
                'page_title' => 'Test_page',
                'page_namespace' => 1,
                'rev_id' => 123,
                'timestamp' => DateTime::createFromFormat('YmdHis', '20170101000000'),
                'minor' => false,
                'length' => 5,
                'length_change' => -5,
                'comment' => 'Test',
            ],
            $edits[0]
        );
    }

    /**
     * Counting non-automated edits.
     */
    public function testCountAutomatedEdits()
    {
        $this->aeRepo->expects($this->once())
            ->method('countAutomatedEdits')
            ->willReturn('50');
        $autoEdits = new AutoEdits($this->project, $this->user, 1);
        $autoEdits->setRepository($this->aeRepo);
        $this->assertEquals(50, $autoEdits->countAutomatedEdits());
    }

    /**
     * Test automated edit counter endpoint.
     */
    public function testAutomatedEditCount()
    {
        if ($this->isSingle || !$this->container->getParameter('app.is_labs')) {
            // untestable :(
            return;
        }

        $url = '/api/user/automated_editcount/en.wikipedia/musikPuppet/all///1';
        $crawler = $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($response->headers->get('content-type'), 'application/json');

        $data = json_decode($response->getContent(), true);
        $toolNames = array_keys($data['automated_tools']);

        $this->assertEquals($data['project'], 'en.wikipedia.org');
        $this->assertEquals($data['username'], 'MusikPuppet');
        $this->assertGreaterThan(15, $data['automated_editcount']);
        $this->assertGreaterThan(35, $data['nonautomated_editcount']);
        $this->assertEquals(
            $data['automated_editcount'] + $data['nonautomated_editcount'],
            $data['total_editcount']
        );
        $this->assertContains('Twinkle', $toolNames);
        $this->assertContains('Huggle', $toolNames);
    }

    /**
     * Test nonautomated edits endpoint.
     */
    public function testNonautomatedEdits()
    {
        if ($this->isSingle || !$this->container->getParameter('app.is_labs')) {
            // untestable :(
            return;
        }

        $url = '/api/user/nonautomated_edits/en.wikipedia/ThisIsaTest/all///0';
        $crawler = $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($response->headers->get('content-type'), 'application/json');

        // This test account *should* never edit again and be safe for testing...
        $this->assertCount(1, json_decode($response->getContent(), true)['nonautomated_edits']);

        // Test again for HTML
        $crawler = $this->client->request('GET', $url . '?format=html');
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('text/html', $response->headers->get('content-type'));

        // Test again for too many edits.
        $url = '/api/user/nonautomated_edits/en.wikipedia/Materialscientist/0';
        $crawler = $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        $this->assertEquals(403, $response->getStatusCode());
    }
}
