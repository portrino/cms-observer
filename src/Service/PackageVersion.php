<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Some packages tag their versions "v7.1.1". The Observer stores and shows them without the
 * "v": version_compare() sorts "v7.1.1" before every plain version, so the package would
 * always look outdated against the same version from the TER or wordpress.org.
 *
 * Only a branch like "dev-feature/..." gets longer than the version column; it can't be rated
 * anyway, so cutting it off loses nothing and the report isn't rejected by the database.
 */
final class PackageVersion
{
    private const int MAX_LENGTH = 64;

    public static function normalize(string $version): string
    {
        return mb_substr((string) preg_replace('/^v(?=\d)/i', '', trim($version)), 0, self::MAX_LENGTH);
    }
}
