<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\PackageType;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeNoteLevel;
use App\Entity\UpgradeSection;

/**
 * One row of a calculation version. Stored as JSON in a version that never
 * changes (ADR-0004): only add fields with defaults, never rename or remove one.
 */
final readonly class UpgradeItem
{
    public function __construct(
        public UpgradeSection $section,
        public string $label,
        public ?string $package = null,
        public ?PackageType $packageType = null,
        public ?string $installedVersion = null,
        public ?string $targetVersion = null,
        public ?UpgradeItemStatus $status = null,
        public ?float $minHours = null,
        public ?float $maxHours = null,
        public ?string $notes = null,
        public ?string $assignee = null,
        public ?UpgradeNoteLevel $noteLevel = null,
        // a position that stands for the hours of a package section, placed among the positions
        public ?UpgradeSection $sumOf = null,
        // fingerprint of what the resolver preset for the row: a refresh tells by it whether that changed
        public ?string $preset = null,
    ) {
    }

    /**
     * The row with the fingerprint of its own values, as a resolver presets them.
     *
     * @param ?string $notesMeaning what the notes say, if the resolver can tell it apart from their
     *                              wording: then switching the language doesn't change the fingerprint
     */
    public function withPreset(?string $notesMeaning = null): self
    {
        $preset = substr(hash('sha256', json_encode([$this->section->value, $this->status?->value, $this->targetVersion, $this->minHours, $this->maxHours, $notesMeaning ?? $this->notes, $this->noteLevel?->value], JSON_THROW_ON_ERROR)), 0, 16);

        return new self($this->section, $this->label, $this->package, $this->packageType, $this->installedVersion, $this->targetVersion, $this->status,
            $this->minHours, $this->maxHours, $this->notes, $this->assignee, $this->noteLevel, $this->sumOf, $preset);
    }

    public static function sumOf(UpgradeSection $section): self
    {
        return new self(UpgradeSection::Positions, $section->label(), sumOf: $section);
    }

    /**
     * Every package section has its sum among the positions: missing ones, e.g. in versions
     * from before them or removed from a submitted form, are added after the positions.
     *
     * @param list<self> $items
     *
     * @return list<self>
     */
    public static function withSectionSums(array $items): array
    {
        $missing = array_filter(UpgradeSection::cases(), static fn (UpgradeSection $section): bool => $section->hasPackages()
            && [] === array_filter($items, static fn (self $item): bool => $item->sumOf === $section));
        $positions = array_filter($items, static fn (self $item): bool => UpgradeSection::Positions === $item->section);
        $after = [] === $positions ? 0 : array_key_last($positions) + 1;

        return [
            ...array_slice($items, 0, $after),
            ...array_map(self::sumOf(...), array_values($missing)),
            ...array_slice($items, $after),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $package = self::stringOf($data, 'package');

        $sumOf = UpgradeSection::tryFrom((string) ($data['sumOf'] ?? ''));
        if (null !== $sumOf && $sumOf->hasPackages()) {
            // its label and hours come from the section, whatever was submitted
            return new self(UpgradeSection::Positions, $sumOf->label(), notes: self::stringOf($data, 'notes'), assignee: self::stringOf($data, 'assignee'),
                noteLevel: UpgradeNoteLevel::tryFrom((string) ($data['noteLevel'] ?? '')), sumOf: $sumOf);
        }

        return new self(
            UpgradeSection::tryFrom((string) ($data['section'] ?? '')) ?? UpgradeSection::Positions,
            self::stringOf($data, 'label') ?? $package ?? '',
            $package,
            PackageType::tryFrom((string) ($data['packageType'] ?? '')),
            self::stringOf($data, 'installedVersion'),
            self::stringOf($data, 'targetVersion'),
            self::statusOf($data, null !== $package),
            self::hoursOf($data, 'minHours'),
            self::hoursOf($data, 'maxHours'),
            self::stringOf($data, 'notes'),
            self::stringOf($data, 'assignee'),
            UpgradeNoteLevel::tryFrom((string) ($data['noteLevel'] ?? '')),
            preset: self::stringOf($data, 'preset'),
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return [
            'section' => $this->section->value,
            'label' => $this->label,
            'package' => $this->package,
            'packageType' => $this->packageType?->value,
            'installedVersion' => $this->installedVersion,
            'targetVersion' => $this->targetVersion,
            'status' => $this->status?->value,
            'minHours' => $this->minHours,
            'maxHours' => $this->maxHours,
            'notes' => $this->notes,
            'assignee' => $this->assignee,
            'noteLevel' => $this->noteLevel?->value,
            'sumOf' => $this->sumOf?->value,
            'preset' => $this->preset,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function stringOf(array $data, string $key): ?string
    {
        return isset($data[$key]) && '' !== (string) $data[$key] ? (string) $data[$key] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function hoursOf(array $data, string $key): ?float
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (float) $data[$key] : null;
    }

    /**
     * A status from a newer Observer, or a lost one, must not hide the row.
     *
     * @param array<string, mixed> $data
     */
    private static function statusOf(array $data, bool $hasPackage): ?UpgradeItemStatus
    {
        if (!$hasPackage && !isset($data['status'])) {
            return null;
        }

        return UpgradeItemStatus::tryFrom((string) ($data['status'] ?? '')) ?? UpgradeItemStatus::Manual;
    }
}
