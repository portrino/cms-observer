<?php

declare(strict_types=1);

namespace App\Setup;

use App\Utility\VersionUtility;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

/**
 * Connects with a DATABASE_URL and reports the server's version in Doctrine's "serverVersion"
 * format. A wrong serverVersion makes Doctrine send SQL for the wrong platform, which fails
 * only once the migrations run.
 */
class DatabaseServerProbe
{
    /**
     * @throws \Throwable when the server can't be reached with the URL
     */
    public function detectServerVersion(string $databaseUrl): string
    {
        $params = new DsnParser(['mysql' => 'pdo_mysql', 'mariadb' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql'])->parse($databaseUrl);
        // the database itself may not exist yet, app:upgrade creates it
        unset($params['dbname'], $params['serverVersion']);

        $connection = DriverManager::getConnection($params);
        try {
            return self::serverVersionFor((string) $connection->fetchOne('SELECT VERSION()'));
        } finally {
            $connection->close();
        }
    }

    /**
     * "10.11.19-MariaDB-ubu2204-log" => "10.11.19-MariaDB", "8.0.46-0ubuntu0.24.04.5" => "8.0.46".
     */
    public static function serverVersionFor(string $rawVersion): string
    {
        [$product, $version] = VersionUtility::normalizeDbVersion(null, $rawVersion);

        return 'mariadb' === $product ? $version.'-MariaDB' : $version;
    }

    /**
     * Sets (or replaces) the serverVersion query parameter of a DATABASE_URL.
     */
    public static function withServerVersion(string $databaseUrl, string $serverVersion): string
    {
        $parts = explode('?', $databaseUrl, 2);
        parse_str($parts[1] ?? '', $query);
        $query['serverVersion'] = $serverVersion;
        $query += ['charset' => 'utf8mb4'];

        return $parts[0].'?'.http_build_query($query);
    }
}
