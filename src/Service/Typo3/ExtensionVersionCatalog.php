<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Dto\Typo3\ExtensionVersion;
use App\Entity\Typo3\PackagistVersion;
use App\Entity\Typo3\TerExtensionVersion;
use App\Repository\Typo3\PackagistVersionRepository;
use App\Repository\Typo3\TerExtensionVersionRepository;

/**
 * The versions of a TYPO3 package from the TER and from Packagist in one list (ADR-0006):
 * authors often update the TER late or never. A version in both takes its TYPO3 majors from
 * Packagist, which Composer installs by, and its security flag and comment from the TER.
 */
class ExtensionVersionCatalog
{
    public function __construct(
        private readonly TerExtensionVersionRepository $terVersionRepository,
        private readonly PackagistVersionRepository $packagistVersionRepository,
    ) {
    }

    /**
     * @return list<ExtensionVersion> newest first
     */
    public function versionsOf(string $extensionKey, ?string $composerName): array
    {
        $ter = $packagist = $names = [];
        foreach ($this->terVersionRepository->findBy(['extensionKey' => $extensionKey]) as $version) {
            $names[] = $version->getVersion();
            $ter[$version->getVersion()] = $version;
        }
        foreach (null !== $composerName ? $this->packagistVersionRepository->findByPackageName($composerName) : [] as $version) {
            // tags like "v4.1.0" are the TER's "4.1.0"
            $name = ltrim($version->getVersion(), 'vV');
            $names[] = $name;
            $packagist[$name] = $version;
        }

        $versions = [];
        foreach (array_unique($names) as $name) {
            $versions[] = self::merged($name, $ter[$name] ?? null, $packagist[$name] ?? null);
        }
        usort($versions, static fn (ExtensionVersion $a, ExtensionVersion $b): int => version_compare($b->version, $a->version));

        return $versions;
    }

    private static function merged(string $version, ?TerExtensionVersion $ter, ?PackagistVersion $packagist): ExtensionVersion
    {
        return new ExtensionVersion(
            $version,
            $packagist?->getTypo3Majors() ?? $ter?->getTypo3MajorVersions() ?? [],
            $ter?->isInsecure() ?? false,
            $ter?->getUploadComment(),
            $ter?->getUploadDate() ?? $packagist?->getReleaseDate(),
            null !== $ter,
            null !== $packagist,
        );
    }
}
