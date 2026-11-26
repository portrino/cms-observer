<?php

declare(strict_types=1);

namespace App\Entity\Craft;

use App\Entity\CoreReleaseInterface;
use App\Repository\Craft\CoreReleaseRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A stable Craft CMS release (craftcms/cms on Packagist). Insecure when its
 * major is past end of life or a Packagist security advisory affects it.
 */
#[ORM\Entity(repositoryClass: CoreReleaseRepository::class)]
#[ORM\Table(name: 'craft_core_release')]
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
        #[ORM\Column(length: 32)]
        private string $latestVersion,
        #[ORM\Column]
        private bool $insecure,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $releaseDate,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(string $latestVersion, bool $insecure): void
    {
        $this->latestVersion = $latestVersion;
        $this->insecure = $insecure;
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

    public function getMajor(): int
    {
        return (int) explode('.', $this->version)[0];
    }

    public function getLatestVersion(): string
    {
        return $this->latestVersion;
    }

    public function isLatest(): bool
    {
        return $this->version === $this->latestVersion;
    }

    public function isInsecure(): bool
    {
        return $this->insecure;
    }

    public function getReleaseDate(): ?\DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
