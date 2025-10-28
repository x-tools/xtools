<?php

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Model\Authorship;
use App\Model\Page;
use App\Model\Project;
use App\Repository\AuthorshipRepository;
use App\Repository\PageRepository;
use App\Repository\ProjectRepository;
use App\Tests\TestAdapter;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub\Stub;

/**
 * @covers \App\Model\Authorship
 */
class AuthorshipTest extends TestAdapter
{
    /**
     * Authorship stats from WhoColor API.
     */
    public function testAuthorship(): void
    {
        /** @var AuthorshipRepository|MockObject $authorshipRepo */
        $authorshipRepo = $this->createMock(AuthorshipRepository::class);
        $authorshipRepo->expects($this->exactly(2))
            ->method('getData')
            ->willReturn([
                'revisions' => [[
                    '123' => [
                        'time' => '2018-04-16T13:51:11Z',
                        'tokens' => [
                            [
                                'editor' => '1',
                                'str' => 'foo',
                            ], [
                                'editor' => '0|192.168.0.1',
                                'str' => 'bar',
                            ], [
                                'editor' => '0|192.168.0.1',
                                'str' => 'baz',
                            ], [
                                'editor' => '2',
                                'str' => 'foobar',
                            ],
                        ],
                    ],
                ]],
            ]);
        $authorshipRepo->expects($this->exactly(2))
            ->method('getUsernamesFromIds')
            ->willReturn([
                ['user_id' => 1, 'user_name' => 'Mick Jagger'],
                ['user_id' => 2, 'user_name' => 'Mr. Rogers'],
            ]);
        $projectRepo = $this->createMock(ProjectRepository::class);
        $projectRepo->expects(static::once())
            ->method('getOne')
            ->willReturn([
                'url' => 'https://en.wikipedia.org',
            ]);
        $project = new Project('en.wikipedia.org');
        $project->setRepository($projectRepo);
        $pageRepo = $this->createMock(PageRepository::class);
        $pageRepo->expects(static::once())
            ->method('getPageInfo')
            ->willReturn([
                'ns' => 0,
            ]);
        $page = new Page($pageRepo, $project, 'Test page');
        $authorship = new Authorship($authorshipRepo, $page, null, 2);
        $authorship->prepareData();
        $authorship->prepareData(); // Ensure caching

        static::assertEquals(
            [
                'Mr. Rogers' => [
                    'count' => 6,
                    'percentage' => 40.0,
                ],
                '192.168.0.1' => [
                    'count' => 6,
                    'percentage' => 40.0,
                ],
            ],
            $authorship->getList()
        );

        static::assertTrue($authorship->isSupportedPage($page));
        static::assertNull($authorship->getError());
        static::assertEquals(3, $authorship->getTotalAuthors());
        static::assertEquals(15, $authorship->getTotalCount());
        static::assertEquals([
            'count' => 3,
            'percentage' => 20.0,
            'numEditors' => 1,
        ], $authorship->getOthers());
        static::assertEquals(['id', 'timestamp'], array_keys($authorship->getRevision()));

        // Test for a day-only target
        $page = $this->createMock(Page::class);
        $page->expects(static::once())
            ->method('getRevisionIdAtDate')
            ->willReturn(1234);
        $authorship = new Authorship($authorshipRepo, $page, '2001-02-03', 2);
        static::assertEquals(1234, $authorship->getTarget());

        // Test for a raw ID target and limit null
        $authorship = new Authorship($authorshipRepo, $page, '1234', null);
        static::assertEquals(1234, $authorship->getTarget());
        $authorship->prepareData();
    }

    /**
     * Test prepareData's reaction to unexpected getData responses
     * @dataProvider getDataEdgeCasesProvider
     * @param Stub $data
     * @param int|null $expected
     */
    public function testGetDataEdgeCases(Stub $data, ?int $expected): void
    {
        $authorshipRepo = $this->createMock(AuthorshipRepository::class);
        $authorshipRepo->expects(static::once())
            ->method('getData')
            ->will($data);
        $page = $this->createMock(Page::class);
        $authorship = new Authorship($authorshipRepo, $page, null);
        $authorship->prepareData();
        static::assertEquals($expected, $authorship->getTotalAuthors());
    }

    public function getDataEdgeCasesProvider(): array
    {
        return [
            'getData requestException' => [
                $this->throwException($this->createMock(RequestException::class)),
                null,
            ],
            'getData no key revisions' => [
                $this->returnValue([]),
                null,
            ],
            'getData no key tokens' => [
                $this->returnValue(['revisions' => [[ '123' => ['time' => '2018-04-16T13:51:11Z', 'tokens' => []] ]] ]),
                0,
            ],
        ];
    }
}
