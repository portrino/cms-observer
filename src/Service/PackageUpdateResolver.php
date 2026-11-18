<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Typo3TerExtensionVersion;
use App\Entity\WordpressPluginVersion;
use App\Repository\Typo3TerExtensionVersionRepository;
use App\Repository\WordpressPluginInfoRepository;
use App\Repository\WordpressPluginVersionRepository;

/**
 * The one rule for "is there an update?": a package is outdated only when a newer
 * version runs on the site's core. Newer versions that need a newer core are reported
 * separately, so a site on the newest installable version isn't flagged.
 */
class PackageUpdateResolver
{
    public function __construct(
        private readonly Typo3TerExtensionVersionRepository $terVersionRepository,
        private readonly WordpressPluginVersionRepository $wordpressPluginVersionRepository,
        private readonly WordpressPluginInfoRepository $wordpressPluginInfoRepository,
    ) {
    }

    public function forTypo3Extension(string $extensionKey, string $installedVersion, ?string $coreVersion): PackageUpdate
    {
        $versions = $this->terVersionRepository->findBy(['extensionKey' => $extensionKey]);
        if ([] === $versions) {
            return new PackageUpdate();
        }

        $newer = array_filter($versions, static fn (Typo3TerExtensionVersion $v): bool => version_compare($v->getVersion(), $installedVersion, '>'));
        usort($newer, static fn (Typo3TerExtensionVersion $a, Typo3TerExtensionVersion $b): int => version_compare($b->getVersion(), $a->getVersion()));
        if (null === $coreVersion) {
            return new PackageUpdate([] !== $newer, ($newer[0] ?? null)?->getVersion());
        }

        $coreMajor = (int) explode('.', $coreVersion)[0];
        $compatible = array_values(array_filter($newer, static fn (Typo3TerExtensionVersion $v): bool => in_array($coreMajor, $v->getTypo3MajorVersions(), true)));

        // the next major any newer version supports, and the newest version supporting it
        $nextMajor = null;
        foreach ($newer as $version) {
            $higherMajors = array_filter($version->getTypo3MajorVersions(), static fn (int $major): bool => $major > $coreMajor);
            if ([] !== $higherMajors && !in_array($coreMajor, $version->getTypo3MajorVersions(), true)) {
                $nextMajor = null === $nextMajor ? min($higherMajors) : min($nextMajor, min($higherMajors));
            }
        }
        $forNextMajor = null === $nextMajor ? null : array_find($newer, static fn (Typo3TerExtensionVersion $v): bool => in_array($nextMajor, $v->getTypo3MajorVersions(), true));

        return new PackageUpdate(
            [] !== $compatible,
            ($compatible[0] ?? null)?->getVersion(),
            null === $forNextMajor ? null : (string) $nextMajor,
            $forNextMajor?->getVersion(),
        );
    }

    /**
     * WordPress installs plugins that weren't tested against the site's version, so only
     * "requires" decides; an unknown "requires" counts as installable.
     */
    public function forWordpressPlugin(string $slug, string $installedVersion, ?string $coreVersion): PackageUpdate
    {
        $newer = array_map(
            static fn (WordpressPluginVersion $v): array => [$v->getVersion(), $v->getRequiresWpVersion()],
            $this->wordpressPluginVersionRepository->findNewerThan($slug, $installedVersion),
        );
        // the version history may not be synced yet, the plugin info has at least the latest version
        if ([] === $newer) {
            $info = $this->wordpressPluginInfoRepository->findOneBySlug($slug);
            if (null === $info) {
                return new PackageUpdate();
            }
            if (version_compare($info->getLatestVersion(), $installedVersion, '>')) {
                $newer = [[$info->getLatestVersion(), $info->getRequiresWpVersion()]];
            }
        }

        $compatible = array_values(array_filter($newer, static fn (array $v): bool => null === $coreVersion || null === $v[1] || version_compare($coreVersion, $v[1], '>=')));
        $needsNewerCore = array_values(array_filter($newer, static fn (array $v): bool => !in_array($v, $compatible, true)));

        return new PackageUpdate(
            [] !== $compatible,
            $compatible[0][0] ?? null,
            $needsNewerCore[0][1] ?? null,
            $needsNewerCore[0][0] ?? null,
        );
    }
}
