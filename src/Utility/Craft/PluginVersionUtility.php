<?php

declare(strict_types=1);

namespace App\Utility\Craft;

use App\Entity\Craft\PluginVersion;
use App\Utility\ComposerUtility;

/**
 * Rules for the Packagist versions of Craft plugins.
 */
final class PluginVersionUtility
{
    private function __construct()
    {
    }

    /** Whether a version installs on a Craft CMS version; an unknown "require" says nothing against it */
    public static function installsOnCraft(PluginVersion $version, ?string $coreVersion): bool
    {
        if (null === $coreVersion || null === $version->getCraftConstraint()) {
            return true;
        }

        // a constraint Composer can't parse says nothing against installing it
        return ComposerUtility::satisfies($coreVersion, $version->getCraftConstraint()) ?? true;
    }
}
