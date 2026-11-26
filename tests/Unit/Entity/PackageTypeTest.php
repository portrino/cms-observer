<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\CmsType;
use App\Entity\PackageType;
use PHPUnit\Framework\TestCase;

class PackageTypeTest extends TestCase
{
    public function testMapsTheReportedTypePerCms(): void
    {
        self::assertSame(PackageType::Typo3CmsExtension, PackageType::fromReport(CmsType::Typo3, 'extension'));
        self::assertSame(PackageType::Typo3CmsExtension, PackageType::fromReport(CmsType::Typo3, null));
        self::assertSame(PackageType::WordpressPlugin, PackageType::fromReport(CmsType::WordPress, 'plugin'));
        self::assertSame(PackageType::WordpressPlugin, PackageType::fromReport(CmsType::WordPress, null));
        self::assertSame(PackageType::WordpressTheme, PackageType::fromReport(CmsType::WordPress, 'theme'));
        self::assertSame(PackageType::CraftPlugin, PackageType::fromReport(CmsType::Craft, 'plugin'));
        self::assertSame(PackageType::CraftPlugin, PackageType::fromReport(CmsType::Craft, null));
        self::assertNull(PackageType::fromReport(CmsType::Typo3, 'theme'));
        self::assertNull(PackageType::fromReport(CmsType::Craft, 'theme'));
        self::assertNull(PackageType::fromReport(CmsType::WordPress, 'block-pattern'));
    }

    public function testListsTheTypesOfACms(): void
    {
        self::assertSame([PackageType::Typo3CmsExtension], PackageType::allOf(CmsType::Typo3));
        self::assertSame([PackageType::WordpressPlugin, PackageType::WordpressTheme], PackageType::allOf(CmsType::WordPress));
        self::assertSame([PackageType::CraftPlugin], PackageType::allOf(CmsType::Craft));
    }
}
