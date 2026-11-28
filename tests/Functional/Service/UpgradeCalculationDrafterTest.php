<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Dto\UpgradeItem;
use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\Typo3\CoreRelease;
use App\Entity\Typo3\PackagistVersion;
use App\Entity\Typo3\ReleaseType;
use App\Entity\Typo3\TerExtensionMeta;
use App\Entity\Typo3\TerExtensionVersion;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeNoteLevel;
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
            'compatible_outdated' => '1.0.0',
            'update_first' => '1.0.0',
            'update' => '1.0.0',
            'blocker' => '1.0.0',
            'no_data' => '1.0.0',
            'dev_branch' => 'dev-main',
            'site_package' => '1.0.0',
        ]);
        $this->terVersions('compatible', ['2.0.0' => '13, 14']);
        $this->terVersions('compatible_outdated', ['1.0.0' => '13, 14', '1.1.0' => '13, 14', '1.2.0' => '14', '2.0.0' => '15'], [
            '1.1.0' => "[!!!][TASK] Drop the\nlegacy plugin",
            '1.2.0' => '[BUGFIX] Fix the list view',
        ]);
        $this->terVersions('update_first', ['1.0.0' => '12, 13', '1.1.0' => '13, 14', '1.2.0' => '13, 14', '2.0.0' => '14']);
        $this->terVersions('update', ['1.0.0' => '13', '2.0.0' => '14', '2.1.0' => '14']);
        $this->terVersions('blocker', ['1.0.0' => '12, 13', '1.1.0' => '13']);
        $this->terVersions('no_data', ['1.0.0' => '13', '2.0.0' => null]);
        $this->terVersions('dev_branch', ['1.0.0' => '13']);
        $this->entityManager->persist(new TerExtensionMeta('compatible', 'https://packagist.org/packages/vendor/compatible', null, null));
        $this->entityManager->flush();

        $items = static::getContainer()->get(UpgradeCalculationDrafter::class)->draft($site, 14);

        $positions = array_filter($items, static fn (UpgradeItem $item): bool => UpgradeSection::Positions === $item->section);
        self::assertSame(
            ['Core upgrade', 'PHP version upgrade', 'Third-party packages', 'Custom packages', 'Testing, code reviews and deployments'],
            array_map(static fn (UpgradeItem $item): string => $item->label, array_values($positions)),
            'the order of the Excel template, with the package sections as positions',
        );
        self::assertSame(UpgradeSection::Packages, array_values($positions)[2]->sumOf);
        self::assertNull(array_values($positions)[0]->maxHours);

        $packages = $labels = [];
        foreach ($items as $item) {
            if (null !== $item->package) {
                $packages[$item->package] = [$item->section, $item->status, $item->targetVersion];
                $labels[$item->package] = $item->label;
            }
        }
        self::assertSame([
            'blocker' => [UpgradeSection::Packages, UpgradeItemStatus::Blocker, null],
            'compatible' => [UpgradeSection::Packages, UpgradeItemStatus::Compatible, '2.0.0'],
            // the newest version for TYPO3 14, not the one for 15
            'compatible_outdated' => [UpgradeSection::Packages, UpgradeItemStatus::Compatible, '1.2.0'],
            'dev_branch' => [UpgradeSection::Packages, UpgradeItemStatus::Manual, null],
            'no_data' => [UpgradeSection::Packages, UpgradeItemStatus::Manual, null],
            'site_package' => [UpgradeSection::OwnPackages, UpgradeItemStatus::Manual, null],
            'update' => [UpgradeSection::Packages, UpgradeItemStatus::Update, '2.0.0'],
            'update_first' => [UpgradeSection::Packages, UpgradeItemStatus::UpdateFirst, '1.1.0'],
        ], $packages);
        self::assertSame('vendor/compatible', $labels['compatible'], 'composer name from TER without one in the report');
        self::assertSame('blocker', $labels['blocker'], 'the key without any composer name');

        $notes = [];
        foreach ($items as $item) {
            if (null !== $item->package) {
                $notes[$item->package] = [$item->noteLevel, $item->notes, $item->minHours, $item->maxHours];
            }
        }
        self::assertSame([UpgradeNoteLevel::Success, 'Latest compatible version already in use', 0.0, 0.0], $notes['compatible']);
        self::assertSame([UpgradeNoteLevel::Danger, 'No TYPO3 14 compatible version yet!', null, null], $notes['blocker']);
        self::assertSame([UpgradeNoteLevel::Success, '- Install/upgrade and test', null, null], $notes['update_first'], 'a minor update, 1.1.0 without an upload comment');
        self::assertSame([UpgradeNoteLevel::Warning, "Attention! Big version jump\n\n- Possibly breaking changes\n- Install/upgrade and test", null, null], $notes['update'], 'a new major, 2.0.0 without an upload comment');
        self::assertSame([
            UpgradeNoteLevel::Warning,
            "- Breaking changes according to the changelog\n  - **1.1.0**: [!!!][TASK] Drop the legacy plugin\n- Install/upgrade and test",
            null,
            null,
        ], $notes['compatible_outdated'], 'only the comments that mention breaking changes, on one line');
        self::assertSame([null, null, null, null], $notes['no_data'], 'nothing to judge by');
    }

    /**
     * b13/http2: the TER stopped at 3.0.0 for TYPO3 12 and 13, Packagist has 4.x for TYPO3 14.
     */
    public function testPackagistVersionsTheTerLacksAreUpdatesToo(): void
    {
        $site = $this->site(CmsType::Typo3, '13.4.20', []);
        $this->entityManager->persist(new SitePackage($site, 'http2', 'HTTP/2', '3.0.0', true, 'b13/http2'));
        $this->entityManager->persist(new SitePackage($site, 'packagist_only', 'Packagist only', '1.0.0', true, 'vendor/packagist-only'));
        $this->terVersions('http2', ['3.0.0' => '12, 13']);
        foreach ([['b13/http2', '3.0.0', [12, 13]], ['b13/http2', '4.0.0', [14]], ['b13/http2', '4.1.0', [14]], ['vendor/packagist-only', '1.0.0', [13, 14]]] as [$name, $version, $majors]) {
            $this->entityManager->persist($packagistVersion = new PackagistVersion($name, $version, null, $majors));
            if ('4.0.0' === $version) {
                // from GitHub (ADR-0007)
                $packagistVersion->recordBreakingCommits([['sha' => 'aaa', 'subject' => '!!![TASK] change requirements', 'url' => 'https://github.com/b13/http2/commit/aaa']]);
            }
        }
        // only on Packagist, their commits checked: nothing breaking, also across a major
        foreach (['minor_update' => ['1.0.0', '1.0.1'], 'major_checked' => ['1.0.0', '2.0.0']] as $key => $packageVersions) {
            $this->entityManager->persist(new SitePackage($site, $key, $key, $packageVersions[0], true, 'vendor/'.$key));
            foreach ($packageVersions as $version) {
                $this->entityManager->persist($packagistVersion = new PackagistVersion('vendor/'.$key, $version, null, [13, 14]));
                $packagistVersion->recordBreakingCommits([]);
            }
        }
        $this->entityManager->flush();

        $items = $notes = [];
        foreach (static::getContainer()->get(UpgradeCalculationDrafter::class)->draft($site, 14) as $item) {
            if (null !== $item->package) {
                $items[$item->package] = [$item->section, $item->status, $item->targetVersion, $item->noteLevel];
                $notes[$item->package] = $item->notes;
            }
        }

        self::assertSame([UpgradeSection::Packages, UpgradeItemStatus::Update, '4.0.0', UpgradeNoteLevel::Warning], $items['http2'], 'with the core, a new major of the package');
        self::assertSame("Attention! Big version jump\n\n- Breaking changes according to the changelog\n  - **4.0.0**: [!!!\\[TASK\\] change requirements](https://github.com/b13/http2/commit/aaa)\n- Install/upgrade and test", $notes['http2'] ?? null, 'the breaking commit from GitHub, linked');
        self::assertSame([UpgradeSection::Packages, UpgradeItemStatus::Compatible, '1.0.0', UpgradeNoteLevel::Success], $items['packagist_only'], 'a third-party package, though not in the TER');
        self::assertSame("According to the changelog no special upgrade steps needed\n\n- Install/upgrade and test", $notes['minor_update'] ?? null, 'every version crossed checked on GitHub');
        self::assertSame([UpgradeSection::Packages, UpgradeItemStatus::Compatible, '2.0.0', UpgradeNoteLevel::Success], $items['major_checked'], 'a new major without breaking changes, as every version tells');
        self::assertSame("According to the changelog no special upgrade steps needed\n\n- Install/upgrade and test", $notes['major_checked'] ?? null);
    }

    public function testTargetsAreTheKnownMajorsAboveTheCurrentOne(): void
    {
        $site = $this->site(CmsType::Typo3, '12.4.30', []);
        foreach (['11.5.40', '12.4.30', '13.4.20', '13.4.21', '14.0.0'] as $version) {
            $this->entityManager->persist(new CoreRelease(
                version: $version,
                releaseDate: new \DateTimeImmutable(),
                type: ReleaseType::Regular,
                latestVersion: $version,
                isActive: true,
                insecure: false,
                maintainedUntil: null,
            ));
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
     * @param array<string, string>  $comments version => upload comment
     */
    private function terVersions(string $key, array $versions, array $comments = []): void
    {
        foreach ($versions as $version => $majors) {
            $this->entityManager->persist(new TerExtensionVersion(
                extensionKey: $key,
                version: $version,
                title: $key,
                insecure: false,
                description: null,
                uploadComment: $comments[$version] ?? null,
                state: 'stable',
                typo3Versions: $majors,
                uploadDate: null,
            ));
        }
    }
}
