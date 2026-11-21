<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\WordpressPluginInfo;
use App\Entity\WordpressPluginVulnerability;
use App\Repository\Typo3TerExtensionVersionRepository;
use App\Repository\WordpressPluginInfoRepository;
use App\Repository\WordpressPluginVersionRepository;
use App\Repository\WordpressPluginVulnerabilityRepository;
use App\Repository\WordpressThemeInfoRepository;
use App\Service\PackageUpdateResolver;
use App\Service\SecurityNotifier;
use App\Service\SitePackageWordpressEvaluator;
use PHPUnit\Framework\TestCase;

class SitePackageWordpressEvaluatorTest extends TestCase
{
    /**
     * @param list<WordpressPluginVulnerability>|null $vulnerabilities null: the Wordfence feed was never synced
     */
    private function createEvaluator(
        WordpressPluginInfoRepository $pluginInfoRepository,
        ?array $vulnerabilities = null,
        ?SecurityNotifier $notifier = null,
    ): SitePackageWordpressEvaluator {
        $vulnerabilityRepository = self::createStub(WordpressPluginVulnerabilityRepository::class);
        $vulnerabilityRepository->method('hasAny')->willReturn(null !== $vulnerabilities);
        $vulnerabilityRepository->method('findBySlug')->willReturn($vulnerabilities ?? []);

        // without a synced version history the resolver falls back to the plugin info
        $resolver = new PackageUpdateResolver(self::createStub(Typo3TerExtensionVersionRepository::class), self::createStub(WordpressPluginVersionRepository::class), $pluginInfoRepository, self::createStub(WordpressThemeInfoRepository::class));

        return new SitePackageWordpressEvaluator($resolver, $vulnerabilityRepository, $notifier ?? self::createStub(SecurityNotifier::class));
    }

    private function createVulnerability(string $toVersion, float $cvssScore, bool $informational = false): WordpressPluginVulnerability
    {
        $vulnerability = new WordpressPluginVulnerability('id-'.$toVersion.'-'.$cvssScore, 'example-plugin');
        $vulnerability->update(
            'Example vulnerability up to '.$toVersion,
            [['from_version' => '*', 'from_inclusive' => true, 'to_version' => $toVersion, 'to_inclusive' => true]],
            [],
            $informational,
            $cvssScore,
            $cvssScore >= 7.0 ? 'High' : 'Medium',
            null,
            'https://www.wordfence.com/threat-intel/vulnerabilities/example',
            [],
            null,
            null,
        );

        return $vulnerability;
    }

    private function createPackage(string $slug, string $version): SitePackage
    {
        $site = new Site('Example', CmsType::WordPress, 'https://example.test', 'token-hash');

        return new SitePackage($site, $slug, 'Example Plugin', $version, true);
    }

    /**
     * Regression test: WordPress.org's plugin info API can report a "latest"
     * version older than what's actually installed - installed 5.1.0 vs.
     * "latest" 5.0.9 must never be flagged outdated just because the strings
     * differ.
     */
    public function testInstalledVersionNewerThanLatestIsNotOutdated(): void
    {
        $package = $this->createPackage('example-plugin', '5.1.0');

        $info = new WordpressPluginInfo('example-plugin', '5.0.9', null, null, null, null);

        $pluginInfoRepository = self::createStub(WordpressPluginInfoRepository::class);
        $pluginInfoRepository->method('findOneBySlug')->willReturn($info);

        $evaluator = $this->createEvaluator($pluginInfoRepository);
        $evaluator->evaluateAll([$package]);

        self::assertFalse($package->isOutdated());
        self::assertNull($package->getLatestVersion());
    }

    public function testInstalledVersionOlderThanLatestIsOutdated(): void
    {
        $package = $this->createPackage('example-plugin', '5.0.0');

        $info = new WordpressPluginInfo('example-plugin', '5.0.9', null, null, null, null);

        $pluginInfoRepository = self::createStub(WordpressPluginInfoRepository::class);
        $pluginInfoRepository->method('findOneBySlug')->willReturn($info);

        $evaluator = $this->createEvaluator($pluginInfoRepository);
        $evaluator->evaluateAll([$package]);

        self::assertTrue($package->isOutdated());
        self::assertSame('5.0.9', $package->getLatestVersion());
    }

    public function testAffectedVersionIsInsecureWithTheMostSevereMatchAndNotifiesOnce(): void
    {
        $package = $this->createPackage('example-plugin', '1.0.0');
        $medium = $this->createVulnerability('1.5.0', 5.3);
        $high = $this->createVulnerability('2.0.0', 8.1);
        $fixedBefore = $this->createVulnerability('0.9.0', 9.8);

        $notifier = $this->createMock(SecurityNotifier::class);
        $notifier->expects(self::once())->method('addFinding');

        $evaluator = $this->createEvaluator(self::createStub(WordpressPluginInfoRepository::class), [$medium, $high, $fixedBefore], $notifier);
        $evaluator->evaluate($package);
        $evaluator->evaluate($package); // already insecure: no second mail

        self::assertTrue($package->isInsecure());
        self::assertSame($high, $package->getMatchedWordpressVulnerability());
    }

    public function testInformationalVulnerabilitiesAreIgnored(): void
    {
        $package = $this->createPackage('example-plugin', '1.0.0');

        $evaluator = $this->createEvaluator(self::createStub(WordpressPluginInfoRepository::class), [$this->createVulnerability('2.0.0', 5.3, informational: true)]);
        $evaluator->evaluate($package);

        self::assertFalse($package->isInsecure());
        self::assertNull($package->getMatchedWordpressVulnerability());
    }

    public function testNoAffectingVulnerabilityIsSecure(): void
    {
        $package = $this->createPackage('example-plugin', '2.1.0');

        $evaluator = $this->createEvaluator(self::createStub(WordpressPluginInfoRepository::class), [$this->createVulnerability('2.0.0', 8.1)]);
        $evaluator->evaluate($package);

        self::assertFalse($package->isInsecure());
    }

    public function testUnknownPluginResetsVersionStatus(): void
    {
        $package = $this->createPackage('unknown-plugin', '1.0.0');

        $pluginInfoRepository = self::createStub(WordpressPluginInfoRepository::class);
        $pluginInfoRepository->method('findOneBySlug')->willReturn(null);

        $evaluator = $this->createEvaluator($pluginInfoRepository);
        $evaluator->evaluateAll([$package]);

        self::assertNull($package->isOutdated());
        self::assertNull($package->isInsecure());
        self::assertNull($package->getLatestVersion());
    }
}
