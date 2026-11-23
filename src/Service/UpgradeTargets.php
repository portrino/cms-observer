<?php

declare(strict_types=1);

namespace App\Service;

final class UpgradeTargets
{
    /**
     * @param list<int> $knownMajors
     *
     * @return list<int> the known majors above the one of $currentVersion, ascending; none without a version
     */
    public static function above(?string $currentVersion, array $knownMajors): array
    {
        if (null === $currentVersion || !self::isRelease($currentVersion)) {
            return [];
        }

        $majors = array_values(array_unique(array_filter($knownMajors, static fn (int $major): bool => $major > (int) $currentVersion)));
        sort($majors);

        return $majors;
    }

    /** A plain version like "13.4.2", unlike "dev-main" or "v12-minimal" */
    public static function isRelease(string $version): bool
    {
        return 1 === preg_match('/^\d+(\.\d+)*$/', $version);
    }
}
