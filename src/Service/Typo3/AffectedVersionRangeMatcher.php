<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Service\StringList;

/**
 * Parses the free-text "Affected Versions" field from a TYPO3 security
 * advisory (e.g. "10.9.2 and below, 11.0.0 - 12.6.0, 13.0.0 - 13.2.0") and
 * checks whether a given version falls into it.
 *
 * ponytail: this covers every phrasing seen across the sampled advisories
 * (exact version, "X and below", "X and above", "A - B" inclusive range).
 * A segment this doesn't recognize can't be safely ruled in or out, so it
 * makes the whole check return null (undetermined) rather than guessing -
 * upgrade this if a differently-worded advisory shows up.
 */
final class AffectedVersionRangeMatcher
{
    private function __construct()
    {
    }

    /**
     * @return bool|null true if affected, false if confirmed not affected, null if undetermined
     */
    public static function matches(string $affectedVersionsRaw, string $version): ?bool
    {
        $segments = StringList::trimExplode(',', $affectedVersionsRaw);

        if ([] === $segments) {
            return null;
        }

        $allParseable = true;

        foreach ($segments as $segment) {
            $result = self::matchesSegment($segment, $version);

            if (null === $result) {
                $allParseable = false;
                continue;
            }

            if (true === $result) {
                return true;
            }
        }

        return $allParseable ? false : null;
    }

    private static function matchesSegment(string $segment, string $version): ?bool
    {
        if (1 === preg_match('/^([\d.]+)\s+and\s+below$/i', $segment, $matches)) {
            return version_compare($version, $matches[1], '<=');
        }

        if (1 === preg_match('/^([\d.]+)\s+and\s+above$/i', $segment, $matches)) {
            return version_compare($version, $matches[1], '>=');
        }

        if (1 === preg_match('/^([\d.]+)\s*-\s*([\d.]+)$/', $segment, $matches)) {
            return version_compare($version, $matches[1], '>=') && version_compare($version, $matches[2], '<=');
        }

        if (1 === preg_match('/^([\d.]+)$/', $segment, $matches)) {
            return version_compare($version, $matches[1], '==');
        }

        return null;
    }
}
