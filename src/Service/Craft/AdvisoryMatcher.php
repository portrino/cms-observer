<?php

declare(strict_types=1);

namespace App\Service\Craft;

use App\Entity\Craft\Advisory;
use App\Service\ComposerVersion;

/**
 * Which of a package's advisories affect an installed version.
 */
final class AdvisoryMatcher
{
    private function __construct()
    {
    }

    /**
     * @param list<Advisory> $advisories of one package
     */
    public static function mostSevere(array $advisories, string $version): ?Advisory
    {
        $matches = array_filter($advisories, static fn (Advisory $advisory): bool => self::affects($advisory, $version));
        usort($matches, static fn (Advisory $a, Advisory $b): int => $b->getSeverityRank() <=> $a->getSeverityRank());

        return $matches[0] ?? null;
    }

    private static function affects(Advisory $advisory, string $version): bool
    {
        // unknown is not insecure
        return ComposerVersion::satisfies($version, $advisory->getAffectedVersions()) ?? false;
    }
}
