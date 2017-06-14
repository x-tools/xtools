<?php

namespace Tests\Xtools;

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

    public function testNamespaceTotals()
    {
        $namespaceTotals = [
            // Namespace IDs => Edit counts
            '1' => '3',
            '2' => '6',
            '3' => '9',
            '4' => '12',
        ];
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())
            ->method('getNamespaceTotals')
            ->willReturn($namespaceTotals);
        $project = new Project('TestProject');
        $user = new User('Testuser1');
        $editCounter = new EditCounter($project, $user);
        $editCounter->setRepository($editCounterRepo);

        $this->assertEquals($namespaceTotals, $editCounter->namespaceTotals());
    }

    /**
     * Get all global edit counts, or just the top N, or the overall grand total.
     */
    public function testGlobalEditCounts()
    {
        $wiki1 = new Project('wiki1');
        $wiki2 = new Project('wiki2');
        $editCounts = [
            ['project' => new Project('wiki0'), 'total' => 30],
            ['project' => $wiki1, 'total' => 50],
            ['project' => $wiki2, 'total' => 40],
            ['project' => new Project('wiki3'), 'total' => 20],
            ['project' => new Project('wiki4'), 'total' => 10],
            ['project' => new Project('wiki5'), 'total' => 35],
        ];
        $editCounterRepo = $this->getMock(EditCounterRepository::class);
        $editCounterRepo->expects($this->once())
            ->method('globalEditCounts')
            ->willReturn($editCounts);
        $user = new User('Testuser1');
        $editCounter = new EditCounter($wiki1, $user);
        $editCounter->setRepository($editCounterRepo);

        // Get the top 2.
        $this->assertEquals(
            [
                ['project' => $wiki1, 'total' => 50],
                ['project' => $wiki2, 'total' => 40],
            ],
            $editCounter->globalEditCountsTopN(2)
        );

        // Grand total.
        $this->assertEquals(185, $editCounter->globalEditCount());
    }
}
