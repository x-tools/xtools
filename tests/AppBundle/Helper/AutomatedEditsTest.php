<?php
/**
 * This file contains tests for the AutomatedEditsHelper class.
 */

namespace Tests\AppBundle\Helper;

use AppBundle\Helper\AutomatedEditsHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Tests\Xtools\TestAdapter;
use Xtools\Project;
use Xtools\ProjectRepository;

/**
 * Tests of the AutomatedEditsHelper class.
 * @group integration
 */
class AutomatedEditsTest extends TestAdapter
{
    /** @var Container The DI container. */
    protected $container;

    /** @var AutomatedEditsHelper The API Helper object to test. */
    protected $aeh;

    /** @var Project The project against which we are testing. */
    protected $project;

    /**
     * Set up the AutomatedEditsHelper object for testing.
     */
    public function setUp()
    {
        $client = static::createClient();
        $this->container = $client->getContainer();
        $this->aeh = new AutomatedEditsHelper($this->container);
        $this->cache = $this->container->get('cache.app');
    }

    /**
     * Test that the merge of per-wiki config and global config works
     */
    public function testTools()
    {
        $this->setProject();
        $tools = $this->aeh->getTools($this->project);

        $this->assertArraySubset(
            [
                'regex' => '\(\[\[WP:HG',
                'tag' => 'huggle',
                'link' => 'w:en:Wikipedia:Huggle',
                'revert' => 'Reverted edits by.*?WP:HG',
            ],
            $tools['Huggle']
        );

        $this->assertEquals(1, array_count_values(array_keys($tools))['Huggle']);
    }

    /**
     * Make sure the right tool is detected
     */
    public function testTool()
    {
        $this->setProject();
        $this->assertArraySubset(
            [
                'name' => 'Huggle',
                'regex' => '\(\[\[WP:HG',
                'tag' => 'huggle',
                'link' => 'w:en:Wikipedia:Huggle',
                'revert' => 'Reverted edits by.*?WP:HG',
            ],
            $this->aeh->getTool(
                'Level 2 warning re. [[Barack Obama]] ([[WP:HG|HG]]) (3.2.0)',
                $this->project
            )
        );
    }

    /**
     * Tests that given edit summary is properly asserted as a revert
     */
    public function testIsAutomated()
    {
        $this->setProject();
        $this->assertTrue($this->aeh->isAutomated(
            'Level 2 warning re. [[Barack Obama]] ([[WP:HG|HG]]) (3.2.0)',
            $this->project
        ));
        $this->assertFalse($this->aeh->isAutomated(
            'You should try [[WP:Huggle]]',
            $this->project
        ));
    }

    /**
     * Test that the revert-related tools of getTools() are properly fetched
     */
    public function testRevertTools()
    {
        $this->setProject();
        $tools = $this->aeh->getTools($this->project);

        $this->assertArraySubset(
            ['Huggle' => [
                'regex' => '\(\[\[WP:HG',
                'tag' => 'huggle',
                'link' => 'w:en:Wikipedia:Huggle',
                'revert' => 'Reverted edits by.*?WP:HG',
            ]],
            $tools
        );

        $this->assertContains('Undo', array_keys($tools));
    }

    /**
     * Test that regex is properly concatenated when merging rules.
     */
    public function testRegexConcat()
    {
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getOne')
            ->willReturn([
                'url' => 'https://ar.wikipedia.org',
                'dbName' => 'arwiki',
                'lang' => 'ar',
            ]);
        $project = new Project('ar.wikipedia.org');
        $project->setRepository($projectRepo);

        $tools = $this->aeh->getTools($project);

        $this->assertArraySubset(
            ['HotCat' => [
                'regex' => 'باستخدام \[\[ويكيبيديا:المصناف الفوري|\|HotCat\]\]' .
                    '|Gadget-Hotcat(?:check)?\.js\|Script|\]\] via HotCat',
                'link' => 'ويكيبيديا:المصناف الفوري',
                'label' => 'المصناف الفوري',
            ]],
            $this->aeh->getTools($project)
        );
    }

    /**
     * Was the edit a revert, based on the edit summary?
     */
    public function testIsRevert()
    {
        $this->setProject();
        $this->assertTrue($this->aeh->isRevert(
            'Reverted edits by Mogultalk (talk) ([[WP:HG|HG]]) (3.2.0)',
            $this->project
        ));
        $this->assertFalse($this->aeh->isRevert(
            'You should have reverted this edit using [[WP:HG|Huggle]]',
            $this->project
        ));
    }

    /**
     * Set the Project. This is done here because we don't want to use
     * en.wikipedia for self::testRegexConcat().
     */
    private function setProject()
    {
        $projectRepo = $this->getMock(ProjectRepository::class);
        $projectRepo->expects($this->once())
            ->method('getOne')
            ->willReturn([
                'url' => 'https://en.wikipedia.org',
                'dbName' => 'enwiki',
                'lang' => 'en',
            ]);
        $this->project = new Project('en.wikipedia.org');
        $this->project->setRepository($projectRepo);
    }
}
