<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Update situation of an installed package, see PackageUpdateResolver.
 */
final readonly class PackageUpdate
{
    public function __construct(
        // null: unknown, e.g. the package's versions weren't synced yet
        public ?bool $outdated = null,
        public ?string $latestCompatibleVersion = null,
        // a newer version that only runs on a newer core, e.g. core "14" (TYPO3) or "6.9" (WordPress) with package "13.0.0"
        public ?string $newerCoreVersion = null,
        public ?string $newerCorePackageVersion = null,
    ) {
    }
}
