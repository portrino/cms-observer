<?php

declare(strict_types=1);

namespace App\Service\Craft;

use App\Entity\Craft\Advisory;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;

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

    /** Whether a version can be checked at all; a branch like "dev-main" can't */
    public static function canRate(string $version): bool
    {
        try {
            new VersionParser()->normalize($version);
        } catch (\UnexpectedValueException) {
            return false; // the caller treats it as unknown
        }

        // Composer accepts branches as versions, but nothing says which release they contain
        return 'dev' !== VersionParser::parseStability($version);
    }

    private static function affects(Advisory $advisory, string $version): bool
    {
        try {
            return Semver::satisfies($version, $advisory->getAffectedVersions());
        } catch (\UnexpectedValueException) {
            // a branch like "dev-main" or a malformed constraint can't be rated: unknown, not insecure
            return false;
        }
    }
}
