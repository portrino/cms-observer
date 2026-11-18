<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Repository\SitePackageRepository;
use App\Repository\Typo3TerExtensionMetaRepository;
use App\Repository\Typo3TerExtensionVersionRepository;
use App\Service\TerExtensionApiClient;
use App\Service\Typo3ExtensionVersionSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class Typo3ExtensionVersionSynchronizerTest extends TestCase
{
    // Regression: sync() used to array_slice() the first 20 keys every run,
    // so anything beyond that cutoff could never be reached.
    public function testNeverSyncedKeysArePrioritizedOverAlreadyKnownOnes(): void
    {
        $site = new Site('Example', CmsType::Typo3, 'https://example.test', 'token-hash');

        $alreadySyncedKeys = array_map(static fn (int $i): string => sprintf('known_%02d', $i), range(1, 20));
        $neverSyncedKeys = array_map(static fn (int $i): string => sprintf('new_%02d', $i), range(1, 5));

        $packages = array_map(
            static fn (string $key): SitePackage => new SitePackage($site, $key, $key, '1.0.0', true),
            [...$alreadySyncedKeys, ...$neverSyncedKeys],
        );

        $sitePackageRepository = self::createStub(SitePackageRepository::class);
        $sitePackageRepository->method('findByPackageType')->willReturn($packages);

        $metaRepository = self::createStub(Typo3TerExtensionMetaRepository::class);
        $metaRepository->method('findAllExtensionKeys')->willReturn($alreadySyncedKeys);
        $metaRepository->method('findOneByExtensionKey')->willReturn(null);

        $versionRepository = self::createStub(Typo3TerExtensionVersionRepository::class);
        $versionRepository->method('findOneByExtensionKeyAndVersion')->willReturn(null);

        $processedKeys = [];
        $apiClient = self::createStub(TerExtensionApiClient::class);
        $apiClient->method('getVersionHistory')->willReturnCallback(static function (string $key) use (&$processedKeys): array {
            $processedKeys[] = $key;

            return [];
        });
        $apiClient->method('getExtensionMeta')->willReturn(null);

        $synchronizer = new Typo3ExtensionVersionSynchronizer(
            $apiClient,
            $sitePackageRepository,
            $versionRepository,
            $metaRepository,
            self::createStub(EntityManagerInterface::class),
        );

        $synced = $synchronizer->sync();

        self::assertSame(20, $synced);
        foreach ($neverSyncedKeys as $key) {
            self::assertContains($key, $processedKeys, sprintf('Never-synced key "%s" should have been processed this run.', $key));
        }
    }
}
