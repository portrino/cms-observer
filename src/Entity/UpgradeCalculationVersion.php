<?php

declare(strict_types=1);

namespace App\Entity;

use App\Dto\UpgradeItem;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One saved state of a calculation. Never changed after it's created:
 * every save, including marking it final, is a new version (ADR-0004).
 */
#[ORM\Entity]
class UpgradeCalculationVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private readonly \DateTimeImmutable $createdAt;

    /** @var list<array<string, scalar|null>> */
    #[ORM\Column(type: Types::JSON)]
    private readonly array $items;

    /**
     * @param list<UpgradeItem> $items
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: UpgradeCalculation::class, inversedBy: 'versions')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly UpgradeCalculation $calculation,
        #[ORM\Column(length: 100)]
        private readonly string $author,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private readonly ?string $note,
        #[ORM\Column]
        private readonly float $hourlyRate,
        // share of the summed hours added for project management, in percent
        #[ORM\Column]
        private readonly float $managementPercent,
        // shown below the totals, e.g. "license costs not included"
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private readonly ?string $remarks,
        #[ORM\Column]
        private readonly bool $final,
        array $items,
        // free notes in the head of the calculation, e.g. when the upgrade is scheduled
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private readonly ?string $headerNotes = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->items = array_map(static fn (UpgradeItem $item): array => $item->toArray(), $items);
        $calculation->addVersion($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCalculation(): UpgradeCalculation
    {
        return $this->calculation;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getHourlyRate(): float
    {
        return $this->hourlyRate;
    }

    public function getManagementPercent(): float
    {
        return $this->managementPercent;
    }

    public function getRemarks(): ?string
    {
        return $this->remarks;
    }

    public function getHeaderNotes(): ?string
    {
        return $this->headerNotes;
    }

    public function isFinal(): bool
    {
        return $this->final;
    }

    /**
     * @return list<UpgradeItem>
     */
    public function getItems(?UpgradeSection $section = null): array
    {
        $items = array_map(UpgradeItem::fromArray(...), $this->items);

        return null === $section ? $items : array_values(array_filter($items, static fn (UpgradeItem $item): bool => $item->section === $section));
    }
}
