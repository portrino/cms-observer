<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Turns the reported database server version into [product, plain version].
 *
 * Clients report e.g. "10.11.19-MariaDB-ubu2204-log", TYPO3 12 (DBAL 3) prefixes
 * a platform label ("MySQL 10.11.19-MariaDB-…", "PostgreSQL 15.2") and some
 * drivers report MariaDB as "5.5.5-10.11.19-MariaDB". Newer clients also send
 * the platform (an endoflife.date slug); without it the product is guessed.
 */
final class DbVersionNormalizer
{
    /**
     * @return array{0: string, 1: string} [product, plain version], neither empty, both at most 32 chars
     */
    public static function normalize(?string $platform, string $raw): array
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
}
