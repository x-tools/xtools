<?php
/**
 * This file contains only the EditTest class.
 */

namespace Tests\Xtools;

use DateTime;
use Xtools\Edit;
use Xtools\Page;
use Xtools\Project;

/**
 * Tests of the Edit class.
 */
class EditTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Test the basic functionality of Edit.
     */
    public function testBasic()
    {
        $project = new Project('TestProject');
        $page = new Page($project, 'Test_page');
        $edit = new Edit($page, [
            'id' => '1',
            'timestamp' => '20170101100000',
            'minor' => '0',
            'length' => '12',
            'length_change' => '2',
            'username' => 'Testuser',
            'comment' => 'Test',
        ]);
        $this->assertEquals($project, $edit->getProject());
        $this->assertInstanceOf(DateTime::class, $edit->getTimestamp());
        $this->assertEquals($page, $edit->getPage());
        $this->assertEquals('1483264800', $edit->getTimestamp()->getTimestamp());
        $this->assertEquals(1, $edit->getId());
        $this->assertFalse($edit->isMinor());
    }
}
