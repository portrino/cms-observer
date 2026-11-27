<?php

declare(strict_types=1);

namespace App\Utility;

/**
 * Plain version numbers as the clients, the TER, wordpress.org and the databases report them;
 * Composer's rules are in ComposerUtility.
 */
final class VersionUtility
{
    private const int MAX_PACKAGE_VERSION_LENGTH = 64;

    private function __construct()
    {
    }

    /**
     * Some packages tag their versions "v7.1.1". The Observer stores and shows them without the
     * "v": version_compare() sorts "v7.1.1" before every plain version, so the package would
     * always look outdated against the same version from the TER or wordpress.org.
     *
     * Only a branch like "dev-feature/..." gets longer than the version column; it can't be rated
     * anyway, so cutting it off loses nothing and the report isn't rejected by the database.
     */
    public static function normalizePackageVersion(string $version): string
    {
        return mb_substr((string) preg_replace('/^v(?=\d)/i', '', trim($version)), 0, self::MAX_PACKAGE_VERSION_LENGTH);
    }

    /**
     * Turns the reported database server version into [product, plain version].
     *
     * Clients report e.g. "10.11.19-MariaDB-ubu2204-log", TYPO3 12 (DBAL 3) prefixes
     * a platform label ("MySQL 10.11.19-MariaDB-…", "PostgreSQL 15.2") and some
     * drivers report MariaDB as "5.5.5-10.11.19-MariaDB". Newer clients also send
     * the platform (an endoflife.date slug); without it the product is guessed.
     *
     * @return array{0: string, 1: string} [product, plain version], neither empty, both at most 32 chars
     */
    public static function normalizeDbVersion(?string $platform, string $raw): array
    {
        $raw = trim($raw);
        preg_match('/^(?:(MySQL|PostgreSQL)\s+)?(?:5\.5\.5-)?(\d+(?:\.\d+)*)/i', $raw, $matches);

        $product = strtolower(trim($platform ?? ''));
        if ('' === $product) {
            $product = match (true) {
                false !== stripos($raw, 'mariadb') => 'mariadb',
                0 === strcasecmp($matches[1] ?? '', 'PostgreSQL') => 'postgresql',
                default => 'mysql',
            };
        }

        return [mb_substr($product, 0, 32), mb_substr($matches[2] ?? $raw, 0, 32)];
    }

    /**
     * @param list<int> $knownMajors
     *
     * @return list<int> the known majors above the one of $currentVersion, ascending; none without a version
     */
    public static function majorsAbove(?string $currentVersion, array $knownMajors): array
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
