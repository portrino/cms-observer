<?php

declare(strict_types=1);

namespace App\Service\Craft;

use App\Entity\Craft\PluginVersion;
use App\Repository\Craft\PluginVersionRepository;
use App\Service\PackageUpdate;
use App\Utility\Craft\PluginVersionUtility;

/**
 * Whether a Craft plugin has an update for the site's Craft CMS, by its Packagist versions
 * (the rule: PackageUpdate).
 */
class PackageUpdateResolver
{
    public function __construct(private readonly PluginVersionRepository $pluginVersionRepository)
    {
    }

    /**
     * Composer decides what a Craft site can install: a version whose "require" of craftcms/cms
     * the site's Craft doesn't satisfy needs a newer Craft, an unknown "require" counts as installable.
     */
    public function forPlugin(string $composerName, string $installedVersion, ?string $coreVersion): PackageUpdate
    {
        $newer = $this->pluginVersionRepository->findNewerThan($composerName, $installedVersion);
        if (null === $newer || [] === $this->pluginVersionRepository->findByPackageName($composerName)) {
            return new PackageUpdate(); // an installed branch, or the versions aren't synced (yet)
        }
        if ([] === $newer) {
            return new PackageUpdate(false);
        }

        $compatible = array_values(array_filter($newer, static fn (PluginVersion $v): bool => PluginVersionUtility::installsOnCraft($v, $coreVersion)));
        $needsNewerCore = array_values(array_filter($newer, static fn (PluginVersion $v): bool => !in_array($v, $compatible, true)));

        return new PackageUpdate(
            [] !== $compatible,
            ($compatible[0] ?? null)?->getVersion(),
            // "^6.0.0" => "6": the Craft major the newest version needs
            1 === preg_match('/\d+/', (string) ($needsNewerCore[0] ?? null)?->getCraftConstraint(), $major) ? $major[0] : null,
            ($needsNewerCore[0] ?? null)?->getVersion(),
        );
    }
}
