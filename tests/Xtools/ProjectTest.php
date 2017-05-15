<?php

namespace Xtools;

use PHPUnit_Framework_TestCase;

class ProjectTest extends PHPUnit_Framework_TestCase
{

    /**
     * A project has its own domain name, database name, URL, script path, and article path.
     */
    public function testBasicMetadata()
    {
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getOne')
            ->willReturn(['url' => 'https://test.example.org', 'dbname' => 'test_wiki']);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        $this->assertEquals('test.example.org', $project->getDomain());
        $this->assertEquals('test_wiki', $project->getDatabaseName());
        $this->assertEquals('https://test.example.org/', $project->getUrl());
        $this->assertEquals('/w/index.php', $project->getScriptPath());
        $this->assertEquals('/wiki/', $project->getArticlePath());
    }

    /**
     * A project has a set of namespaces, comprising integer IDs and string titles.
     */
    public function testNamespaces()
    {
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getMetadata')
            ->willReturn(['namespaces' => [0 => 'Article', 1 => 'Article_talk']]);

        $project = new Project('testWiki');
        $project->setRepository($projectRepo);
        $this->assertCount(2, $project->getNamespaces());
    }

    /**
     * Xtools can be run in single-wiki mode, where there is only one project.
     */
    public function testSingleWiki()
    {
        $projectRepo = new ProjectRepository();
        $projectRepo->setSingleMetadata([
            'url' => 'https://example.org/a-wiki/',
            'dbname' => 'example_wiki',
        ]);
        $project = new Project('disregarded_wiki_name');
        $project->setRepository($projectRepo);
        $this->assertEquals('example_wiki', $project->getDatabaseName());
        $this->assertEquals('https://example.org/a-wiki/', $project->getUrl());
    }
}
