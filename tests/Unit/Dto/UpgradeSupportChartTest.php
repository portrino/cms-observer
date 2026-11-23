<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\UpgradeSupportChart;
use App\Dto\UpgradeSupportPhase;
use PHPUnit\Framework\TestCase;

class UpgradeSupportChartTest extends TestCase
{
    public function testDrawsThePhasesOfEachMajorOnOneTimeAxis(): void
    {
        $chart = UpgradeSupportChart::of([
            new UpgradeSupportPhase(
                13,
                new \DateTimeImmutable('2024-01-01'),
                maintainedUntil: new \DateTimeImmutable('2027-01-01'),
                extendedUntil: new \DateTimeImmutable('2028-01-01'),
                stableSince: new \DateTimeImmutable('2024-07-01'),
                prioritySince: new \DateTimeImmutable('2025-07-01'),
                previewReleases: [new \DateTimeImmutable('2024-01-01'), new \DateTimeImmutable('2024-04-01')],
            ),
            new UpgradeSupportPhase(14, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2028-01-01')),
        ], new \DateTimeImmutable('2026-01-01'));

        [$v13, $v14] = $chart->rows;
        self::assertSame(['development', 'regular', 'priority', 'extended'], array_column($v13['segments'], 'phase'));
        self::assertSame(110, $v13['segments'][0]['x'], 'the oldest start is the left edge of the bars');
        $last = end($v13['segments']);
        self::assertSame(990, $last['x'] + $last['width'], 'the latest end is the right edge');
        foreach ([1, 2, 3] as $i) {
            self::assertSame($v13['segments'][$i - 1]['x'] + $v13['segments'][$i - 1]['width'], $v13['segments'][$i]['x'], 'phases follow each other without gaps');
        }
        self::assertSame([110, $v13['ticks'][1]], [$v13['ticks'][0], $v13['ticks'][1]]);
        self::assertGreaterThan($v13['ticks'][0], $v13['ticks'][1]);

        self::assertSame(['priority'], array_column($v14['segments'], 'phase'), 'unknown boundaries leave phases out');
        self::assertSame([38, 8], [$v13['y'], $v14['y']], 'the newest major on top, the current one at the bottom');
        self::assertSame($v14['segments'][0]['x'], $chart->today);
        self::assertSame([2025, 2026, 2027, 2028], array_column($chart->years, 'year'));
    }

    public function testTodayOutsideThePhasesIsNotDrawn(): void
    {
        $chart = UpgradeSupportChart::of([
            new UpgradeSupportPhase(13, new \DateTimeImmutable('2024-01-01'), new \DateTimeImmutable('2026-01-01')),
        ], new \DateTimeImmutable('2030-01-01'));

        self::assertNull($chart->today);
    }
}
