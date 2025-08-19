<?php

declare(strict_types = 1);

namespace App\Tests\Model;

use App\Model\Project;
use App\Model\SimpleEditCounter;
use App\Model\User;
use App\Repository\SimpleEditCounterRepository;
use App\Tests\TestAdapter;

class ModelTest extends TestAdapter
{
    public function testBasics(): void
    {
        // Use SimpleEditCounter since Model is abstract.
        $repo = $this->createMock(SimpleEditCounterRepository::class);
        $project = $this->createMock(Project::class);
        $user = $this->createMock(User::class);
        $start = '2020-01-01';
        $end = '2020-02-01';

        $model = new SimpleEditCounter(
            $repo,
            $project,
            $user,
            'all',
            strtotime($start),
            strtotime($end)
        );

        self::assertEquals($model->getRepository(), $repo);
        self::assertEquals($model->getProject(), $project);
        self::assertEquals($model->getUser(), $user);
        self::assertNull($model->getPage());
        self::assertEquals('all', $model->getNamespace());
        self::assertEquals(strtotime($start), $model->getStart());
        self::assertEquals($start, $model->getStartDate());
        self::assertEquals(strtotime($end), $model->getEnd());
        self::assertEquals($end, $model->getEndDate());
        self::assertTrue($model->hasDateRange());
        self::assertNull($model->getLimit());
        self::assertFalse($model->getOffset());
        self::assertNull($model->getOffsetISO());
    }
}
