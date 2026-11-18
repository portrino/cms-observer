<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\EndOfLifeRelease;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EndOfLifeReleaseTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, ?bool, string}>
     */
    public static function supportStates(): iterable
    {
        // isEol, extendedSupportEnded, expected status (TYPO3 examples as of 2026-11)
        yield 'regular support (TYPO3 13)' => [false, false, 'supported'];
        yield 'ELTS only (TYPO3 12)' => [true, false, 'extended'];
        yield 'ELTS ended too (TYPO3 10)' => [true, true, 'unsupported'];
        yield 'EOL, product without extended support' => [true, null, 'unsupported'];
    }

    #[DataProvider('supportStates')]
    public function testSupportStatus(bool $isEol, ?bool $extendedSupportEnded, string $expected): void
    {
        $release = new EndOfLifeRelease('typo3', '12', $isEol, '12.4.40', 'https://endoflife.date/typo3');
        $release->setExtendedSupportEnded($extendedSupportEnded);

        self::assertSame($expected, $release->getSupportStatus());
    }
}
