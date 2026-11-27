<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Dto\Typo3\ExtensionVersion;
use App\Service\PackageUpdate;

/**
 * Whether a TYPO3 extension has an update for the site's TYPO3, by its TER and Packagist
 * versions, and which version supports the next major (the rule: PackageUpdate).
 */
class PackageUpdateResolver
{
    public function __construct(private readonly ExtensionVersionCatalog $versionCatalog)
    {
    }

    public function forExtension(string $extensionKey, ?string $composerName, string $installedVersion, ?string $coreVersion): PackageUpdate
    {
        $versions = $this->versionCatalog->versionsOf($extensionKey, $composerName);
        if ([] === $versions) {
            return new PackageUpdate();
        }

        // newest first, like the catalog
        $newer = array_values(array_filter($versions, static fn (ExtensionVersion $v): bool => version_compare($v->version, $installedVersion, '>')));
        if (null === $coreVersion) {
            return new PackageUpdate([] !== $newer, ($newer[0] ?? null)?->version);
        }

        $coreMajor = (int) explode('.', $coreVersion)[0];
        $compatible = array_values(array_filter($newer, static fn (ExtensionVersion $v): bool => $v->supports($coreMajor)));

        // The next major the installed or a newer version supports, so a list of packages shows at a
        // glance which ones are ready for a core upgrade: the installed version if it supports it
        // already, otherwise the newest version that does.
        $installed = array_find($versions, static fn (ExtensionVersion $v): bool => $v->version === $installedVersion);
        $nextMajor = self::nextMajor(null === $installed ? $newer : [$installed, ...$newer], $coreMajor);
        $forNextMajor = null === $nextMajor ? null : self::firstSupporting($nextMajor, $installed, $newer);

        return new PackageUpdate(
            [] !== $compatible,
            ($compatible[0] ?? null)?->version,
            null === $forNextMajor ? null : (string) $nextMajor,
            $forNextMajor?->version,
        );
    }

    /**
     * The lowest TYPO3 major above the site's one that any of the versions supports.
     *
     * @param list<ExtensionVersion> $versions
     */
    private static function nextMajor(array $versions, int $coreMajor): ?int
    {
        $majors = array_filter(
            array_merge(...array_map(static fn (ExtensionVersion $v): array => $v->typo3MajorVersions, $versions)),
            static fn (int $major): bool => $major > $coreMajor,
        );

        return [] === $majors ? null : min($majors);
    }

    /**
     * The installed version if it supports the major already, otherwise the newest version that does.
     *
     * @param list<ExtensionVersion> $newer newest first
     */
    private static function firstSupporting(int $major, ?ExtensionVersion $installed, array $newer): ?ExtensionVersion
    {
        if (null !== $installed && $installed->supports($major)) {
            return $installed;
        }

        return array_find($newer, static fn (ExtensionVersion $v): bool => $v->supports($major));
    }
}
