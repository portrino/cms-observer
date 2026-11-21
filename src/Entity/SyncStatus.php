<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SyncStatusRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * When a scheduled sync last succeeded, see SyncMonitor. One row per command, created on its
 * first success or by app:check-syncs, whichever comes first.
 */
#[ORM\Entity(repositoryClass: SyncStatusRepository::class)]
class SyncStatus
{
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSucceededAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $overdueNotifiedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 128)]
        private string $command,
        // the baseline until the first success, so a new installation isn't overdue right away
        #[ORM\Column]
        private \DateTimeImmutable $trackedSince,
    ) {
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getLastSucceededAt(): ?\DateTimeImmutable
    {
        return $this->lastSucceededAt;
    }

    public function getLastSucceededOrTrackedSince(): \DateTimeImmutable
    {
        return $this->lastSucceededAt ?? $this->trackedSince;
    }

    public function recordSuccess(\DateTimeImmutable $at): void
    {
        $this->lastSucceededAt = $at;
        $this->overdueNotifiedAt = null;
    }

    public function isOverdueNotified(): bool
    {
        return null !== $this->overdueNotifiedAt;
    }

    public function markOverdueNotified(\DateTimeImmutable $at): void
    {
        $this->overdueNotifiedAt = $at;
    }
}
