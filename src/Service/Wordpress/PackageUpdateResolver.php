<?php

declare(strict_types=1);

namespace App\Service\Wordpress;

use App\Entity\Wordpress\PluginVersion;
use App\Repository\Wordpress\PluginInfoRepository;
use App\Repository\Wordpress\PluginVersionRepository;
use App\Repository\Wordpress\ThemeInfoRepository;
use App\Service\PackageUpdate;

/**
 * Whether a WordPress plugin or theme has an update for the site's WordPress, by the synced
 * wordpress.org versions (the rule: PackageUpdate).
 */
class PackageUpdateResolver
{
    public function __construct(
        private readonly PluginVersionRepository $pluginVersionRepository,
        private readonly PluginInfoRepository $pluginInfoRepository,
        private readonly ThemeInfoRepository $themeInfoRepository,
    ) {
    }

    /**
     * WordPress installs plugins that weren't tested against the site's version, so only
     * "requires" decides; an unknown "requires" counts as installable.
     */
    public function forPlugin(string $slug, string $installedVersion, ?string $coreVersion): PackageUpdate
    {
        $newer = array_map(
            static fn (PluginVersion $v): array => [$v->getVersion(), $v->getRequiresWpVersion()],
            $this->pluginVersionRepository->findNewerThan($slug, $installedVersion),
        );
        // the version history may not be synced yet, the plugin info has at least the latest version
        if ([] === $newer) {
            $info = $this->pluginInfoRepository->findOneBySlug($slug);
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
    public function forTheme(string $slug, string $installedVersion, ?string $coreVersion): PackageUpdate
    {
        $info = $this->themeInfoRepository->findOneBySlug($slug);
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
