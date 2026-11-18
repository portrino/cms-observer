<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\Typo3TerExtensionVersion;
use App\Repository\Typo3TerExtensionVersionRepository;
use App\Repository\WordpressPluginInfoRepository;
use App\Repository\WordpressPluginVersionRepository;
use App\Service\PackageUpdateResolver;
use App\Service\SecurityNotifier;
use App\Service\SitePackageTerEvaluator;
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
            static fn (array $v): Typo3TerExtensionVersion => new Typo3TerExtensionVersion('typo3_console', $v[0], 'TYPO3 Console', null, null, 'stable', $v[1], null, $v[2] ?? false),
            $versions,
        );
        $versionRepository = self::createStub(Typo3TerExtensionVersionRepository::class);
        $versionRepository->method('findBy')->willReturn($entities);
        $versionRepository->method('findOneByExtensionKeyAndVersion')->willReturnCallback(
            static fn (string $key, string $version): ?Typo3TerExtensionVersion => array_find($entities, static fn (Typo3TerExtensionVersion $v): bool => $v->getVersion() === $version),
        );

        $resolver = new PackageUpdateResolver($versionRepository, self::createStub(WordpressPluginVersionRepository::class), self::createStub(WordpressPluginInfoRepository::class));
        new SitePackageTerEvaluator($versionRepository, $resolver, self::createStub(SecurityNotifier::class))->evaluateAll([$package]);

        return $package;
    }
}
