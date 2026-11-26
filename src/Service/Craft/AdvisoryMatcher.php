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
        // Composer accepts branches as versions, but nothing says which release they contain
        return new VersionParser()->isValid($version) && 'dev' !== VersionParser::parseStability($version);
    }

    /**
     * Whether a version satisfies a Composer constraint, null if either can't be parsed
     * (a malformed constraint from Packagist): each caller decides what unknown means.
     */
    public static function satisfies(string $version, string $constraint): ?bool
    {
        try {
            return Semver::satisfies($version, $constraint);
        } catch (\UnexpectedValueException) {
            return null; // the callers treat it as unknown
        }
    }

    private static function affects(Advisory $advisory, string $version): bool
    {
        // unknown is not insecure
        return self::satisfies($version, $advisory->getAffectedVersions()) ?? false;
    }
}
