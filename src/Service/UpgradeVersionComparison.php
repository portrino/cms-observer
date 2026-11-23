<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\UpgradeItem;
use App\Entity\UpgradeCalculationVersion;
use App\Entity\UpgradeSection;

/**
 * What changed from one version of a calculation to another, as "old → new" per field.
 * Rows are matched by their package, the section they stand for, or else their label.
 */
final readonly class UpgradeVersionComparison
{
    private const array ROW_FIELDS = [
        'label' => 'calculation.label',
        'installedVersion' => 'calculation.installed',
        'targetVersion' => 'calculation.target',
        'status' => 'calculation.todo',
        'minHours' => 'calculation.min_h',
        'maxHours' => 'calculation.max_h',
        'notes' => 'calculation.notes',
        'noteLevel' => 'calculation.note_color',
        'assignee' => 'calculation.by',
    ];

    /** @var list<array{field: string, old: string, new: string}> */
    public array $settings;
    /** @var array<string, array{added: list<UpgradeItem>, removed: list<UpgradeItem>, changed: list<array{item: UpgradeItem, fields: list<array{field: string, old: string, new: string}>}>}> */
    public array $sections;
    /** @var list<array{field: string, old: string, new: string}> */
    public array $totals;

    public function __construct(public UpgradeCalculationVersion $old, public UpgradeCalculationVersion $new)
    {
        $this->settings = self::changes([
            'calculation.field.rate' => [self::number($old->getHourlyRate()), self::number($new->getHourlyRate())],
            'calculation.field.management' => [self::number($old->getManagementPercent()), self::number($new->getManagementPercent())],
            'calculation.final_version' => [$old->isFinal() ? 'common.yes' : 'common.no', $new->isFinal() ? 'common.yes' : 'common.no'],
            'calculation.field.header_notes' => [(string) $old->getHeaderNotes(), (string) $new->getHeaderNotes()],
            'calculation.field.remarks' => [(string) $old->getRemarks(), (string) $new->getRemarks()],
        ]);

        $sections = [];
        foreach (UpgradeSection::cases() as $section) {
            $oldRows = self::byKey($old->getItems($section));
            $newRows = self::byKey($new->getItems($section));
            $changed = [];
            foreach (array_intersect_key($newRows, $oldRows) as $key => $row) {
                $fields = self::changes(array_combine(self::ROW_FIELDS, array_map(
                    static fn (string $field): array => [self::value($oldRows[$key], $field), self::value($row, $field)],
                    array_keys(self::ROW_FIELDS),
                )));
                if ([] !== $fields) {
                    $changed[] = ['item' => $row, 'fields' => $fields];
                }
            }
            $sections[$section->value] = [
                'added' => array_values(array_diff_key($newRows, $oldRows)),
                'removed' => array_values(array_diff_key($oldRows, $newRows)),
                'changed' => $changed,
            ];
        }
        $this->sections = $sections;

        $oldTotals = new UpgradeTotals($old->getItems(), $old->getManagementPercent());
        $newTotals = new UpgradeTotals($new->getItems(), $new->getManagementPercent());
        $this->totals = self::changes([
            'calculation.total.positions' => [self::number($oldTotals->work['max']), self::number($newTotals->work['max'])],
            'calculation.total.hours' => [self::number($oldTotals->required['max']), self::number($newTotals->required['max'])],
            'calculation.total.price' => [self::number($oldTotals->required['max'] * $old->getHourlyRate()), self::number($newTotals->required['max'] * $new->getHourlyRate())],
            'calculation.total.optional' => [self::number($oldTotals->optional['max']), self::number($newTotals->optional['max'])],
        ]);
    }

    public function isEmpty(): bool
    {
        return [] === $this->settings && [] === $this->totals
            && [] === array_filter($this->sections, static fn (array $rows): bool => [] !== $rows['added'] || [] !== $rows['removed'] || [] !== $rows['changed']);
    }

    /**
     * @param list<UpgradeItem> $items
     *
     * @return array<string, UpgradeItem>
     */
    private static function byKey(array $items): array
    {
        $rows = [];
        foreach ($items as $item) {
            $key = match (true) {
                null !== $item->sumOf => 'sum|'.$item->sumOf->value,
                null !== $item->package => 'package|'.($item->packageType->value ?? '').'|'.$item->package,
                default => 'label|'.$item->label,
            };
            // the same label twice: compared in their order
            $unique = $key;
            for ($n = 2; isset($rows[$unique]); ++$n) {
                $unique = $key.'|'.$n;
            }
            $rows[$unique] = $item;
        }

        return $rows;
    }

    private static function value(UpgradeItem $item, string $field): string
    {
        $hours = static fn (?float $hours): string => null !== $hours ? self::number($hours) : '';

        return match ($field) {
            'label' => $item->label,
            'installedVersion' => (string) $item->installedVersion,
            'targetVersion' => (string) $item->targetVersion,
            'status' => null !== $item->status ? 'calculation.status.'.$item->status->value : '',
            'minHours' => $hours($item->minHours),
            'maxHours' => $hours($item->maxHours),
            'notes' => (string) $item->notes,
            'noteLevel' => null !== $item->noteLevel ? 'calculation.note_level.'.$item->noteLevel->value : '',
            'assignee' => (string) $item->assignee,
            default => throw new \LogicException(sprintf('Unknown row field "%s".', $field)),
        };
    }

    private static function number(float $value): string
    {
        return number_format($value, 2);
    }

    /**
     * @param array<string, array{string, string}> $pairs
     *
     * @return list<array{field: string, old: string, new: string}>
     */
    private static function changes(array $pairs): array
    {
        $changes = [];
        foreach ($pairs as $field => [$old, $new]) {
            if ($old !== $new) {
                $changes[] = ['field' => $field, 'old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }
}
