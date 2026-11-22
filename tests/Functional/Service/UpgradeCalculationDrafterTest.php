<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Dto\UpgradeItem;
use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\Typo3CoreRelease;
use App\Entity\Typo3ReleaseType;
use App\Entity\Typo3TerExtensionVersion;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeSection;
use App\Service\UpgradeCalculationDrafter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UpgradeCalculationDrafterTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testJudgesTypo3ExtensionsByTheirTerVersions(): void
    {
        $site = $this->site(CmsType::Typo3, '13.4.20', [
            'compatible' => '2.0.0',
            'update_first' => '1.0.0',
            'update' => '1.0.0',
            'blocker' => '1.0.0',
            'no_data' => '1.0.0',
            'dev_branch' => 'dev-main',
            'site_package' => '1.0.0',
        ]);
        $this->terVersions('compatible', ['2.0.0' => '13, 14']);
        $this->terVersions('update_first', ['1.0.0' => '12, 13', '1.1.0' => '13, 14', '1.2.0' => '13, 14', '2.0.0' => '14']);
        $this->terVersions('update', ['1.0.0' => '13', '2.0.0' => '14', '2.1.0' => '14']);
        $this->terVersions('blocker', ['1.0.0' => '12, 13', '1.1.0' => '13']);
        $this->terVersions('no_data', ['1.0.0' => '13', '2.0.0' => null]);
        $this->terVersions('dev_branch', ['1.0.0' => '13']);
        $this->entityManager->flush();

        $items = static::getContainer()->get(UpgradeCalculationDrafter::class)->draft($site, 14);

        $positions = array_filter($items, static fn (UpgradeItem $item): bool => UpgradeSection::Positions === $item->section);
        self::assertCount(3, $positions);
        self::assertNull(array_values($positions)[0]->maxHours);

        $packages = [];
        foreach ($items as $item) {
            if (null !== $item->package) {
                $packages[$item->package] = [$item->section, $item->status, $item->targetVersion];
            }
        }
        self::assertSame([
            'blocker' => [UpgradeSection::Packages, UpgradeItemStatus::Blocker, null],
            'compatible' => [UpgradeSection::Packages, UpgradeItemStatus::Compatible, '2.0.0'],
            'dev_branch' => [UpgradeSection::Packages, UpgradeItemStatus::Manual, null],
            'no_data' => [UpgradeSection::Packages, UpgradeItemStatus::Manual, null],
            'site_package' => [UpgradeSection::OwnPackages, UpgradeItemStatus::Manual, null],
            'update' => [UpgradeSection::Packages, UpgradeItemStatus::Update, '2.0.0'],
            'update_first' => [UpgradeSection::Packages, UpgradeItemStatus::UpdateFirst, '1.1.0'],
        ], $packages);
    }

    public function testTargetsAreTheKnownMajorsAboveTheCurrentOne(): void
    {
        $site = $this->site(CmsType::Typo3, '12.4.30', []);
        foreach (['11.5.40', '12.4.30', '13.4.20', '13.4.21', '14.0.0'] as $version) {
            $this->entityManager->persist(new Typo3CoreRelease($version, new \DateTimeImmutable(), Typo3ReleaseType::Regular, $version, true, null, false));
        }
        $this->entityManager->flush();

        $drafter = static::getContainer()->get(UpgradeCalculationDrafter::class);

        self::assertSame([13, 14], $drafter->targetMajors($site));
        self::assertSame([], $drafter->targetMajors(new Site('Never reported', CmsType::Typo3, 'https://new.test', 'hash-new')));
    }

    public function testWordpressPackagesAreCheckedManually(): void
    {
        $site = $this->site(CmsType::WordPress, '6.7.1', ['akismet' => '5.3']);
        $this->entityManager->flush();

        $items = static::getContainer()->get(UpgradeCalculationDrafter::class)->draft($site, 7);
        $akismet = array_values(array_filter($items, static fn (UpgradeItem $item): bool => 'akismet' === $item->package))[0];

        self::assertSame(UpgradeSection::Packages, $akismet->section);
        self::assertSame(UpgradeItemStatus::Manual, $akismet->status);
        self::assertSame('5.3', $akismet->installedVersion);
    }

    /**
     * @param array<string, string> $packages name => installed version
     */
    private function site(CmsType $type, string $coreVersion, array $packages): Site
    {
        $site = new Site('Upgrade '.$type->value, $type, 'https://upgrade-'.$type->value.'.test', 'hash-'.$type->value.'-'.$coreVersion);
        $site->recordReport($coreVersion, '8.3.14', '10.11.19-MariaDB');
        $this->entityManager->persist($site);
        foreach ($packages as $name => $version) {
            $this->entityManager->persist(new SitePackage($site, $name, ucfirst($name), $version, true));
        }

        return $site;
    }

    /**
     * @param array<string, ?string> $versions version => TYPO3 majors
     */
    private function terVersions(string $key, array $versions): void
    {
        foreach ($versions as $version => $majors) {
            $this->entityManager->persist(new Typo3TerExtensionVersion($key, $version, $key, null, null, 'stable', $majors, null, false));
        }
    }
}
