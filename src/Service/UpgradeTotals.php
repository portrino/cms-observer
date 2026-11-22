<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\UpgradeItem;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeSection;

/**
 * Hours of a calculation as a min-max range. Required work is everything but the
 * optional section; both get project management on top, rounded up to whole hours.
 */
final readonly class UpgradeTotals
{
    /** @var array<string, array{min: float, max: float}> section value => hours */
    public array $sections;
    /** @var array{min: float, max: float} */
    public array $requiredManagement;
    /** @var array{min: float, max: float} */
    public array $required;
    /** @var array{min: float, max: float} */
    public array $optionalManagement;
    /** @var array{min: float, max: float} */
    public array $optional;
    /** @var array{min: float, max: float} part of the required work possible before the upgrade, e.g. in maintenance */
    public array $beforehand;

    /**
     * @param list<UpgradeItem> $items
     */
    public function __construct(array $items, private float $managementPercent)
    {
        $sections = [];
        foreach (UpgradeSection::cases() as $section) {
            $inSection = array_filter($items, static fn (UpgradeItem $item): bool => $item->section === $section);
            $sections[$section->value] = [
                'min' => array_sum(array_map(static fn (UpgradeItem $item): float => $item->minHours ?? 0.0, $inSection)),
                'max' => array_sum(array_map(static fn (UpgradeItem $item): float => $item->maxHours ?? 0.0, $inSection)),
            ];
        }
        $this->sections = $sections;

        $work = self::sum(...array_values(array_diff_key($sections, [UpgradeSection::Optional->value => true])));
        $this->requiredManagement = $this->management($work);
        $this->required = self::sum($work, $this->requiredManagement);

        $beforehand = array_filter($items, static fn (UpgradeItem $item): bool => UpgradeItemStatus::UpdateFirst === $item->status && UpgradeSection::Optional !== $item->section);
        $this->beforehand = [
            'min' => array_sum(array_map(static fn (UpgradeItem $item): float => $item->minHours ?? 0.0, $beforehand)),
            'max' => array_sum(array_map(static fn (UpgradeItem $item): float => $item->maxHours ?? 0.0, $beforehand)),
        ];

        $optionalWork = $sections[UpgradeSection::Optional->value];
        $this->optionalManagement = $this->management($optionalWork);
        $this->optional = self::sum($optionalWork, $this->optionalManagement);
    }

    /**
     * @param array{min: float, max: float} $hours
     *
     * @return array{min: float, max: float}
     */
    private function management(array $hours): array
    {
        // like Excel's ROUNDUP: multiply before dividing (100 * (7 / 100) is 7.000000000000001) and round away float noise, or 7 h become 8
        $round = fn (float $h): float => ceil(round($h * $this->managementPercent / 100, 6));

        return ['min' => $round($hours['min']), 'max' => $round($hours['max'])];
    }

    /**
     * @param array{min: float, max: float} ...$hours
     *
     * @return array{min: float, max: float}
     */
    private static function sum(array ...$hours): array
    {
        return ['min' => array_sum(array_column($hours, 'min')), 'max' => array_sum(array_column($hours, 'max'))];
    }
}
