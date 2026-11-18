<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\Typo3ExtensionAdvisoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: Typo3ExtensionAdvisoryRepository::class)]
class Typo3ExtensionAdvisory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Column(length: 32, unique: true)]
        private string $advisoryId,
        #[ORM\Column(length: 255)]
        private string $extensionKey,
        #[ORM\Column(length: 255)]
        private string $title,
        #[ORM\Column(length: 255)]
        private string $url,
        #[ORM\Column]
        private \DateTimeImmutable $publishedAt,
        #[ORM\Column(length: 32, nullable: true)]
        private ?string $severity,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $cveIds,
        #[ORM\Column(type: 'text')]
        private string $affectedVersionsRaw,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdvisoryId(): string
    {
        return $this->advisoryId;
    }

    public function getExtensionKey(): string
    {
        return $this->extensionKey;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function getCveIds(): ?string
    {
        return $this->cveIds;
    }

    public function getAffectedVersionsRaw(): string
    {
        return $this->affectedVersionsRaw;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
