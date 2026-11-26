<?php

declare(strict_types=1);

namespace App\Entity\Craft;

use App\Repository\Craft\AdvisoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A Packagist security advisory of Craft CMS (craftcms/cms) or a Craft plugin, synced by
 * AdvisorySynchronizer; Packagist merges the GitHub Advisory Database and FriendsOfPHP.
 */
#[ORM\Entity(repositoryClass: AdvisoryRepository::class)]
#[ORM\Table(name: 'craft_advisory')]
#[ORM\Index(name: 'craft_advisory_package', columns: ['package_name'])]
class Advisory
{
    // Packagist's "severity" follows CVSS 3; a higher rank is worse
    private const array SEVERITY_RANK = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        // Packagist's PKSA id
        #[ORM\Column(length: 32, unique: true)]
        private string $advisoryId,
        #[ORM\Column(length: 255)]
        private string $packageName,
        #[ORM\Column(type: 'text')]
        private string $title,
        #[ORM\Column(length: 255)]
        private string $link,
        // a Composer constraint, e.g. ">=4.0.0-RC1,<4.18.2|>=5.0.0-RC1,<5.10.6"
        #[ORM\Column(type: 'text')]
        private string $affectedVersions,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $cve = null,
        #[ORM\Column(length: 16, nullable: true)]
        private ?string $severity = null,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(string $title, string $link, string $affectedVersions, ?string $cve, ?string $severity): void
    {
        $this->title = $title;
        $this->link = $link;
        $this->affectedVersions = $affectedVersions;
        $this->cve = $cve;
        $this->severity = $severity;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getAdvisoryId(): string
    {
        return $this->advisoryId;
    }

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function getAffectedVersions(): string
    {
        return $this->affectedVersions;
    }

    public function getCve(): ?string
    {
        return $this->cve;
    }

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function getSeverityRank(): int
    {
        return self::SEVERITY_RANK[$this->severity ?? ''] ?? 0;
    }
}
