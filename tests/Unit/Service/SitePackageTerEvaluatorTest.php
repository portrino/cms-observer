<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Repository\Typo3TerExtensionVersionRepository;
use App\Service\SecurityNotifier;
use App\Service\SitePackageTerEvaluator;
use App\Service\TerExtensionApiClient;
use PHPUnit\Framework\TestCase;

class SitePackageTerEvaluatorTest extends TestCase
{
    private function createPackage(string $version): SitePackage
    {
        $site = new Site('Example', CmsType::Typo3, 'https://example.test', 'token-hash');

        return new SitePackage($site, 'typo3_console', 'TYPO3 Console', $version, true);
    }

    /**
     * Regression test: TER can report a "latest" version that is older than
     * what's actually installed (e.g. a locally patched build, or TER simply
     * hasn't caught up) - installed 9.0.1 vs. TER's "latest" 9.0.0 must never
     * be flagged outdated just because the strings differ.
     */
    public function testInstalledVersionNewerThanTerLatestIsNotOutdated(): void
    {
        $package = $this->createPackage('9.0.1');

        $versionRepository = self::createStub(Typo3TerExtensionVersionRepository::class);
        $versionRepository->method('findOneByExtensionKeyAndVersion')->willReturn(null);

        $apiClient = self::createStub(TerExtensionApiClient::class);
        $apiClient->method('getLatestVersions')->willReturn(['typo3_console' => '9.0.0']);

        $evaluator = new SitePackageTerEvaluator($versionRepository, $apiClient, self::createStub(SecurityNotifier::class));
        $evaluator->evaluateAll([$package]);

        self::assertFalse($package->isOutdated());
        self::assertNull($package->getLatestVersion());
    }

    public function testInstalledVersionOlderThanTerLatestIsOutdated(): void
    {
        $package = $this->createPackage('8.3.1');

        $versionRepository = self::createStub(Typo3TerExtensionVersionRepository::class);
        $versionRepository->method('findOneByExtensionKeyAndVersion')->willReturn(null);

        $apiClient = self::createStub(TerExtensionApiClient::class);
        $apiClient->method('getLatestVersions')->willReturn(['typo3_console' => '9.0.0']);

        $evaluator = new SitePackageTerEvaluator($versionRepository, $apiClient, self::createStub(SecurityNotifier::class));
        $evaluator->evaluateAll([$package]);

        self::assertTrue($package->isOutdated());
        self::assertSame('9.0.0', $package->getLatestVersion());
    }

    public function testInstalledVersionEqualToTerLatestIsNotOutdated(): void
    {
        $package = $this->createPackage('9.0.0');

        $versionRepository = self::createStub(Typo3TerExtensionVersionRepository::class);
        $versionRepository->method('findOneByExtensionKeyAndVersion')->willReturn(null);

        $apiClient = self::createStub(TerExtensionApiClient::class);
        $apiClient->method('getLatestVersions')->willReturn(['typo3_console' => '9.0.0']);

        $evaluator = new SitePackageTerEvaluator($versionRepository, $apiClient, self::createStub(SecurityNotifier::class));
        $evaluator->evaluateAll([$package]);

        self::assertFalse($package->isOutdated());
        self::assertNull($package->getLatestVersion());
    }
}
