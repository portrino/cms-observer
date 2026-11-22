<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Geometry of the support phase bars in an SVG 1000 units wide: labels on the left,
 * one row per major with the newest on top like the roadmap of typo3.com, a grid line
 * per new year and a line for today. A row runs through development (with a tick per
 * preview release), regular maintenance, priority bugfixes and extended support.
 */
final readonly class UpgradeSupportChart
{
    public const int WIDTH = 1000;
    private const int LEFT = 110;
    private const int ROW = 30;

    /**
     * @param list<array{major: int, y: int, segments: list<array{phase: string, x: int, width: int, until: \DateTimeImmutable}>, ticks: list<int>}> $rows
     * @param list<array{year: int, x: int}>                                                                                                         $years
     */
    private function __construct(
        public array $rows,
        public array $years,
        public ?int $today,
        public int $height,
    ) {
    }

    /**
     * @param non-empty-list<UpgradeSupportPhase> $phases
     */
    public static function of(array $phases, \DateTimeImmutable $now): self
    {
        $from = min(array_map(static fn (UpgradeSupportPhase $p): int => $p->start->getTimestamp(), $phases));
        $to = max(array_map(static fn (UpgradeSupportPhase $p): int => $p->getEnd()->getTimestamp(), $phases));
        $x = static fn (\DateTimeImmutable $t): int => self::LEFT + (int) round(($t->getTimestamp() - $from) / max(1, $to - $from) * (self::WIDTH - self::LEFT - 10));

        $rows = [];
        foreach ($phases as $i => $phase) {
            // each phase runs from the end of the previous one; unknown boundaries leave a phase out
            $boundaries = array_filter([
                'development' => $phase->stableSince,
                'regular' => $phase->prioritySince,
                'priority' => $phase->maintainedUntil,
                'extended' => $phase->extendedUntil,
            ]);
            $segments = [];
            $begin = $phase->start;
            foreach ($boundaries as $name => $until) {
                if ($until > $begin) {
                    $segments[] = ['phase' => $name, 'x' => $x($begin), 'width' => max(1, $x($until) - $x($begin)), 'until' => $until];
                    $begin = $until;
                }
            }

            $rows[] = [
                'major' => $phase->major,
                'y' => 8 + (count($phases) - 1 - $i) * self::ROW,
                'segments' => $segments,
                'ticks' => array_map($x, $phase->previewReleases),
            ];
        }

        $years = [];
        for ($year = (int) date('Y', $from) + 1; $year <= (int) date('Y', $to); ++$year) {
            $years[] = ['year' => $year, 'x' => $x(new \DateTimeImmutable("{$year}-01-01"))];
        }

        $today = $now->getTimestamp() > $from && $now->getTimestamp() < $to ? $x($now) : null;

        return new self($rows, $years, $today, 40 + count($phases) * self::ROW);
    }
}
