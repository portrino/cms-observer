<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utility;

use App\Utility\StringUtility;
use PHPUnit\Framework\TestCase;

class StringUtilityTest extends TestCase
{
    public function testTrimsThePartsAndDropsEmptyOnes(): void
    {
        self::assertSame(['portrino', 'acme', 'b13'], StringUtility::trimExplode(',', " portrino ,acme,,  b13  ,\t"));
        self::assertSame(['12', '13'], StringUtility::trimExplode(',', '12, 13'));
        self::assertSame([], StringUtility::trimExplode(',', ' , '));
        self::assertSame([], StringUtility::trimExplode(',', null));
    }
}
