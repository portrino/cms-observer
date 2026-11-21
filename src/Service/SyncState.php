<?php

declare(strict_types=1);

namespace App\Service;

final readonly class SyncState
{
    public function __construct(
        public string $command,
        public string $description,
        public string $schedule,
        public ?\DateTimeImmutable $lastSucceededAt,
        public bool $overdue,
        public ?\DateTimeImmutable $lastFailedAt = null,
        public ?string $lastError = null,
    ) {
    }
}
