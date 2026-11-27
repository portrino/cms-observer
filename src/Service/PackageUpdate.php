<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Update situation of an installed package, by the PackageUpdateResolver of its CMS. The one
 * rule for "is there an update?": a package is outdated only when a newer version runs on the
 * site's core. Newer versions that need a newer core are reported separately, so a site on the
 * newest installable version isn't flagged.
 */
final readonly class PackageUpdate
{
    public function __construct(
        // null: unknown, e.g. the package's versions weren't synced yet
        public ?bool $outdated = null,
        public ?string $latestCompatibleVersion = null,
        // a version for a newer core, e.g. core "14" (TYPO3) or "6.9" (WordPress) with package "13.0.0";
        // for TYPO3 also the installed version itself when it supports the next major already
        public ?string $newerCoreVersion = null,
        public ?string $newerCorePackageVersion = null,
    ) {
    }
}
