<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup;

use App\Setup\DatabaseServerProbe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatabaseServerProbeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function serverVersions(): iterable
    {
        yield 'MySQL on Ubuntu' => ['8.0.46-0ubuntu0.24.04.5', '8.0.46'];
        yield 'MariaDB with distribution suffix' => ['10.11.19-MariaDB-ubu2204-log', '10.11.19-MariaDB'];
        yield 'MariaDB with replication prefix' => ['5.5.5-10.11.6-MariaDB', '10.11.6-MariaDB'];
    }

    #[DataProvider('serverVersions')]
    public function testFormatsTheServerVersionForDoctrine(string $raw, string $expected): void
    {
        self::assertSame($expected, DatabaseServerProbe::serverVersionFor($raw));
    }

    public function testSetsTheServerVersionInTheDatabaseUrl(): void
    {
        self::assertSame(
            'mysql://u:p@db:3306/observer?serverVersion=8.0.46&charset=utf8mb4',
            DatabaseServerProbe::withServerVersion('mysql://u:p@db:3306/observer', '8.0.46'),
        );
        // a wrong version from the user is replaced, other parameters are kept
        self::assertSame(
            'mysql://u:p@db:3306/observer?serverVersion=11.8.0-MariaDB&charset=latin1',
            DatabaseServerProbe::withServerVersion('mysql://u:p@db:3306/observer?serverVersion=8.0.32&charset=latin1', '11.8.0-MariaDB'),
        );
    }
}
