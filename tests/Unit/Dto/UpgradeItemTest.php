<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\UpgradeItem;
use App\Entity\PackageType;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeSection;
use PHPUnit\Framework\TestCase;

class UpgradeItemTest extends TestCase
{
    public function testRoundTripsThroughTheStoredArray(): void
    {
        $item = new UpgradeItem(UpgradeSection::Packages, 'News', 'news', PackageType::Typo3CmsExtension, '12.3.1', '13.0.0', UpgradeItemStatus::UpdateFirst, 'Update', 0.25, 0.5, 'See changelog', 'RB');

        self::assertEquals($item, UpgradeItem::fromArray($item->toArray()));
    }

    public function testUnknownValuesOfOlderOrNewerVersionsFallBack(): void
    {
        $item = UpgradeItem::fromArray(['section' => 'future-section', 'package' => 'news', 'status' => 'future-status', 'minHours' => 'n/a']);

        self::assertSame(UpgradeSection::Positions, $item->section);
        self::assertSame('news', $item->label);
        self::assertSame(UpgradeItemStatus::Manual, $item->status);
        self::assertNull($item->minHours);
    }
}
