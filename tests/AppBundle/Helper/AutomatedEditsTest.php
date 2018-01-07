<?php
/**
 * This file contains tests for the AutomatedEditsHelper class.
 */

namespace Tests\AppBundle\Helper;

use AppBundle\Helper\AutomatedEditsHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * Tests of the AutomatedEditsHelper class.
 * @group integration
 */
class AutomatedEditsTest extends WebTestCase
{
    /** @var Container The DI container. */
    protected $container;

    /** @var AutomatedEditsHelper The API Helper object to test. */
    protected $aeh;

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
        $tools = $this->aeh->getTools('en.wikipedia.org');

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
                'en.wikipedia.org'
            )
        );
    }

    /**
     * Tests that given edit summary is properly asserted as a revert
     */
    public function testIsAutomated()
    {
        $this->assertTrue($this->aeh->isAutomated(
            'Level 2 warning re. [[Barack Obama]] ([[WP:HG|HG]]) (3.2.0)',
            'en.wikipedia.org'
        ));
        $this->assertFalse($this->aeh->isAutomated(
            'You should try [[WP:Huggle]]',
            'en.wikipedia.org'
        ));
    }

    /**
     * Test that the revert-related tools of getTools() are properly fetched
     */
    public function testRevertTools()
    {
        $tools = $this->aeh->getTools('en.wikipedia.org');

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
        $tools = $this->aeh->getTools('ar.wikipedia.org');

        $this->assertArraySubset(
            ['HotCat' => [
                'regex' => 'باستخدام \[\[ويكيبيديا:المصناف الفوري|using ' .
                    '\[\[(WP:HOTCAT|WP:HC|Help:Gadget-HotCat)\|HotCat|' .
                    'Gadget-Hotcat(?:check)?\.js\|Script',
                'link' => 'ويكيبيديا:المصناف الفوري',
            ]],
            $tools
        );
    }

    /**
     * Was the edit a revert, based on the edit summary?
     */
    public function testIsRevert()
    {
        $this->assertTrue($this->aeh->isRevert(
            'Reverted edits by Mogultalk (talk) ([[WP:HG|HG]]) (3.2.0)',
            'en.wikipedia.org'
        ));
        $this->assertFalse($this->aeh->isRevert(
            'You should have reverted this edit using [[WP:HG|Huggle]]',
            'en.wikipedia.org'
        ));
    }
}
