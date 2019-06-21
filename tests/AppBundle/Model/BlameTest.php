<?php
declare(strict_types = 1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\Blame;
use AppBundle\Model\Page;
use AppBundle\Model\Project;
use AppBundle\Repository\BlameRepository;
use Tests\AppBundle\TestAdapter;

class BlameTest extends TestAdapter
{
    /** @var Project */
    protected $project;

    /** @var Page */
    protected $page;

    /** @var BlameRepository|\PHPUnit_Framework_MockObject_MockObject */
    protected $blameRepo;

    /**
     * Set up shared mocks and class instances.
     */
    public function setUp(): void
    {
        $this->project = new Project('test.example.org');
        $this->page = new Page($this->project, 'Test page');
        $this->blameRepo = $this->getMock(BlameRepository::class);
    }

    /**
     * @covers Blame::getQuery
     * @covers Blame::getTokenizedQuery
     */
    public function testBasics(): void
    {
        $blame = new Blame($this->page, "Foo bar\nBAZ");
        static::assertEquals("Foo bar\nBAZ", $blame->getQuery());
        static::assertEquals('foobarbaz', $blame->getTokenizedQuery());
    }

    /**
     * @covers Blame::prepareData
     * @covers Blame::searchTokens
     */
    public function testPrepareData(): void
    {
        $this->blameRepo->expects($this->once())
            ->method('getData')
            ->willReturn([
                'revisions' => [[
                    '123' => [
                        'time' => '2018-04-16T13:51:11Z',
                        'tokens' => [
                            [
                                'o_rev_id' => 1,
                                'editor' => 'MusikAnimal',
                                'str' => 'foo',
                            ], [
                                'o_rev_id' => 1,
                                'editor' => 'MusikAnimal',
                                'str' => 'bar',
                            ], [
                                'o_rev_id' => 2,
                                'editor' => '0|192.168.0.1',
                                'str' => 'baz',
                            ], [
                                'o_rev_id' => 3,
                                'editor' => 'Matthewrbowker',
                                'str' => 'foobar',
                            ],
                        ],
                    ],
                ]],
            ]);

        $blame = new Blame($this->page, 'Foo bar');
        $blame->setRepository($this->blameRepo);
        $blame->prepareData();
        $matches = $blame->getMatches();

        static::assertCount(2, $matches);
        static::assertEquals([3, 1], array_keys($matches));
    }
}
