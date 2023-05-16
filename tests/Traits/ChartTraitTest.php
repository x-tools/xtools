<?php

namespace App\Tests\Traits;

use App\Traits\Chart;
use PHPUnit\Framework\TestCase;

class ChartTraitTest extends TestCase
{
    use Chart;

    public function testGetChartColors(): void
    {
        static::assertEquals('rgba(171, 212, 235, 1)', Chart::getChartColor(0));
        static::assertEquals('#FF5555', Chart::getColorList(0));
        static::assertEquals('#CCC', Chart::getColorList(null));
    }
}
