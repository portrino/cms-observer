<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\UpgradeItem;
use App\Entity\PackageType;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeNoteLevel;
use App\Entity\UpgradeSection;
use PHPUnit\Framework\TestCase;

class UpgradeItemTest extends TestCase
{
    public function testRoundTripsThroughTheStoredArray(): void
    {
        $item = new UpgradeItem(UpgradeSection::Packages, 'News', 'news', PackageType::Typo3CmsExtension, '12.3.1', '13.0.0', UpgradeItemStatus::UpdateFirst, 0.25, 0.5, 'See changelog', 'RB', UpgradeNoteLevel::Warning);

        self::assertEquals($item, UpgradeItem::fromArray($item->toArray()));
    }

    public function testEveryStatusHasALabelAndColor(): void
    {
        foreach (UpgradeItemStatus::cases() as $status) {
            self::assertSame($status, UpgradeItem::fromArray(['package' => 'news', 'status' => $status->value])->status);
            self::assertNotSame('', $status->label().$status->color());
        }
    }

    public function testUnknownValuesOfOlderOrNewerVersionsFallBack(): void
    {
        $item = UpgradeItem::fromArray(['section' => 'future-section', 'package' => 'news', 'status' => 'future-status', 'minHours' => 'n/a']);

        self::assertSame(UpgradeSection::Positions, $item->section);
        self::assertSame('news', $item->label);
        self::assertSame(UpgradeItemStatus::Manual, $item->status);
        self::assertNull($item->minHours);
    }

    public function testEveryPackageSectionKeepsItsSumAmongThePositions(): void
    {
        $items = UpgradeItem::withSectionSums([
            new UpgradeItem(UpgradeSection::Positions, 'Core upgrade'),
            UpgradeItem::sumOf(UpgradeSection::OwnPackages),
            new UpgradeItem(UpgradeSection::Packages, 'news', 'news'),
        ]);

        self::assertSame([null, UpgradeSection::OwnPackages, UpgradeSection::Packages, null], array_map(static fn (UpgradeItem $item): ?UpgradeSection => $item->sumOf, $items), 'a missing sum is added after the positions');
    }

    public function testASumTakesNoHoursOrLabelFromTheForm(): void
    {
        $item = UpgradeItem::fromArray(['section' => 'positions', 'sumOf' => 'packages', 'label' => 'Changed', 'minHours' => 3, 'maxHours' => 5, 'notes' => 'Kept']);

        self::assertSame(['Third-party packages', null, null, 'Kept'], [$item->label, $item->minHours, $item->maxHours, $item->notes]);
    }
}
