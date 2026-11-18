<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\PackageVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PackageVersionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function versions(): iterable
    {
        yield 'v prefix' => ['v7.1.1', '7.1.1'];
        yield 'capital V' => ['V2.0.0', '2.0.0'];
        yield 'plain' => ['12.3.1', '12.3.1'];
        yield 'whitespace' => [' 1.0.0 ', '1.0.0'];
        // not a version prefix, e.g. a branch name
        yield 'dev branch' => ['dev-main', 'dev-main'];
    }

    #[DataProvider('versions')]
    public function testStripsTheVPrefix(string $reported, string $expected): void
    {
        self::assertSame($expected, PackageVersion::normalize($reported));
    }
}
