<?php

namespace Xtools;

class EditCounterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Get counts of revisions: deleted, not-deleted, and total.
     */
    public function testLiveAndDeletedEdits()
    {
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())
            ->method('getRevisionCounts')
            ->willReturn([
                'deleted' => 10,
                'live' => 100,
            ]);

        $project = new Project('TestProject');
        $user = new User('Testuser');
        $editCounter = new EditCounter($project, $user);
        $editCounter->setRepository($editCounterRepo);

        $this->assertEquals(100, $editCounter->getLiveEditCount());
        $this->assertEquals(10, $editCounter->getDeletedEditCount());
        $this->assertEquals(110, $editCounter->getTotalEditCount());
    }

    /**
     * A first and last date, and number of days between.
     */
    public function testDates()
    {
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())
            ->method('getRevisionDates')
            ->willReturn([
                'first' => '20170510100000',
                'last' => '20170515150000',
            ]);
        $project = new Project('TestProject');
        $user = new User('Testuser1');
        $editCounter = new EditCounter($project, $user);
        $editCounter->setRepository($editCounterRepo);
        $this->assertEquals(
            new \DateTime('2017-05-10 10:00'),
            $editCounter->getFirstEditDatetime()
        );
        $this->assertEquals(
            new \DateTime('2017-05-15 15:00'),
            $editCounter->getLastEditDatetime()
        );
        $this->assertEquals(5, $editCounter->getDays());

        // Only one edit means the dates will be the same.
        $editCounterRepo2 = $this->getMock(EditCounterRepository::class);
        $editCounterRepo2->expects($this->once())
            ->method('getRevisionDates')
            ->willReturn([
                'first' => '20170510110000',
                'last' => '20170510110000',
            ]);
        $editCounter2 = new EditCounter($project, $user);
        $editCounter2->setRepository($editCounterRepo2);
        $this->assertEquals(
            new \DateTime('2017-05-10 11:00'),
            $editCounter2->getFirstEditDatetime()
        );
        $this->assertEquals(
            new \DateTime('2017-05-10 11:00'),
            $editCounter2->getLastEditDatetime()
        );
        $this->assertEquals(1, $editCounter2->getDays());
    }
}
