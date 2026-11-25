<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Wordpress\PluginInfo;
use App\Entity\Wordpress\PluginVersion;
use App\Entity\Wordpress\ThemeInfo;
use App\Repository\Typo3\TerExtensionVersionRepository;
use App\Repository\Wordpress\PluginInfoRepository;
use App\Repository\Wordpress\PluginVersionRepository;
use App\Repository\Wordpress\ThemeInfoRepository;
use App\Service\PackageUpdate;
use App\Service\PackageUpdateResolver;
use PHPUnit\Framework\TestCase;

class PackageUpdateResolverTest extends TestCase
{
    public function testWordpressUpdatesThatNeedANewerWordpressAreNotOutdated(): void
    {
        $update = $this->resolveWordpress('6.4.3', [['2.0.0', '6.6'], ['1.9.0', '6.5']]);

        self::assertEquals(new PackageUpdate(false, null, '6.6', '2.0.0'), $update);
    }

    /**
     * WordPress installs plugins not tested against the site's version, only "requires" counts.
     */
    public function testAnUntestedOrUnknownRequirementCountsAsInstallable(): void
    {
        $update = $this->resolveWordpress('6.4.3', [['2.0.0', '6.6'], ['1.2.0', null], ['1.1.0', '6.0']]);

        self::assertEquals(new PackageUpdate(true, '1.2.0', '6.6', '2.0.0'), $update);
    }

    public function testFallsBackToThePluginInfoWithoutAVersionHistory(): void
    {
        $info = new PluginInfo('example-plugin', '3.0.0', '6.8', '6.9', null, null);

        self::assertEquals(new PackageUpdate(false, null, '6.8', '3.0.0'), $this->resolveWordpress('6.4.3', [], $info));
        self::assertEquals(new PackageUpdate(true, '3.0.0'), $this->resolveWordpress('6.9.1', [], $info));
    }

    public function testAnUnknownPluginStaysUnknown(): void
    {
        self::assertEquals(new PackageUpdate(), $this->resolveWordpress('6.4.3', []));
    }

    public function testWithoutACoreVersionEveryNewerVersionCounts(): void
    {
        self::assertEquals(new PackageUpdate(true, '2.0.0'), $this->resolveWordpress(null, [['2.0.0', '6.6']]));
    }

    public function testRatesAThemeByItsLatestVersion(): void
    {
        $info = new ThemeInfo('example-theme', '2.0.0', '6.6');

        // installed 1.0.0
        self::assertEquals(new PackageUpdate(true, '2.0.0'), $this->resolveTheme('6.7.1', $info));
        self::assertEquals(new PackageUpdate(false, null, '6.6', '2.0.0'), $this->resolveTheme('6.4.3', $info));
        self::assertEquals(new PackageUpdate(true, '2.0.0'), $this->resolveTheme('6.4.3', new ThemeInfo('example-theme', '2.0.0', null)));
        self::assertEquals(new PackageUpdate(false), $this->resolveTheme('6.7.1', new ThemeInfo('example-theme', '1.0.0', null)));
        // unknown to WordPress.org, e.g. a premium theme
        self::assertEquals(new PackageUpdate(), $this->resolveTheme('6.7.1', null));
    }

    private function resolveTheme(?string $coreVersion, ?ThemeInfo $info): PackageUpdate
    {
        $themeInfoRepository = self::createStub(ThemeInfoRepository::class);
        $themeInfoRepository->method('findOneBySlug')->willReturn($info);

        return new PackageUpdateResolver(self::createStub(TerExtensionVersionRepository::class), self::createStub(PluginVersionRepository::class), self::createStub(PluginInfoRepository::class), $themeInfoRepository)
            ->forWordpressTheme('example-theme', '1.0.0', $coreVersion);
    }

    /**
     * @param list<array{0: string, 1: ?string}> $versions newest first, version and "requires"
     */
    private function resolveWordpress(?string $coreVersion, array $versions, ?PluginInfo $info = null): PackageUpdate
    {
        $versionRepository = self::createStub(PluginVersionRepository::class);
        $versionRepository->method('findNewerThan')->willReturn(array_map(
            static fn (array $v): PluginVersion => new PluginVersion('example-plugin', $v[0], null, $v[1]),
            $versions,
        ));
        $infoRepository = self::createStub(PluginInfoRepository::class);
        $infoRepository->method('findOneBySlug')->willReturn($info);

        return new PackageUpdateResolver(self::createStub(TerExtensionVersionRepository::class), $versionRepository, $infoRepository, self::createStub(ThemeInfoRepository::class))
            ->forWordpressPlugin('example-plugin', '1.0.0', $coreVersion);
    }
}
