<?php

declare(strict_types = 1);

namespace App\Tests;

use App\Helper\AutomatedEditsHelper;
use App\Model\Project;
use App\Repository\ProjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parent test adapter to shamelessly reimplement the deprecated PHPUnit_Framework_TestCase::getMock().
 */
class TestAdapter extends WebTestCase
{
    use SessionHelper;

    /**
     * Get a mocked ProjectRepository with some dummy data.
     * @return MockObject|ProjectRepository
     */
    public function getProjectRepo(): MockObject
    {
        /** @var MockObject|ProjectRepository $repo */
        $repo = $this->createMock(ProjectRepository::class);
        $repo->method('getOne')
            ->willReturn([
                'url' => 'https://test.example.org',
                'dbName' => 'test_wiki',
                'lang' => 'en',
            ]);
        $repo->method('checkReplication')
            ->willReturn(true);
        return $repo;
    }

    /**
     * Get a Project object for en.wikipedia.org
     * @return Project
     */
    protected function getMockEnwikiProject(): Project
    {
        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->method('getOne')
            ->willReturn([
                'url' => 'https://en.wikipedia.org/w/api.php',
            ]);
        $projectRepo->method('getMetadata')
            ->willReturn([
                'general' => [
                    'mainpage' => 'Main Page',
                    'scriptPath' => '/w',
                ],
                'tempAccountPatterns' => ['~2$1'],
            ]);
        $project = new Project('en.wikipedia.org');
        $project->setRepository($projectRepo);
        return $project;
    }

    /**
     * Get an AutomatedEditsHelper with the session properly set.
     * @param KernelBrowser|null $client
     * @return AutomatedEditsHelper
     */
    protected function getAutomatedEditsHelper(?KernelBrowser $client = null): AutomatedEditsHelper
    {
        $client = $client ?? static::createClient();
        $session = $this->createSession($client);
        return new AutomatedEditsHelper(
            $this->getRequestStack($session),
            static::getContainer()->get('cache.app'),
            static::getContainer()->get('eight_points_guzzle.client.xtools')
        );
    }
}
