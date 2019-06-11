<?php
declare(strict_types = 1);

namespace Tests\AppBundle\Model;

use AppBundle\Model\Authorship;
use AppBundle\Model\Page;
use AppBundle\Model\Project;
use AppBundle\Repository\AuthorshipRepository;
use Tests\AppBundle\TestAdapter;

class AuthorshipTest extends TestAdapter
{
    /**
     * Authorship stats from WhoColor API.
     */
    public function testAuthorship(): void
    {
        /** @var AuthorshipRepository $authorshipRepo */
        $authorshipRepo = $this->getMock(AuthorshipRepository::class);
        $authorshipRepo->expects($this->once())
            ->method('getData')
            ->willReturn([
                'revisions' => [[
                    '123' => [
                        'time' => '2018-04-16T13:51:11Z',
                        'tokens' => [
                            [
                                'editor' => '1',
                                'str' => 'Foo',
                            ], [
                                'editor' => '0|192.168.0.1',
                                'str' => 'Bar',
                            ], [
                                'editor' => '0|192.168.0.1',
                                'str' => 'Baz',
                            ], [
                                'editor' => '2',
                                'str' => 'Foo bar',
                            ],
                        ],
                    ],
                ]],
            ]);
        $authorshipRepo->expects($this->once())
            ->method('getUsernamesFromIds')
            ->willReturn([
                ['user_id' => 1, 'user_name' => 'Mick Jagger'],
                ['user_id' => 2, 'user_name' => 'Mr. Rogers'],
            ]);
        $project = new Project('test.example.org');
        $page = new Page($project, 'Test page');
        $authorship = new Authorship($page, null, 2);
        $authorship->setRepository($authorshipRepo);
        $authorship->prepareData();

        static::assertEquals(
            [
                'Mr. Rogers' => [
                    'count' => 7,
                    'percentage' => 43.8,
                ],
                '192.168.0.1' => [
                    'count' => 6,
                    'percentage' => 37.5,
                ],
            ],
            $authorship->getList()
        );

        static::assertEquals(3, $authorship->getTotalAuthors());
        static::assertEquals(16, $authorship->getTotalCount());
        static::assertEquals([
            'count' => 3,
            'percentage' => 18.7,
            'numEditors' => 1,
        ], $authorship->getOthers());
    }
}
