<?php

declare(strict_types=1);

namespace App\Utility\Typo3;

use App\Utility\ComposerUtility;
use App\Utility\StringUtility;

/**
 * Rules for the versions of TYPO3 extensions: which TYPO3 majors a version installs on, and
 * whether an advisory affects it.
 */
final class ExtensionUtility
{
    /** TYPO3's "!!!" commit prefix (also as "[!!!]") and the usual words for a breaking change */
    private const string BREAKING_CHANGE = '/!!!|breaking|incompatib|inkompatib/i';

    /**
     * In a commit subject only the markers: "!!!" (TYPO3), "BREAKING CHANGE" and "feat!:"
     * (Conventional Commits); "breaking" alone also stands in "non-breaking" or "respect a
     * breaking change of the core".
     */
    private const string BREAKING_COMMIT = '/!!!|BREAKING[ -]CHANGE|^\w+(\([^)]*\))?!:/';

    /** The footer of a Conventional Commit, on a line of its own in the message */
    private const string BREAKING_FOOTER = '/^BREAKING[ -]CHANGE:/m';

    /** the core of TYPO3 8 and older as one package */
    private const string LEGACY_CORE = 'typo3/cms';

    /** in the typo3/cms-* namespace, but with versions of their own */
    private const array OWN_VERSIONS = ['typo3/cms-composer-installers', 'typo3/cms-cli'];

    private function __construct()
    {
    }

    /**
     * The TYPO3 majors a package version installs on, by its "require" of the core packages.
     * Only since TYPO3 14 must an extension require typo3/cms-core; older versions often name
     * only e.g. typo3/cms-extbase. All core packages of a project share one version, so a major
     * counts when every one of them admits it.
     *
     * @param array<string, string> $require
     * @param list<int>             $majors  the TYPO3 majors to check, e.g. the known ones
     *
     * @return list<int>|null null when the version requires no core package, so nothing says
     */
    public static function supportedCoreMajors(array $require, array $majors): ?array
    {
        $constraints = array_filter(
            $require,
            static fn (string $package): bool => (self::LEGACY_CORE === $package || str_starts_with($package, 'typo3/cms-')) && !in_array($package, self::OWN_VERSIONS, true),
            ARRAY_FILTER_USE_KEY,
        );
        if ([] === $constraints) {
            return null;
        }

        return array_values(array_filter($majors, static fn (int $major): bool => [] === array_filter(
            $constraints,
            static fn (string $constraint): bool => !ComposerUtility::admitsMajor($constraint, $major),
        )));
    }

    /** Whether a changelog text, e.g. a TER upload comment, mentions a breaking change */
    public static function mentionsBreakingChange(?string $text): bool
    {
        return 1 === preg_match(self::BREAKING_CHANGE, (string) $text);
    }

    /** Whether a commit subject marks the commit as a breaking change */
    public static function marksBreakingCommit(string $subject): bool
    {
        return 1 === preg_match(self::BREAKING_COMMIT, $subject);
    }

    /** Whether a commit message has a "BREAKING CHANGE:" footer, as Conventional Commits write it */
    public static function hasBreakingFooter(string $message): bool
    {
        return 1 === preg_match(self::BREAKING_FOOTER, $message);
    }

    /**
     * Whether a version is in the free-text "Affected Versions" of a TYPO3 security advisory,
     * e.g. "10.9.2 and below, 11.0.0 - 12.6.0, 13.0.0 - 13.2.0".
     *
     * shortcut: covers every phrasing seen across the sampled advisories (exact version,
     * "X and below", "X and above", "A - B" inclusive range); a segment it doesn't recognize
     * makes the whole check undetermined rather than guessing; extend it when a differently
     * worded advisory shows up.
     *
     * @return bool|null true if affected, false if confirmed not affected, null if undetermined
     */
    public static function matchesAffectedVersions(string $affectedVersionsRaw, string $version): ?bool
    {
        $segments = StringUtility::trimExplode(',', $affectedVersionsRaw);

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
