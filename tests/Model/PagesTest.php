<?php

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Model\PageAssessments;
use App\Model\Pages;
use App\Model\Project;
use App\Model\User;
use App\Repository\PageAssessmentsRepository;
use App\Repository\PagesRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Tests\TestAdapter;

/**
 * Tests of the Pages class.
 * @covers \App\Model\Pages
 */
class PagesTest extends TestAdapter
{
    protected Project $project;
    protected ProjectRepository $projectRepo;
    protected User $user;
    protected UserRepository $userRepo;
    protected PagesRepository $pagesRepo;

    /**
     * Set up class instances and mocks.
     */
    public function setUp(): void
    {
        $this->project = $this->createMock(Project::class);
        $paRepo = $this->createMock(PageAssessmentsRepository::class);
        $paRepo->method('getConfig')
            ->willReturn($this->getAssessmentsConfig());
        $pa = new PageAssessments($paRepo, $this->project);
        $this->project->method('getPageAssessments')
            ->willReturn($pa);
        $this->project->method('hasPageAssessments')
            ->with(0)
            ->willReturn(true);
        $this->project->method('getNamespaces')
            ->willReturn([0 => 'Main', 1 => 'Talk', 3 => 'User_talk']);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->user = new User($this->userRepo, 'Test user');
        $this->pagesRepo = $this->createMock(PagesRepository::class);
    }

    /**
     * Test the basic getters.
     */
    public function testConstructor(): void
    {
        $pages = new Pages($this->pagesRepo, $this->project, $this->user);
        static::assertEquals(0, $pages->getNamespace());
        static::assertEquals($this->project, $pages->getProject());
        static::assertEquals($this->user, $pages->getUser());
        static::assertEquals('noredirects', $pages->getRedirects());
        static::assertEquals(0, $pages->getOffset());
    }

    public function testResults(): void
    {
        $this->setPagesResults();
        $pages = new Pages($this->pagesRepo, $this->project, $this->user, 0, 'both');
        $pages->setRepository($this->pagesRepo);
        $pages->prepareData();
        static::assertEquals(3, $pages->getNumResults());
        static::assertEquals(1, $pages->getNumDeleted());
        static::assertEquals(1, $pages->getNumRedirects());

        static::assertEquals([
            0 => [
                'count' => 2,
                'redirects' => 0,
                'deleted' => 1,
                'total_length' => 17,
                'avg_length' => 8.5,
            ],
            1 => [
                'count' => 1,
                'redirects' => 1,
                'deleted' => 0,
                'total_length' => 10,
                'avg_length' => 10,
            ],
        ], $pages->getCounts());

        $results = $pages->getResults();

        static::assertEquals([0, 1], array_keys($results));
        static::assertEquals([
            'deleted' => true,
            'namespace' => 0,
            'page_title' => 'My_fun_page',
            'full_page_title' => 'My_fun_page',
            'redirect' => false,
            'timestamp' => '20160519000000',
            'rev_id' => 16,
            'rev_length' => 5,
            'length' => null,
            'recreated' => true,
            'assessment' => [
                'class' => 'Unknown',
                'badge' => 'https://upload.wikimedia.org/wikipedia/commons/e/e0/Symbol_question.svg',
                'color' => '',
                'category' => 'Category:Unassessed articles',
            ],
        ], $results[0][0]);
        static::assertEquals([
            'deleted' => false,
            'namespace' => 1,
            'page_title' => 'Google',
            'full_page_title' => 'Talk:Google',
            'redirect' => true,
            'timestamp' => '20160719000000',
            'rev_id' => 15,
            'rev_length' => 10,
            'length' => 50,
            'assessment' => [
                'class' => 'A',
                'badge' => 'https://upload.wikimedia.org/wikipedia/commons/2/25/Symbol_a_class.svg',
                'color' => '#66FFFF',
                'category' => 'Category:A-Class articles',
            ],
        ], $results[1][0]);
        static::assertTrue($pages->isMultiNamespace());
    }

    public function setPagesResults(): void
    {
        $this->pagesRepo->expects($this->exactly(2))
            ->method('getPagesCreated')
            ->willReturn([
                [
                    'namespace' => 1,
                    'type' => 'rev',
                    'page_title' => 'Google',
                    'redirect' => '1',
                    'rev_length' => 10,
                    'length' => 50,
                    'timestamp' => '20160719000000',
                    'rev_id' => 15,
                    'recreated' => null,
                    'pa_class' => 'A',
                ], [
                    'namespace' => 0,
                    'type' => 'arc',
                    'page_title' => 'My_fun_page',
                    'redirect' => '0',
                    'rev_length' => 5,
                    'length' => null,
                    'timestamp' => '20160519000000',
                    'rev_id' => 16,
                    'recreated' => 1,
                    'pa_class' => null,
                ], [
                    'namespace' => 0,
                    'type' => 'rev',
                    'page_title' => 'Foo_bar',
                    'redirect' => '0',
                    'rev_length' => 12,
                    'length' => 50,
                    'timestamp' => '20160101000000',
                    'rev_id' => 17,
                    'recreated' => null,
                    'pa_class' => 'FA',
                ],
            ]);
        $this->pagesRepo->expects($this->once())
            ->method('countPagesCreated')
            ->willReturn([
                [
                    'namespace' => 0,
                    'count' => 2,
                    'deleted' => 1,
                    'redirects' => 0,
                    'total_length' => 17,
                ], [
                    'namespace' => 1,
                    'count' => 1,
                    'deleted' => 0,
                    'redirects' => 1,
                    'total_length' => 10,
                ],
            ]);
    }

    /**
     * Mock assessments configuration.
     * @return array
     */
    private function getAssessmentsConfig(): array
    {
        return [
            'class' => [
                'FA' =>  [
                    'badge' => 'b/bc/Featured_article_star.svg',
                    'color' => '#9CBDFF',
                    'category' => 'Category:FA-Class articles',
                ],
                'A' => [
                    'badge' => '2/25/Symbol_a_class.svg',
                    'color' => '#66FFFF',
                    'category' => 'Category:A-Class articles',
                ],
                'Unknown' => [
                    'badge' => 'e/e0/Symbol_question.svg',
                    'color' => '',
                    'category' => 'Category:Unassessed articles',
                ],
            ],
        ];
    }
}
