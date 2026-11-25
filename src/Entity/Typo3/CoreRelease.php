<?php

declare(strict_types=1);

namespace App\Entity\Typo3;

use App\Entity\CoreReleaseInterface;
use App\Repository\Typo3\CoreReleaseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CoreReleaseRepository::class)]
#[ORM\Table(name: 'typo3_core_release')]
class CoreRelease implements CoreReleaseInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Column(length: 32, unique: true)]
        private string $version,
        #[ORM\Column]
        private \DateTimeImmutable $releaseDate,
        #[ORM\Column(enumType: ReleaseType::class)]
        private ReleaseType $type,
        #[ORM\Column(length: 32)]
        private string $latestVersion,
        #[ORM\Column]
        private bool $isActive,
        #[ORM\Column]
        private bool $insecure,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $maintainedUntil,
        // end of the paid extended support (ELTS) of the release's major
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $eltsUntil = null,
        // release of the major's LTS version (e.g. 13.4.0), the end of its sprint releases
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $ltsReleaseDate = null,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(
        \DateTimeImmutable $releaseDate,
        ReleaseType $type,
        string $latestVersion,
        bool $isActive,
        bool $insecure,
        ?\DateTimeImmutable $maintainedUntil,
        ?\DateTimeImmutable $eltsUntil = null,
        ?\DateTimeImmutable $ltsReleaseDate = null,
    ): void {
        $this->releaseDate = $releaseDate;
        $this->type = $type;
        $this->latestVersion = $latestVersion;
        $this->isActive = $isActive;
        $this->maintainedUntil = $maintainedUntil;
        $this->insecure = $insecure;
        $this->eltsUntil = $eltsUntil;
        $this->ltsReleaseDate = $ltsReleaseDate;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Leading major component, e.g. 13 for "13.4.28".
     */
    public function getMajor(): int
    {
        return (int) explode('.', $this->version)[0];
    }

    public function getReleaseDate(): \DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function getType(): ReleaseType
    {
        return $this->type;
    }

    public function getLatestVersion(): string
    {
        return $this->latestVersion;
    }

    public function isLatest(): bool
    {
        return $this->version === $this->latestVersion;
    }

    /**
     * Whether this release's major/LTS line is still within its official
     * maintenance window (see Typo3\CoreReleaseSynchronizer).
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getMaintainedUntil(): ?\DateTimeImmutable
    {
        return $this->maintainedUntil;
    }

    public function getEltsUntil(): ?\DateTimeImmutable
    {
        return $this->eltsUntil;
    }

    public function getLtsReleaseDate(): ?\DateTimeImmutable
    {
        return $this->ltsReleaseDate;
    }

    public function isInsecure(): bool
    {
        return $this->insecure;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
