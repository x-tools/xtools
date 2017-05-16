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

        $this->assertEquals(100, $editCounter->countLiveRevisions());
        $this->assertEquals(10, $editCounter->countDeletedRevisions());
        $this->assertEquals(110, $editCounter->countAllRevisions());
    }

    /**
     * A first and last date, and number of days between.
     */
    public function testDates()
    {
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())->method('getRevisionDates')->willReturn([
                'first' => '20170510100000',
                'last' => '20170515150000',
            ]);
        $project = new Project('TestProject');
        $user = new User('Testuser1');
        $editCounter = new EditCounter($project, $user);
        $editCounter->setRepository($editCounterRepo);
        $this->assertEquals(
            new \DateTime('2017-05-10 10:00'),
            $editCounter->datetimeFirstRevision()
        );
        $this->assertEquals(
            new \DateTime('2017-05-15 15:00'),
            $editCounter->datetimeLastRevision()
        );
        $this->assertEquals(5, $editCounter->getDays());
    }

    /**
     * Only one edit means the dates will be the same.
     */
    public function testDatesWithOneRevision()
    {
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())
            ->method('getRevisionDates')
            ->willReturn([
                'first' => '20170510110000',
                'last' => '20170510110000',
            ]);
        $project = new Project('TestProject');
        $user = new User('Testuser1');
        $editCounter = new EditCounter($project, $user);
        $editCounter->setRepository($editCounterRepo);
        $this->assertEquals(
            new \DateTime('2017-05-10 11:00'),
            $editCounter->datetimeFirstRevision()
        );
        $this->assertEquals(
            new \DateTime('2017-05-10 11:00'),
            $editCounter->datetimeLastRevision()
        );
        $this->assertEquals(1, $editCounter->getDays());
    }

    public function testPageCounts()
    {
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())
            ->method('getPageCounts')
            ->willReturn([
                'edited-live' => '3',
                'edited-deleted' => '1',
                'created-live' => '6',
                'created-deleted' => '2',
            ]);
        $project = new Project('TestProject');
        $user = new User('Testuser1');
        $editCounter = new EditCounter($project, $user);
        $editCounter->setRepository($editCounterRepo);
        
        $this->assertEquals(3, $editCounter->countLivePagesEdited());
        $this->assertEquals(1, $editCounter->countDeletedPagesEdited());
        $this->assertEquals(4, $editCounter->countAllPagesEdited());
        
        $this->assertEquals(6, $editCounter->countCreatedPagesLive());
        $this->assertEquals(2, $editCounter->countPagesCreatedDeleted());
        $this->assertEquals(8, $editCounter->countPagesCreated());
    }
}
