<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Typo3\ExtensionVersion;
use App\Entity\Craft\PluginVersion as CraftPluginVersion;
use App\Entity\Wordpress\PluginVersion;
use App\Repository\Craft\PluginVersionRepository as CraftPluginVersionRepository;
use App\Repository\Wordpress\PluginInfoRepository;
use App\Repository\Wordpress\PluginVersionRepository;
use App\Repository\Wordpress\ThemeInfoRepository;
use App\Service\Typo3\ExtensionVersionCatalog;

/**
 * The one rule for "is there an update?": a package is outdated only when a newer
 * version runs on the site's core. Newer versions that need a newer core are reported
 * separately, so a site on the newest installable version isn't flagged.
 */
class PackageUpdateResolver
{
    public function __construct(
        private readonly ExtensionVersionCatalog $typo3VersionCatalog,
        private readonly PluginVersionRepository $wordpressPluginVersionRepository,
        private readonly PluginInfoRepository $wordpressPluginInfoRepository,
        private readonly ThemeInfoRepository $wordpressThemeInfoRepository,
        private readonly CraftPluginVersionRepository $craftPluginVersionRepository,
    ) {
    }

    public function forTypo3Extension(string $extensionKey, ?string $composerName, string $installedVersion, ?string $coreVersion): PackageUpdate
    {
        $versions = $this->typo3VersionCatalog->versionsOf($extensionKey, $composerName);
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

    /**
     * Composer decides what a Craft site can install: a version whose "require" of craftcms/cms
     * the site's Craft doesn't satisfy needs a newer Craft, an unknown "require" counts as installable.
     */
    public function forCraftPlugin(string $composerName, string $installedVersion, ?string $coreVersion): PackageUpdate
    {
        $newer = $this->craftPluginVersionRepository->findNewerThan($composerName, $installedVersion);
        if (null === $newer || [] === $this->craftPluginVersionRepository->findByPackageName($composerName)) {
            return new PackageUpdate(); // an installed branch, or the versions aren't synced (yet)
        }
        if ([] === $newer) {
            return new PackageUpdate(false);
        }

        $compatible = array_values(array_filter($newer, static fn (CraftPluginVersion $v): bool => self::installsOnCraft($v, $coreVersion)));
        $needsNewerCore = array_values(array_filter($newer, static fn (CraftPluginVersion $v): bool => !in_array($v, $compatible, true)));

        return new PackageUpdate(
            [] !== $compatible,
            ($compatible[0] ?? null)?->getVersion(),
            // "^6.0.0" => "6": the Craft major the newest version needs
            1 === preg_match('/\d+/', (string) ($needsNewerCore[0] ?? null)?->getCraftConstraint(), $major) ? $major[0] : null,
            ($needsNewerCore[0] ?? null)?->getVersion(),
        );
    }

    public static function installsOnCraft(CraftPluginVersion $version, ?string $coreVersion): bool
    {
        if (null === $coreVersion || null === $version->getCraftConstraint()) {
            return true;
        }

        // a constraint Composer can't parse says nothing against installing it
        return ComposerVersion::satisfies($coreVersion, $version->getCraftConstraint()) ?? true;
    }
}
