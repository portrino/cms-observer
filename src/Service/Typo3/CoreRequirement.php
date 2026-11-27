<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Service\ComposerVersion;

/**
 * The TYPO3 majors a package version installs on, by its "require" of the core packages.
 * Only since TYPO3 14 must an extension require typo3/cms-core; older versions often name
 * only e.g. typo3/cms-extbase. All core packages of a project share one version, so a major
 * counts when every one of them admits it.
 */
final class CoreRequirement
{
    /** the core of TYPO3 8 and older as one package */
    private const string LEGACY_CORE = 'typo3/cms';

    /** in the typo3/cms-* namespace, but with versions of their own */
    private const array OWN_VERSIONS = ['typo3/cms-composer-installers', 'typo3/cms-cli'];

    private function __construct()
    {
    }

    /**
     * @param array<string, string> $require
     * @param list<int>             $majors  the TYPO3 majors to check, e.g. the known ones
     *
     * @return list<int>|null null when the version requires no core package, so nothing says
     */
    public static function supportedMajors(array $require, array $majors): ?array
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
            static fn (string $constraint): bool => !ComposerVersion::admitsMajor($constraint, $major),
        )));
    }
}
