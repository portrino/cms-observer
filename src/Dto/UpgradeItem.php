<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\PackageType;
use App\Entity\UpgradeItemStatus;
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
        public ?string $todo = null,
        public ?float $minHours = null,
        public ?float $maxHours = null,
        public ?string $notes = null,
        public ?string $assignee = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $string = static fn (string $key): ?string => isset($data[$key]) && '' !== (string) $data[$key] ? (string) $data[$key] : null;
        $hours = static fn (string $key): ?float => isset($data[$key]) && is_numeric($data[$key]) ? (float) $data[$key] : null;
        $package = $string('package');

        return new self(
            UpgradeSection::tryFrom((string) ($data['section'] ?? '')) ?? UpgradeSection::Positions,
            $string('label') ?? $package ?? '',
            $package,
            PackageType::tryFrom((string) ($data['packageType'] ?? '')),
            $string('installedVersion'),
            $string('targetVersion'),
            // a status from a newer Observer, or a lost one, must not hide the row
            null !== $package || isset($data['status'])
                ? UpgradeItemStatus::tryFrom((string) ($data['status'] ?? '')) ?? UpgradeItemStatus::Manual
                : null,
            $string('todo'),
            $hours('minHours'),
            $hours('maxHours'),
            $string('notes'),
            $string('assignee'),
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
            'todo' => $this->todo,
            'minHours' => $this->minHours,
            'maxHours' => $this->maxHours,
            'notes' => $this->notes,
            'assignee' => $this->assignee,
        ];
    }
}
