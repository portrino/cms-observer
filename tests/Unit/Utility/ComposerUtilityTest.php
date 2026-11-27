<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utility;

use App\Utility\ComposerUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ComposerUtilityTest extends TestCase
{
    public function testRatesOnlyReleases(): void
    {
        self::assertTrue(ComposerUtility::canRate('5.10.2'));
        self::assertFalse(ComposerUtility::canRate('dev-main'));
        self::assertFalse(ComposerUtility::canRate('not a version'));
    }

    public function testAMalformedConstraintIsUnknown(): void
    {
        self::assertTrue(ComposerUtility::satisfies('5.8.0', '^5.0.0'));
        self::assertFalse(ComposerUtility::satisfies('5.8.0', '^4.0.0'));
        self::assertNull(ComposerUtility::satisfies('5.8.0', '>=5.0 <<5.4'));
    }

    /**
     * @return iterable<string, array{string, int, bool}>
     */
    public static function majors(): iterable
    {
        yield 'caret of the major' => ['^14.3', 14, true];
        yield 'one of several' => ['^12.4 || ^13.4', 13, true];
        yield 'not listed' => ['^12.4 || ^13.4', 14, false];
        yield 'open range' => ['>=12.4', 15, true];
        yield 'below the range' => ['>=12.4', 11, false];
        yield 'upper bound before the major' => ['>=12.4 <14.0', 14, false];
        yield 'dev versions of the major' => ['14.0.x-dev', 14, true];
        yield 'malformed' => ['>=5.0 <<5.4', 5, false];
    }

    #[DataProvider('majors')]
    public function testAdmitsAMajorWhenAnyOfItsReleasesFits(string $constraint, int $major, bool $admits): void
    {
        self::assertSame($admits, ComposerUtility::admitsMajor($constraint, $major));
    }
}
