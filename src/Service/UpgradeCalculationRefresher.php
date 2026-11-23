<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\UpgradeItem;
use App\Entity\UpgradeCalculation;
use App\Entity\UpgradeCalculationVersion;
use App\Entity\UpgradeItemStatus;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private TranslatorInterface $translator,
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
                $changes[] = $this->translator->trans('calculation.change.gone', ['label' => $item->label]);
                continue;
            }

            $status = in_array($item->status, self::DECISIONS, true) ? $item->status : $now->status;
            $items[] = new UpgradeItem(
                $now->section, $item->label, $item->package, $item->packageType, $now->installedVersion, $now->targetVersion, $status,
                $item->minHours, $item->maxHours, $item->notes, $item->assignee, $item->noteLevel,
            );

            $changed = array_filter([
                $item->installedVersion !== $now->installedVersion ? $this->translator->trans('calculation.change.installed', ['old' => $item->installedVersion ?? '–', 'new' => $now->installedVersion ?? '–']) : null,
                $item->targetVersion !== $now->targetVersion ? $this->translator->trans('calculation.change.target', ['old' => $item->targetVersion ?? '–', 'new' => $now->targetVersion ?? '–']) : null,
                $item->status !== $status ? $this->translator->trans('calculation.change.status', ['old' => $item->status?->trans($this->translator) ?? '–', 'new' => $status?->trans($this->translator) ?? '–']) : null,
                $item->section !== $now->section ? $this->translator->trans('calculation.change.moved', ['section' => $now->section->trans($this->translator)]) : null,
            ], static fn (?string $change): bool => null !== $change);
            if ([] !== $changed) {
                $changes[] = $this->translator->trans('calculation.change.row', ['label' => $item->label, 'changes' => implode(', ', $changed)]);
            }
        }

        foreach ($fresh as $item) {
            $items[] = $item;
            $changes[] = $this->translator->trans('calculation.change.added', ['label' => $item->label]);
        }

        return ['items' => UpgradeItem::withSectionSums($items), 'changes' => $changes];
    }

    private static function key(UpgradeItem $item): string
    {
        return ($item->packageType->value ?? '').'|'.$item->package;
    }
}
