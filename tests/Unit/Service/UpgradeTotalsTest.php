<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\UpgradeItem;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeSection;
use App\Service\UpgradeTotals;
use PHPUnit\Framework\TestCase;

class UpgradeTotalsTest extends TestCase
{
    public function testSumsRequiredAndOptionalWorkWithManagementRoundedUp(): void
    {
        // the positions of the agency's Excel template: 34.75 h required, 9 h optional at 10 %
        $totals = new UpgradeTotals([
            new UpgradeItem(UpgradeSection::Positions, 'Core upgrade', minHours: 4, maxHours: 4),
            new UpgradeItem(UpgradeSection::Positions, 'PHP', minHours: 2, maxHours: 2),
            new UpgradeItem(UpgradeSection::Positions, 'Without hours'),
            new UpgradeItem(UpgradeSection::Packages, 'solr', minHours: 4, maxHours: 8),
            new UpgradeItem(UpgradeSection::Packages, 'powermail', status: UpgradeItemStatus::UpdateFirst, minHours: 2, maxHours: 3.25),
            new UpgradeItem(UpgradeSection::OwnPackages, 'site_package', minHours: 7.5, maxHours: 11.5),
            new UpgradeItem(UpgradeSection::Positions, 'Testing', minHours: 2, maxHours: 2),
            new UpgradeItem(UpgradeSection::Optional, 'Content blocks', minHours: 8, maxHours: 8),
        ], 10);

        self::assertSame(['min' => 6.0, 'max' => 11.25], $totals->sections['packages']);
        self::assertSame(['min' => 3.0, 'max' => 4.0], $totals->requiredManagement);
        self::assertSame(['min' => 24.5, 'max' => 34.75], $totals->required);
        self::assertSame(['min' => 1.0, 'max' => 1.0], $totals->optionalManagement);
        self::assertSame(['min' => 9.0, 'max' => 9.0], $totals->optional);
        self::assertSame(['min' => 2.0, 'max' => 3.25], $totals->beforehand, 'counted in the required work, shown separately');
    }

    public function testManagementIgnoresFloatNoiseWhenRoundingUp(): void
    {
        // as floats 100 * (7 / 100) is 7.000000000000001, which a plain ceil() turns into 8
        $totals = new UpgradeTotals([
            new UpgradeItem(UpgradeSection::Positions, 'a', minHours: 0.1, maxHours: 100),
            new UpgradeItem(UpgradeSection::Positions, 'b', minHours: 0.2),
            new UpgradeItem(UpgradeSection::Positions, 'c', minHours: 0.7),
        ], 7);

        self::assertSame(7.0, $totals->requiredManagement['max']);
        self::assertSame(1.0, $totals->requiredManagement['min']);
    }

    public function testNoHoursMeansNoManagement(): void
    {
        $totals = new UpgradeTotals([], 10);

        self::assertSame(['min' => 0.0, 'max' => 0.0], $totals->required);
        self::assertSame(['min' => 0.0, 'max' => 0.0], $totals->optional);
    }
}
