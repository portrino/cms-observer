<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteReportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteReportRepository::class)]
class SiteReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Site::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Site $site,
        #[ORM\Column(length: 32)]
        private string $coreVersion,
        #[ORM\Column(length: 32)]
        private string $phpVersion,
        #[ORM\Column(length: 32)]
        private string $dbVersion,
        #[ORM\Column]
        private array $payload,
    ) {
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getCoreVersion(): string
    {
        return $this->coreVersion;
    }

    public function getPhpVersion(): string
    {
        return $this->phpVersion;
    }

    public function getDbVersion(): string
    {
        return $this->dbVersion;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
