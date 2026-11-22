<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Geometry of the support phase bars in an SVG 1000 units wide: labels on the left,
 * one row per major with the newest on top like the roadmap of typo3.com, a grid line
 * per new year and a line for today.
 */
final readonly class UpgradeSupportChart
{
    public const int WIDTH = 1000;
    private const int LEFT = 110;
    private const int ROW = 30;

    /**
     * @param list<array{major: int, x: int, regularWidth: int, extendedWidth: int, y: int}> $rows
     * @param list<array{year: int, x: int}>                                                 $years
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
        $x = static fn (int $t): int => self::LEFT + (int) round(($t - $from) / max(1, $to - $from) * (self::WIDTH - self::LEFT - 10));

        $rows = [];
        foreach ($phases as $i => $phase) {
            $start = $x($phase->start->getTimestamp());
            $maintained = $x($phase->maintainedUntil->getTimestamp());
            $rows[] = [
                'major' => $phase->major,
                'x' => $start,
                'regularWidth' => max(1, $maintained - $start),
                'extendedWidth' => null !== $phase->extendedUntil ? max(1, $x($phase->extendedUntil->getTimestamp()) - $maintained) : 0,
                'y' => 8 + (count($phases) - 1 - $i) * self::ROW,
            ];
        }

        $years = [];
        for ($year = (int) date('Y', $from) + 1; $year <= (int) date('Y', $to); ++$year) {
            $years[] = ['year' => $year, 'x' => $x((int) mktime(0, 0, 0, 1, 1, $year))];
        }

        $today = $now->getTimestamp() > $from && $now->getTimestamp() < $to ? $x($now->getTimestamp()) : null;

        return new self($rows, $years, $today, 40 + count($phases) * self::ROW);
    }
}
