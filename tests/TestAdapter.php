<?php

declare(strict_types = 1);

namespace App\Tests;

use App\Model\Project;
use App\Repository\ProjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parent test adapter to shamelessly reimplement the deprecated PHPUnit_Framework_TestCase::getMock().
 */
class TestAdapter extends WebTestCase
{

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
        return $repo;
    }

    protected function getMockEnwikiProject(): Project
    {
        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->method('getOne')
            ->willReturn([
                "url" => "https://en.wikipedia.org/w/api.php",
            ]);
        $projectRepo->method('getMetadata')
            ->willReturn([
                "general" => [
                    "mainpage" => "Main Page",
                    "scriptPath" => "/w",
                ],
            ]);
        $project = new Project('en.wikipedia.org');
        $project->setRepository($projectRepo);
        return $project;
    }
}
