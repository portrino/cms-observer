<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Support of one core major, for the timeline of an upgrade calculation:
 * development (preview releases), regular maintenance, priority bugfixes, extended support.
 */
final readonly class UpgradeSupportPhase
{
    /**
     * @param list<\DateTimeImmutable> $previewReleases releases before the stable one, e.g. TYPO3's sprint releases
     */
    public function __construct(
        public int $major,
        // first release of the major
        public \DateTimeImmutable $start,
        // end of the free core updates
        public \DateTimeImmutable $maintainedUntil,
        // end of the paid extended support, if the CMS has one
        public ?\DateTimeImmutable $extendedUntil = null,
        // first stable (LTS) release; before it the major is in development
        public ?\DateTimeImmutable $stableSince = null,
        // only security and priority fixes from here on
        public ?\DateTimeImmutable $prioritySince = null,
        public array $previewReleases = [],
    ) {
    }

    public function getEnd(): \DateTimeImmutable
    {
        return $this->extendedUntil ?? $this->maintainedUntil;
    }
}
