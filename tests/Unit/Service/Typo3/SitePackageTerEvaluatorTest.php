<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Typo3;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\Typo3TerExtensionVersion;
use App\Repository\Typo3TerExtensionVersionRepository;
use App\Repository\WordpressPluginInfoRepository;
use App\Repository\WordpressPluginVersionRepository;
use App\Repository\WordpressThemeInfoRepository;
use App\Service\PackageUpdateResolver;
use App\Service\SecurityNotifier;
use App\Service\Typo3\SitePackageTerEvaluator;
use PHPUnit\Framework\TestCase;

class SitePackageTerEvaluatorTest extends TestCase
{
    public function testAnInstalledVersionNewerThanAllTerVersionsIsNotOutdated(): void
    {
        $package = $this->evaluate('9.0.1', [['9.0.0', '13']]);

        self::assertFalse($package->isOutdated());
        self::assertNull($package->getLatestVersion());
    }

    public function testANewerVersionForTheSitesCoreMakesItOutdated(): void
    {
        $package = $this->evaluate('8.3.1', [['8.3.1', '13'], ['9.0.0', '12,13']]);

        self::assertTrue($package->isOutdated());
        self::assertSame('9.0.0', $package->getLatestVersion());
        self::assertNull($package->getNewerCoreVersion());
    }

    /**
     * The bug: the newest version for TYPO3 13 is installed, newer ones need TYPO3 14.
     */
    public function testNewerVersionsForANewerCoreOnlyAreNotOutdated(): void
    {
        $package = $this->evaluate('12.4.0', [['12.4.0', '12,13'], ['13.0.0', '14'], ['13.1.0', '14']]);

        self::assertFalse($package->isOutdated());
        self::assertNull($package->getLatestVersion());
        self::assertSame('14', $package->getNewerCoreVersion());
        self::assertSame('13.1.0', $package->getNewerCorePackageVersion());
    }

    /**
     * Ready for the next major without an update: the hint shows the installed version,
     * so the list tells at a glance which packages support it.
     */
    public function testAnInstalledVersionSupportingTheNextMajorShowsIt(): void
    {
        $package = $this->evaluate('7.1.1', [['7.1.1', '13,14']]);

        self::assertFalse($package->isOutdated());
        self::assertSame('14', $package->getNewerCoreVersion());
        self::assertSame('7.1.1', $package->getNewerCorePackageVersion());
    }

    public function testAnUpdateSupportingBothMajorsIsOutdatedAndShowsTheNextMajor(): void
    {
        $package = $this->evaluate('11.4.0', [['11.4.0', '12,13'], ['14.1.2', '13,14']]);

        self::assertTrue($package->isOutdated());
        self::assertSame('14.1.2', $package->getLatestVersion());
        self::assertSame('14', $package->getNewerCoreVersion());
        self::assertSame('14.1.2', $package->getNewerCorePackageVersion());
    }

    public function testNoHintWithoutAnyVersionForANewerMajor(): void
    {
        $package = $this->evaluate('8.3.1', [['8.3.1', '12,13'], ['9.0.0', '13']]);

        self::assertNull($package->getNewerCoreVersion());
        self::assertNull($package->getNewerCorePackageVersion());
    }

    public function testInsecureComesFromTheExactVersion(): void
    {
        $package = $this->evaluate('8.3.1', [['8.3.1', '13', true], ['9.0.0', '13']]);

        self::assertTrue($package->isInsecure());
    }

    public function testUnsyncedExtensionsStayUnknown(): void
    {
        $package = $this->evaluate('8.3.1', []);

        self::assertNull($package->isOutdated());
    }

    /**
     * @param list<array{0: string, 1: string, 2?: bool}> $versions version, TYPO3 majors, insecure
     */
    private function evaluate(string $installed, array $versions): SitePackage
    {
        $site = new Site('Example', CmsType::Typo3, 'https://example.test', 'token-hash');
        $site->recordReport('13.4.20', '8.3.0', '10.11.0');
        $package = new SitePackage($site, 'typo3_console', 'TYPO3 Console', $installed, true);

        $entities = array_map(
            static fn (array $v): Typo3TerExtensionVersion => new Typo3TerExtensionVersion(
                extensionKey: 'typo3_console',
                version: $v[0],
                title: 'TYPO3 Console',
                insecure: $v[2] ?? false,
                description: null,
                uploadComment: null,
                state: 'stable',
                typo3Versions: $v[1],
                uploadDate: null,
            ),
            $versions,
        );
        $versionRepository = self::createStub(Typo3TerExtensionVersionRepository::class);
        $versionRepository->method('findBy')->willReturn($entities);
        $versionRepository->method('findOneByExtensionKeyAndVersion')->willReturnCallback(
            static fn (string $key, string $version): ?Typo3TerExtensionVersion => array_find($entities, static fn (Typo3TerExtensionVersion $v): bool => $v->getVersion() === $version),
        );

        $resolver = new PackageUpdateResolver($versionRepository, self::createStub(WordpressPluginVersionRepository::class), self::createStub(WordpressPluginInfoRepository::class), self::createStub(WordpressThemeInfoRepository::class));
        new SitePackageTerEvaluator($versionRepository, $resolver, self::createStub(SecurityNotifier::class))->evaluateAll([$package]);

        return $package;
    }
}
