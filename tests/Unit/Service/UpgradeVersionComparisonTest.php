<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\UpgradeItem;
use App\Entity\CmsType;
use App\Entity\PackageType;
use App\Entity\Site;
use App\Entity\UpgradeCalculation;
use App\Entity\UpgradeCalculationVersion;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeSection;
use App\Service\UpgradeVersionComparison;
use PHPUnit\Framework\TestCase;

class UpgradeVersionComparisonTest extends TestCase
{
    public function testListsWhatChangedBetweenTwoVersions(): void
    {
        $calculation = new UpgradeCalculation(new Site('Project', CmsType::Typo3, 'https://example.test', 'hash'), 13, 14);
        $news = static fn (UpgradeItemStatus $status, float $hours): UpgradeItem => new UpgradeItem(UpgradeSection::Packages, 'georgringer/news', 'news', PackageType::Typo3CmsExtension, '12.3.1', '13.0.0', $status, maxHours: $hours);

        $old = new UpgradeCalculationVersion(calculation: $calculation, author: 'JD', hourlyRate: 100, managementPercent: 10, final: false, note: null, remarks: null, items: [
            new UpgradeItem(UpgradeSection::Positions, 'Core upgrade', maxHours: 4),
            $news(UpgradeItemStatus::Blocker, 2),
            new UpgradeItem(UpgradeSection::Packages, 'gone', 'gone', PackageType::Typo3CmsExtension),
        ]);
        $new = new UpgradeCalculationVersion(calculation: $calculation, author: 'PM', hourlyRate: 90, managementPercent: 10, final: true, note: null, remarks: null, items: [
            new UpgradeItem(UpgradeSection::Positions, 'Core upgrade', maxHours: 4),
            $news(UpgradeItemStatus::Update, 3),
            new UpgradeItem(UpgradeSection::Optional, 'Content blocks', maxHours: 8),
        ]);

        $comparison = new UpgradeVersionComparison($old, $new);

        self::assertSame([
            ['field' => 'calculation.field.rate', 'old' => '100.00', 'new' => '90.00'],
            ['field' => 'calculation.final_version', 'old' => 'common.no', 'new' => 'common.yes'],
        ], $comparison->settings);
        self::assertSame([], $comparison->sections['positions']['changed'], 'unchanged rows, also the package sums with unchanged hours');
        $packages = $comparison->sections['packages'];
        self::assertSame([
            ['field' => 'calculation.todo', 'old' => 'calculation.status.blocker', 'new' => 'calculation.status.update'],
            ['field' => 'calculation.max_h', 'old' => '2.00', 'new' => '3.00'],
        ], $packages['changed'][0]['fields']);
        self::assertSame('gone', $packages['removed'][0]->label);
        self::assertSame('Content blocks', $comparison->sections['optional']['added'][0]->label);
        self::assertContains(['field' => 'calculation.total.price', 'old' => '700.00', 'new' => '720.00'], $comparison->totals);
        self::assertFalse($comparison->isEmpty());
        self::assertTrue(new UpgradeVersionComparison($old, $old)->isEmpty());
    }
}
