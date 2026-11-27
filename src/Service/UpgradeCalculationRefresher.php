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
 * (hours, notes, assignee, label, a "Replace" or "Remove" decision). A row whose versions
 * or to-do changed gets the fresh notes and their color instead, e.g. green once the
 * package was updated; the old ones stay in the previous version. Packages no longer
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
     * @return array{items: list<UpgradeItem>, changes: list<string>, changedPackages: list<string>} changedPackages: the packages of added or changed rows
     */
    public function refresh(UpgradeCalculation $calculation, UpgradeCalculationVersion $base): array
    {
        $fresh = [];
        foreach ($this->drafter->packageItems($calculation->getSite(), $calculation->getTargetMajor()) as $item) {
            $fresh[self::key($item)] = $item;
        }

        $items = [];
        $changes = [];
        $changedPackages = [];
        foreach ($base->getItems() as $item) {
            if (!self::isPackageRow($item)) {
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

            $refreshed = $this->refreshed($item, $now);
            $items[] = $refreshed;
            $change = $this->rowChange($item, $refreshed);
            if (null !== $change) {
                $changes[] = $change;
                $changedPackages[] = (string) $item->package;
            }
        }

        foreach ($fresh as $item) {
            $items[] = $item;
            $changes[] = $this->translator->trans('calculation.change.added', ['label' => $item->label]);
            $changedPackages[] = (string) $item->package;
        }

        return ['items' => UpgradeItem::withSectionSums($items), 'changes' => $changes, 'changedPackages' => $changedPackages];
    }

    /**
     * The fresh versions, to-do and section with what people entered on the old row.
     */
    private function refreshed(UpgradeItem $item, UpgradeItem $now): UpgradeItem
    {
        $decided = in_array($item->status, self::DECISIONS, true);
        $judgedAnew = !$decided && ($item->installedVersion !== $now->installedVersion || $item->targetVersion !== $now->targetVersion || $item->status !== $now->status);

        return new UpgradeItem(
            $now->section, $item->label, $item->package, $item->packageType, $now->installedVersion, $now->targetVersion,
            $decided ? $item->status : $now->status,
            ...($judgedAnew ? self::freshHours($item, $now) : [$item->minHours, $item->maxHours]),
            notes: $judgedAnew ? $now->notes : $item->notes,
            assignee: $item->assignee,
            noteLevel: $judgedAnew ? $now->noteLevel : $item->noteLevel,
        );
    }

    /**
     * The fresh row's preset hours (0 for a package already up to date), else the entered ones;
     * but not a 0 preset of a package that has something to do now.
     *
     * @return array{?float, ?float}
     */
    private static function freshHours(UpgradeItem $item, UpgradeItem $now): array
    {
        if (null !== $now->minHours) {
            return [$now->minHours, $now->maxHours];
        }

        return 0.0 === $item->minHours && 0.0 === $item->maxHours ? [null, null] : [$item->minHours, $item->maxHours];
    }

    private function rowChange(UpgradeItem $old, UpgradeItem $new): ?string
    {
        $changed = array_filter([
            $old->installedVersion !== $new->installedVersion ? $this->translator->trans('calculation.change.installed', ['old' => $old->installedVersion ?? '–', 'new' => $new->installedVersion ?? '–']) : null,
            $old->targetVersion !== $new->targetVersion ? $this->translator->trans('calculation.change.target', ['old' => $old->targetVersion ?? '–', 'new' => $new->targetVersion ?? '–']) : null,
            $old->status !== $new->status ? $this->translator->trans('calculation.change.status', ['old' => $old->status?->trans($this->translator) ?? '–', 'new' => $new->status?->trans($this->translator) ?? '–']) : null,
            $old->section !== $new->section ? $this->translator->trans('calculation.change.moved', ['section' => $new->section->trans($this->translator)]) : null,
        ], static fn (?string $change): bool => null !== $change);

        return [] === $changed ? null : $this->translator->trans('calculation.change.row', ['label' => $old->label, 'changes' => implode(', ', $changed)]);
    }

    /**
     * Rows without a package, and section sums, are kept as they are.
     */
    private static function isPackageRow(UpgradeItem $item): bool
    {
        return null !== $item->package && null === $item->sumOf;
    }

    private static function key(UpgradeItem $item): string
    {
        return ($item->packageType->value ?? '').'|'.$item->package;
    }
}
