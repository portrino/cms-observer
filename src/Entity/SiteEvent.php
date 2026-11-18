<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One change in a site's history (e.g. "news 12.3.0 => 12.3.1"). Packages are referenced by
 * name, not by SitePackage, so the history survives the package's removal.
 */
#[ORM\Entity(repositoryClass: SiteEventRepository::class)]
#[ORM\Index(name: 'site_event_site_occurred', columns: ['site_id', 'occurred_at'])]
class SiteEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Site::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Site $site,
        #[ORM\Column(enumType: SiteEventType::class)]
        private SiteEventType $type,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $subject = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $oldValue = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $newValue = null,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $detail = null,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getType(): SiteEventType
    {
        return $this->type;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }
}
