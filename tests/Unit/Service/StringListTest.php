<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\StringList;
use PHPUnit\Framework\TestCase;

class StringListTest extends TestCase
{
    public function testTrimsThePartsAndDropsEmptyOnes(): void
    {
        self::assertSame(['portrino', 'acme', 'b13'], StringList::trimExplode(',', " portrino ,acme,,  b13  ,\t"));
        self::assertSame(['12', '13'], StringList::trimExplode(',', '12, 13'));
        self::assertSame([], StringList::trimExplode(',', ' , '));
        self::assertSame([], StringList::trimExplode(',', null));
    }
}
