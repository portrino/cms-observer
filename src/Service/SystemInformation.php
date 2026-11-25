<?php

declare(strict_types=1);

namespace App\Service;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;

/**
 * What the brand dropdown shows, like TYPO3's system information toolbar item.
 */
class SystemInformation
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
    }

    /**
     * @return array<string, string> value by translation key suffix
     */
    public function collect(Request $request): array
    {
        return [
            'observer' => $this->observerVersion(),
            'symfony' => Kernel::VERSION,
            'environment' => $this->environment,
            'php' => PHP_VERSION,
            'database' => $this->databaseVersion(),
            'webserver' => $request->server->getString('SERVER_SOFTWARE', '-'),
            'os' => PHP_OS_FAMILY.' '.php_uname('r'),
        ];
    }

    private function observerVersion(): string
    {
        $root = InstalledVersions::getRootPackage();

        // a branch checkout (dev-main) says little without its commit; shortcut: the commit
        // is the one of the last composer install (bin/setup runs it), read git if that misleads
        return str_starts_with($root['pretty_version'], 'dev-') && null !== $root['reference']
            ? $root['pretty_version'].' ('.substr($root['reference'], 0, 7).')'
            : $root['pretty_version'];
    }

    private function databaseVersion(): string
    {
        $platform = $this->connection->getDatabasePlatform();
        $name = match (true) {
            $platform instanceof MariaDBPlatform => 'MariaDB',
            $platform instanceof AbstractMySQLPlatform => 'MySQL',
            $platform instanceof PostgreSQLPlatform => 'PostgreSQL',
            $platform instanceof SQLitePlatform => 'SQLite',
            default => new \ReflectionClass($platform)->getShortName(),
        };

        // e.g. "11.4.2-MariaDB-ubu2404" or "16.2 (Debian 16.2-1)"
        preg_match('/^\d+(\.\d+)*/', $this->connection->getServerVersion(), $match);

        return trim($name.' '.($match[0] ?? ''));
    }
}
