<?php

namespace Xtools;

use DateTime;

class EditTest extends \PHPUnit_Framework_TestCase
{

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
