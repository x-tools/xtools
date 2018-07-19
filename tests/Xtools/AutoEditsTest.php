<?php
/**
 * This file contains only the AutoEditsTest class.
 */

namespace Tests\Xtools;

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Xtools\AutoEdits;
use Xtools\AutoEditsRepository;
use Xtools\Edit;
use Xtools\Page;
use Xtools\Project;
use Xtools\ProjectRepository;
use Xtools\User;
use Xtools\UserRepository;

/**
 * Tests for the AutoEdits class.
 */
class AutoEditsTest extends WebTestCase
{
    /** @var Container The DI container. */
    protected $container;

    /** @var Client HTTP client */
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
     * The constructor.
     */
    public function testConstructor()
    {
        $autoEdits = new AutoEdits(
            $this->project,
            $this->user,
            1,
            strtotime('2017-01-01'),
            strtotime('2018-01-01'),
            'Twinkle',
            50
        );

        static::assertEquals(1, $autoEdits->getNamespace());
        static::assertEquals('2017-01-01', $autoEdits->getStart());
        static::assertEquals('2018-01-01', $autoEdits->getEnd());
        static::assertEquals('Twinkle', $autoEdits->getTool());
        static::assertEquals(50, $autoEdits->getOffset());
    }

    /**
     * User's non-automated edits
     */
    public function testGetNonAutomatedEdits()
    {
        $rev = [
            'page_title' => 'Test_page',
            'page_namespace' => '0',
            'rev_id' => '123',
            'timestamp' => '20170101000000',
            'minor' => '0',
            'length' => '5',
            'length_change' => '-5',
            'comment' => 'Test',
        ];

        $this->aeRepo->expects($this->exactly(2))
            ->method('getNonAutomatedEdits')
            ->willReturn([$rev]);

        $autoEdits = new AutoEdits($this->project, $this->user, 1);
        $autoEdits->setRepository($this->aeRepo);

        $rawEdits = $autoEdits->getNonAutomatedEdits(true);
        static::assertArraySubset($rev, $rawEdits[0]);

        $edit = new Edit(
            new Page($this->project, 'Test_page'),
            array_merge($rev, ['user' => $this->user])
        );
        static::assertEquals($edit, $autoEdits->getNonAutomatedEdits()[0]);

        // One more time to ensure things are re-queried.
        static::assertEquals($edit, $autoEdits->getNonAutomatedEdits()[0]);
    }

    /**
     * Test fetching the tools and counts.
     */
    public function testToolCounts()
    {
        $toolCounts = [
            'Twinkle' => [
                'link' => 'Project:Twinkle',
                'label' => 'Twinkle',
                'count' => '13',
            ],
            'HotCat' => [
                'link' => 'Special:MyLanguage/Project:HotCat',
                'label' => 'HotCat',
                'count' => '5',
            ],
        ];

        $this->aeRepo->expects($this->once())
            ->method('getToolCounts')
            ->willReturn($toolCounts);
        $autoEdits = new AutoEdits($this->project, $this->user, 1);
        $autoEdits->setRepository($this->aeRepo);

        static::assertEquals($toolCounts, $autoEdits->getToolCounts());
        static::assertEquals(18, $autoEdits->getToolsTotal());
    }

    /**
     * User's (semi-)automated edits
     */
    public function testGetAutomatedEdits()
    {
        $rev = [
            'page_title' => 'Test_page',
            'page_namespace' => '1',
            'rev_id' => '123',
            'timestamp' => '20170101000000',
            'minor' => '0',
            'length' => '5',
            'length_change' => '-5',
            'comment' => 'Test ([[WP:TW|TW]])',
        ];

        $this->aeRepo->expects($this->exactly(2))
            ->method('getAutomatedEdits')
            ->willReturn([$rev]);

        $autoEdits = new AutoEdits($this->project, $this->user, 1);
        $autoEdits->setRepository($this->aeRepo);

        $rawEdits = $autoEdits->getAutomatedEdits(true);
        static::assertArraySubset($rev, $rawEdits[0]);

        $edit = new Edit(
            new Page($this->project, 'Talk:Test_page'),
            array_merge($rev, ['user' => $this->user])
        );
        static::assertEquals($edit, $autoEdits->getAutomatedEdits()[0]);

        // One more time to ensure things are re-queried.
        static::assertEquals($edit, $autoEdits->getAutomatedEdits()[0]);
    }

    /**
     * Counting non-automated edits.
     */
    public function testCounts()
    {
        $this->aeRepo->expects($this->once())
            ->method('countAutomatedEdits')
            ->willReturn('50');
        $userRepo = $this->getMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('countEdits')
            ->willReturn(200);
        $this->user->setRepository($userRepo);

        $autoEdits = new AutoEdits($this->project, $this->user, 1);
        $autoEdits->setRepository($this->aeRepo);
        static::assertEquals(50, $autoEdits->getAutomatedCount());
        static::assertEquals(200, $autoEdits->getEditCount());
        static::assertEquals(25, $autoEdits->getAutomatedPercentage());

        // Again to ensure they're not re-queried.
        static::assertEquals(50, $autoEdits->getAutomatedCount());
        static::assertEquals(200, $autoEdits->getEditCount());
        static::assertEquals(25, $autoEdits->getAutomatedPercentage());
    }

    /**
     * Test automated edit counter endpoint.
     */
    public function testAutomatedEditCount()
    {
        if ($this->isSingle || !$this->container->getParameter('app.is_labs')) {
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

//    /**
//     * Test nonautomated edits endpoint.
//     */
//    public function testNonautomatedEdits()
//    {
//        if ($this->isSingle || !$this->container->getParameter('app.is_labs')) {
//            // untestable :(
//            return;
//        }
//
//        $url = '/api/user/nonautomated_edits/en.wikipedia/ThisIsaTest/all///0';
//        $crawler = $this->client->request('GET', $url);
//        $response = $this->client->getResponse();
//        static::assertEquals(200, $response->getStatusCode());
//        static::assertEquals('application/json', $response->headers->get('content-type'));
//
//        // This test account *should* never edit again and be safe for testing...
//        static::assertCount(1, json_decode($response->getContent(), true)['nonautomated_edits']);
//
//        // Test again for too many edits.
//        $url = '/api/user/nonautomated_edits/en.wikipedia/Materialscientist/0';
//        $this->client->request('GET', $url);
//        $response = $this->client->getResponse();
//        static::assertEquals(500, $response->getStatusCode());
//    }
}
