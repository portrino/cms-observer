<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\UpgradeSupportChart;
use App\Dto\UpgradeSupportPhase;
use PHPUnit\Framework\TestCase;

class UpgradeSupportChartTest extends TestCase
{
    public function testPlacesThePhasesOnOneTimeAxis(): void
    {
        $chart = UpgradeSupportChart::of([
            new UpgradeSupportPhase(13, new \DateTimeImmutable('2024-01-01'), new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2028-01-01')),
            new UpgradeSupportPhase(14, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2028-01-01')),
        ], new \DateTimeImmutable('2026-01-01'));

        [$v13, $v14] = $chart->rows;
        self::assertSame([13, 110], [$v13['major'], $v13['x']], 'the oldest start is the left edge of the bars');
        self::assertSame(990, $v13['x'] + $v13['regularWidth'] + $v13['extendedWidth'], 'the latest end is the right edge');
        self::assertSame($v13['x'] + $v13['regularWidth'], $v14['x'], 'v14 starts where v13 leaves free updates');
        self::assertSame(0, $v14['extendedWidth'], 'no extended support known');
        self::assertSame($v14['x'], $chart->today);
        self::assertSame([2025, 2026, 2027, 2028], array_column($chart->years, 'year'));
        self::assertSame(100, $chart->height);
    }

    public function testTodayOutsideThePhasesIsNotDrawn(): void
    {
        $chart = UpgradeSupportChart::of([
            new UpgradeSupportPhase(13, new \DateTimeImmutable('2024-01-01'), new \DateTimeImmutable('2026-01-01')),
        ], new \DateTimeImmutable('2030-01-01'));

        self::assertNull($chart->today);
    }
}
