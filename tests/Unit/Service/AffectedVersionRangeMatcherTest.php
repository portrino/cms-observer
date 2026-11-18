<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\AffectedVersionRangeMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AffectedVersionRangeMatcherTest extends TestCase
{
    #[DataProvider('matchCases')]
    public function testMatches(string $affectedVersionsRaw, string $version, ?bool $expected): void
    {
        self::assertSame($expected, AffectedVersionRangeMatcher::matches($affectedVersionsRaw, $version));
    }

    /**
     * @return iterable<string, array{string, string, ?bool}>
     */
    public static function matchCases(): iterable
    {
        yield 'exact match' => ['12.4.9', '12.4.9', true];
        yield 'exact non-match' => ['12.4.9', '12.4.10', false];

        yield '"and below" - at the bound' => ['10.9.2 and below', '10.9.2', true];
        yield '"and below" - under the bound' => ['10.9.2 and below', '10.9.1', true];
        yield '"and below" - over the bound' => ['10.9.2 and below', '10.9.3', false];

        yield '"and above" - at the bound' => ['11.0.0 and above', '11.0.0', true];
        yield '"and above" - over the bound' => ['11.0.0 and above', '12.0.0', true];
        yield '"and above" - under the bound' => ['11.0.0 and above', '10.9.9', false];

        yield 'inclusive range - lower bound' => ['11.0.0 - 12.6.0', '11.0.0', true];
        yield 'inclusive range - upper bound' => ['11.0.0 - 12.6.0', '12.6.0', true];
        yield 'inclusive range - inside' => ['11.0.0 - 12.6.0', '11.5.0', true];
        yield 'inclusive range - below' => ['11.0.0 - 12.6.0', '10.9.9', false];
        yield 'inclusive range - above' => ['11.0.0 - 12.6.0', '12.6.1', false];

        yield 'multiple segments - matches the second' => ['10.9.2 and below, 13.0.0 - 13.2.0', '13.1.0', true];
        yield 'multiple segments - matches neither' => ['10.9.2 and below, 13.0.0 - 13.2.0', '11.5.0', false];

        yield 'unrecognized segment alone is undetermined' => ['some free text', '1.0.0', null];
        yield 'unrecognized segment mixed with a non-matching one is undetermined' => ['10.9.2 and below, some free text', '11.0.0', null];
        yield 'unrecognized segment mixed with a matching one still matches' => ['some free text, 10.9.2 and below', '10.9.2', true];

        yield 'empty string is undetermined' => ['', '1.0.0', null];
        yield 'blank/whitespace-only segments are ignored, not treated as unrecognized' => [' , 10.9.2 and below , ', '10.9.2', true];
    }
}
