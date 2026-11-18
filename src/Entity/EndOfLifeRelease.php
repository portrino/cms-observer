<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EndOfLifeReleaseRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One release cycle (e.g. product "php", cycle "8.3") synced from
 * endoflife.date's v1 API. "Cycle" here means major.minor, not a full patch
 * version - endoflife.date tracks support windows per cycle, not per patch.
 */
#[ORM\Entity(repositoryClass: EndOfLifeReleaseRepository::class)]
#[ORM\UniqueConstraint(name: 'eol_product_cycle', columns: ['product', 'cycle'])]
class EndOfLifeRelease
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    // Newest release in this cycle that shipped security fixes (php.net release tags) - PHP only.
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $latestSecurityVersion = null;

    // endoflife.date "isEoes": extended support (e.g. TYPO3 ELTS) has ended as well; null if the product has none.
    #[ORM\Column(nullable: true)]
    private ?bool $extendedSupportEnded = null;

    public function __construct(
        #[ORM\Column(length: 32)]
        private string $product,
        #[ORM\Column(length: 32)]
        private string $cycle,
        #[ORM\Column]
        private bool $isEol,
        #[ORM\Column(length: 32)]
        private string $latestVersion,
        #[ORM\Column(length: 255)]
        private string $eolUrl,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(bool $isEol, string $latestVersion, string $eolUrl): void
    {
        $this->isEol = $isEol;
        $this->latestVersion = $latestVersion;
        $this->eolUrl = $eolUrl;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): string
    {
        return $this->product;
    }

    public function getCycle(): string
    {
        return $this->cycle;
    }

    public function isEol(): bool
    {
        return $this->isEol;
    }

    public function getLatestVersion(): string
    {
        return $this->latestVersion;
    }

    public function getEolUrl(): string
    {
        return $this->eolUrl;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLatestSecurityVersion(): ?string
    {
        return $this->latestSecurityVersion;
    }

    public function setLatestSecurityVersion(?string $latestSecurityVersion): void
    {
        $this->latestSecurityVersion = $latestSecurityVersion;
    }

    public function setExtendedSupportEnded(?bool $extendedSupportEnded): void
    {
        $this->extendedSupportEnded = $extendedSupportEnded;
    }

    /**
     * @return 'supported'|'extended'|'unsupported' regular support, extended (paid) support only, or none at all
     */
    public function getSupportStatus(): string
    {
        if (!$this->isEol) {
            return 'supported';
        }

        return false === $this->extendedSupportEnded ? 'extended' : 'unsupported';
    }
}
