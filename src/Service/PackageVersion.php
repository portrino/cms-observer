<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Some packages tag their versions "v7.1.1". The Observer stores and shows them without the
 * "v": version_compare() sorts "v7.1.1" before every plain version, so the package would
 * always look outdated against the same version from the TER or wordpress.org.
 */
final class PackageVersion
{
    public static function normalize(string $version): string
    {
        return (string) preg_replace('/^v(?=\d)/i', '', trim($version));
    }
}
