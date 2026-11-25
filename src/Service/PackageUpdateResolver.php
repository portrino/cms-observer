<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Typo3\TerExtensionVersion;
use App\Entity\Wordpress\PluginVersion;
use App\Repository\Typo3\TerExtensionVersionRepository;
use App\Repository\Wordpress\PluginInfoRepository;
use App\Repository\Wordpress\PluginVersionRepository;
use App\Repository\Wordpress\ThemeInfoRepository;

/**
 * The one rule for "is there an update?": a package is outdated only when a newer
 * version runs on the site's core. Newer versions that need a newer core are reported
 * separately, so a site on the newest installable version isn't flagged.
 */
class PackageUpdateResolver
{
    public function __construct(
        private readonly TerExtensionVersionRepository $terVersionRepository,
        private readonly PluginVersionRepository $wordpressPluginVersionRepository,
        private readonly PluginInfoRepository $wordpressPluginInfoRepository,
        private readonly ThemeInfoRepository $wordpressThemeInfoRepository,
    ) {
    }

    public function forTypo3Extension(string $extensionKey, string $installedVersion, ?string $coreVersion): PackageUpdate
    {
        $versions = $this->terVersionRepository->findBy(['extensionKey' => $extensionKey]);
        if ([] === $versions) {
            return new PackageUpdate();
        }

        $newer = array_filter($versions, static fn (TerExtensionVersion $v): bool => version_compare($v->getVersion(), $installedVersion, '>'));
        usort($newer, static fn (TerExtensionVersion $a, TerExtensionVersion $b): int => version_compare($b->getVersion(), $a->getVersion()));
        if (null === $coreVersion) {
            return new PackageUpdate([] !== $newer, ($newer[0] ?? null)?->getVersion());
        }

        $coreMajor = (int) explode('.', $coreVersion)[0];
        $compatible = array_values(array_filter($newer, static fn (TerExtensionVersion $v): bool => in_array($coreMajor, $v->getTypo3MajorVersions(), true)));

        // The next major the installed or a newer version supports, so a list of packages shows at a
        // glance which ones are ready for a core upgrade: the installed version if it supports it
        // already, otherwise the newest version that does.
        $installed = array_find($versions, static fn (TerExtensionVersion $v): bool => $v->getVersion() === $installedVersion);
        $nextMajor = self::nextMajor(null === $installed ? $newer : [$installed, ...$newer], $coreMajor);
        $forNextMajor = null === $nextMajor ? null : self::firstSupporting($nextMajor, $installed, $newer);

        return new PackageUpdate(
            [] !== $compatible,
            ($compatible[0] ?? null)?->getVersion(),
            null === $forNextMajor ? null : (string) $nextMajor,
            $forNextMajor?->getVersion(),
        );
    }

    /**
     * The lowest TYPO3 major above the site's one that any of the versions supports.
     *
     * @param list<TerExtensionVersion> $versions
     */
    private static function nextMajor(array $versions, int $coreMajor): ?int
    {
        $majors = array_filter(
            array_merge(...array_map(static fn (TerExtensionVersion $v): array => $v->getTypo3MajorVersions(), $versions)),
            static fn (int $major): bool => $major > $coreMajor,
        );

        return [] === $majors ? null : min($majors);
    }

    /**
     * The installed version if it supports the major already, otherwise the newest version that does.
     *
     * @param list<TerExtensionVersion> $newer newest first
     */
    private static function firstSupporting(int $major, ?TerExtensionVersion $installed, array $newer): ?TerExtensionVersion
    {
        $supports = static fn (TerExtensionVersion $v): bool => in_array($major, $v->getTypo3MajorVersions(), true);
        if (null !== $installed && $supports($installed)) {
            return $installed;
        }

        return array_find($newer, $supports);
    }

    /**
     * WordPress installs plugins that weren't tested against the site's version, so only
     * "requires" decides; an unknown "requires" counts as installable.
     */
    public function forWordpressPlugin(string $slug, string $installedVersion, ?string $coreVersion): PackageUpdate
    {
        $newer = array_map(
            static fn (PluginVersion $v): array => [$v->getVersion(), $v->getRequiresWpVersion()],
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

    /**
     * Only the latest version is synced for themes: outdated when it's newer and runs on the
     * site's WordPress, otherwise a hint at the WordPress it needs.
     */
    public function forWordpressTheme(string $slug, string $installedVersion, ?string $coreVersion): PackageUpdate
    {
        $info = $this->wordpressThemeInfoRepository->findOneBySlug($slug);
        if (null === $info) {
            return new PackageUpdate(); // not synced yet or unknown to WordPress.org, e.g. a premium theme
        }
        if (!version_compare($info->getLatestVersion(), $installedVersion, '>')) {
            return new PackageUpdate(false);
        }

        $requires = $info->getRequiresWpVersion();
        if (null === $coreVersion || null === $requires || version_compare($coreVersion, $requires, '>=')) {
            return new PackageUpdate(true, $info->getLatestVersion());
        }

        return new PackageUpdate(false, null, $requires, $info->getLatestVersion());
    }
}
