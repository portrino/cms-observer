<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\UpgradeItem;
use App\Entity\UpgradeCalculation;
use App\Entity\UpgradeCalculationVersion;
use App\Entity\UpgradeItemStatus;

/**
 * The rows of a version brought up to the project's latest report: package rows get their
 * installed and target version, to-do and section anew, but keep what people entered
 * (hours, notes, assignee, label, a "Replace" or "Remove" decision). Packages no longer
 * installed are left out, new ones added; rows without a package stay as they are.
 */
final readonly class UpgradeCalculationRefresher
{
    private const array DECISIONS = [UpgradeItemStatus::Replace, UpgradeItemStatus::Remove];

    public function __construct(
        private UpgradeCalculationDrafter $drafter,
    ) {
    }

    /**
     * @return array{items: list<UpgradeItem>, changes: list<string>}
     */
    public function refresh(UpgradeCalculation $calculation, UpgradeCalculationVersion $base): array
    {
        $fresh = [];
        foreach ($this->drafter->packageItems($calculation->getSite(), $calculation->getTargetMajor()) as $item) {
            $fresh[self::key($item)] = $item;
        }

        $items = [];
        $changes = [];
        foreach ($base->getItems() as $item) {
            if (null === $item->package || null !== $item->sumOf) {
                $items[] = $item;
                continue;
            }
            $key = self::key($item);
            $now = $fresh[$key] ?? null;
            unset($fresh[$key]);
            if (null === $now) {
                $changes[] = sprintf('%s: not installed anymore, left out', $item->label);
                continue;
            }

            $status = in_array($item->status, self::DECISIONS, true) ? $item->status : $now->status;
            $items[] = new UpgradeItem(
                $now->section, $item->label, $item->package, $item->packageType, $now->installedVersion, $now->targetVersion, $status,
                $item->minHours, $item->maxHours, $item->notes, $item->assignee, $item->noteLevel,
            );

            $changed = array_filter([
                $item->installedVersion !== $now->installedVersion ? sprintf('installed %s → %s', $item->installedVersion ?? '–', $now->installedVersion ?? '–') : null,
                $item->targetVersion !== $now->targetVersion ? sprintf('target %s → %s', $item->targetVersion ?? '–', $now->targetVersion ?? '–') : null,
                $item->status !== $status ? sprintf('to-do %s → %s', $item->status?->label() ?? '–', $status?->label() ?? '–') : null,
                $item->section !== $now->section ? sprintf('moved to %s', $now->section->label()) : null,
            ]);
            if ([] !== $changed) {
                $changes[] = sprintf('%s: %s', $item->label, implode(', ', $changed));
            }
        }

        foreach ($fresh as $item) {
            $items[] = $item;
            $changes[] = sprintf('%s: newly installed, added', $item->label);
        }

        return ['items' => UpgradeItem::withSectionSums($items), 'changes' => $changes];
    }

    private static function key(UpgradeItem $item): string
    {
        return ($item->packageType->value ?? '').'|'.$item->package;
    }
}
