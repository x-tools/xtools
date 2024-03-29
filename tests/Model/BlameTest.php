<?php

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Model\Blame;
use App\Model\Page;
use App\Model\Project;
use App\Repository\BlameRepository;
use App\Repository\PageRepository;
use App\Tests\TestAdapter;

/**
 * @covers \App\Model\Blame
 */
class BlameTest extends TestAdapter
{
    protected BlameRepository $blameRepo;
    protected Page $page;
    protected Project $project;

    /**
     * Set up shared mocks and class instances.
     */
    public function setUp(): void
    {
        $this->project = new Project('test.example.org');
        $pageRepo = $this->createMock(PageRepository::class);
        $this->page = new Page($pageRepo, $this->project, 'Test page');
        $this->blameRepo = $this->createMock(BlameRepository::class);
    }

    /**
     * @covers \App\Model\Blame::getQuery
     * @covers \App\Model\Blame::getTokenizedQuery
     */
    public function testBasics(): void
    {
        $blame = new Blame($this->blameRepo, $this->page, "Foo bar\nBAZ");
        static::assertEquals("Foo bar\nBAZ", $blame->getQuery());
        static::assertEquals('foobarbaz', $blame->getTokenizedQuery());
    }

    /**
     * @covers \App\Model\Blame::prepareData
     * @covers \App\Model\Blame::searchTokens
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
                                'str' => 'loremfoo',
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
        $this->blameRepo->expects($this->exactly(2))
            ->method('getEditFromRevId')
            ->willReturn($this->createMock('App\Model\Edit'));

        $blame = new Blame($this->blameRepo, $this->page, 'Foo bar');
        $blame->prepareData();
        $matches = $blame->getMatches();

        static::assertCount(2, $matches);
        static::assertEquals([3, 1], array_keys($matches));
    }
}
