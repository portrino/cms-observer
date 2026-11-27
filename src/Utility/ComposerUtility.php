<?php

declare(strict_types=1);

namespace App\Utility;

use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\VersionParser;

/**
 * Composer's version rules for the packages from Packagist, as Composer itself would apply them.
 */
final class ComposerUtility
{
    private function __construct()
    {
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
        $parser = new VersionParser();
        $parsed = self::parse($constraint);
        if (null === $parsed || !$parser->isValid($version)) {
            return null; // the callers treat it as unknown
        }

        return $parsed->matches(new Constraint('==', $parser->normalize($version)));
    }

    /**
     * Whether a constraint admits any release of a major, e.g. "^13.4 || ^14.0" admits 14.
     * A constraint Composer can't parse admits none.
     */
    public static function admitsMajor(string $constraint, int $major): bool
    {
        $majorRange = new VersionParser()->parseConstraints(sprintf('>=%d.0.0.0-dev <%d.0.0.0-dev', $major, $major + 1));

        // nothing to install by
        return self::parse($constraint)?->matches($majorRange) ?? false;
    }

    private static function parse(string $constraint): ?ConstraintInterface
    {
        try {
            return new VersionParser()->parseConstraints($constraint);
        } catch (\UnexpectedValueException) {
            return null; // a malformed constraint, each caller decides what that means
        }
    }
}
