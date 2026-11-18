<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\Typo3TerExtensionMetaRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Caches one extension key's TER metadata (Packagist/repository/manual
 * links) - fetched via TerExtensionApiClient::getExtensionMeta() alongside
 * the version history in Typo3ExtensionVersionSynchronizer, since it lives
 * on the same per-key TER endpoint. One row per extension key, unlike
 * Typo3TerExtensionVersion which has one row per (key, version).
 */
#[ORM\Entity(repositoryClass: Typo3TerExtensionMetaRepository::class)]
class Typo3TerExtensionMeta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $fetchedAt;

    public function __construct(
        #[ORM\Column(length: 255, unique: true)]
        private string $extensionKey,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $packagistUrl,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $repositoryUrl,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $manualUrl,
    ) {
        $this->fetchedAt = new \DateTimeImmutable();
    }

    public function update(?string $packagistUrl, ?string $repositoryUrl, ?string $manualUrl): void
    {
        $this->packagistUrl = $packagistUrl;
        $this->repositoryUrl = $repositoryUrl;
        $this->manualUrl = $manualUrl;
        $this->fetchedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExtensionKey(): string
    {
        return $this->extensionKey;
    }

    public function getPackagistUrl(): ?string
    {
        return $this->packagistUrl;
    }

    public function getRepositoryUrl(): ?string
    {
        return $this->repositoryUrl;
    }

    public function getManualUrl(): ?string
    {
        return $this->manualUrl;
    }

    public function getFetchedAt(): \DateTimeImmutable
    {
        return $this->fetchedAt;
    }
}
