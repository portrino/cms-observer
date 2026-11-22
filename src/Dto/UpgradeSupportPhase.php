<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Support of one core major, for the timeline of an upgrade calculation.
 */
final readonly class UpgradeSupportPhase
{
    public function __construct(
        public int $major,
        public \DateTimeImmutable $start,
        // end of the free core updates
        public \DateTimeImmutable $maintainedUntil,
        // end of the paid extended support, if the CMS has one
        public ?\DateTimeImmutable $extendedUntil = null,
    ) {
    }

    public function getEnd(): \DateTimeImmutable
    {
        return $this->extendedUntil ?? $this->maintainedUntil;
    }
}
