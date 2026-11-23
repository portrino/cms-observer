<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\CmsType;
use App\Entity\EndOfLifeRelease;
use App\Entity\Site;
use App\Repository\EndOfLifeReleaseRepository;
use App\Service\DbVersionNormalizer;
use App\Service\SecurityNotifier;
use App\Service\SiteRuntimeVersionEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class SiteRuntimeVersionEvaluatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, ?string, bool, ?TranslatableMessage}>
     */
    public static function phpVersions(): iterable
    {
        // installed, cycle EOL, latest security release, expected insecure, expected finding detail
        yield 'older than the latest security release' => ['8.4.24', false, '8.4.27', true, new TranslatableMessage('event.detail.php_missing_fixes', ['fixed' => '8.4.27'])];
        yield 'equal to the latest security release' => ['8.4.27', false, '8.4.27', false, null];
        yield 'newer than the latest security release' => ['8.4.28', false, '8.4.27', false, null];
        yield 'no security release known' => ['8.4.24', false, null, false, null];
        yield 'EOL cycle' => ['8.0.30', true, null, true, new TranslatableMessage('event.detail.php_eol')];
    }

    #[DataProvider('phpVersions')]
    public function testPhpSecurityStatus(string $installed, bool $isEol, ?string $securityVersion, bool $expectedInsecure, ?TranslatableMessage $expectedFinding): void
    {
        $site = new Site('Example', CmsType::Typo3, 'https://example.test', 'token-hash');
        $site->recordReport('13.4.0', $installed, '10.11.0-MariaDB');

        $release = new EndOfLifeRelease('php', '8.4', $isEol, '8.4.28', 'https://endoflife.date/php');
        $release->setLatestSecurityVersion($securityVersion);

        $repository = self::createStub(EndOfLifeReleaseRepository::class);
        $repository->method('findOneByProductAndCycle')->willReturnCallback(
            static fn (string $product): ?EndOfLifeRelease => 'php' === $product ? $release : null,
        );

        $notifier = $this->createMock(SecurityNotifier::class);
        $notifier->expects(null === $expectedFinding ? self::never() : self::once())
            ->method('addFinding')
            ->with($site, new TranslatableMessage('event.subject.php', ['version' => $installed]), $expectedFinding);

        new SiteRuntimeVersionEvaluator($repository, $notifier)->evaluate($site);

        self::assertSame($expectedInsecure, $site->isPhpInsecure());
    }

    public function testAnUpdateOutOfAnInsecureVersionIsRecordedAsResolvedButNotMailed(): void
    {
        $site = new Site('Example', CmsType::Typo3, 'https://example.test', 'token-hash');
        $release = new EndOfLifeRelease('php', '8.4', false, '8.4.28', 'https://endoflife.date/php');
        $release->setLatestSecurityVersion('8.4.27');

        $repository = self::createStub(EndOfLifeReleaseRepository::class);
        $repository->method('findOneByProductAndCycle')->willReturnCallback(
            static fn (string $product): ?EndOfLifeRelease => 'php' === $product ? $release : null,
        );

        $site->recordReport('13.4.0', '8.4.24', '10.11.0-MariaDB');
        new SiteRuntimeVersionEvaluator($repository, self::createStub(SecurityNotifier::class))->evaluate($site);
        self::assertTrue($site->isPhpInsecure());

        $notifier = $this->createMock(SecurityNotifier::class);
        $notifier->expects(self::never())->method('addFinding');
        $notifier->expects(self::once())->method('recordResolved')->with($site, new TranslatableMessage('event.subject.php', ['version' => '8.4.28']), new TranslatableMessage('event.detail.php_secure'));

        $site->recordReport('13.4.0', '8.4.28', '10.11.0-MariaDB');
        new SiteRuntimeVersionEvaluator($repository, $notifier)->evaluate($site);
        self::assertFalse($site->isPhpInsecure());

        // already secure: nothing to record on the next evaluation
        $quietNotifier = $this->createMock(SecurityNotifier::class);
        $quietNotifier->expects(self::never())->method('recordResolved');
        new SiteRuntimeVersionEvaluator($repository, $quietNotifier)->evaluate($site);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function outdatedVersions(): iterable
    {
        // installed version, expected outdated (latest known release: 8.4.27 / 10.11.20)
        yield 'older than the latest release' => ['older', true];
        yield 'equal to the latest release' => ['equal', false];
        yield 'newer than the latest release (endoflife.date lags behind)' => ['newer', false];
    }

    #[DataProvider('outdatedVersions')]
    public function testOnlyVersionsOlderThanTheLatestReleaseAreOutdated(string $case, bool $expectedOutdated): void
    {
        $php = ['older' => '8.4.26', 'equal' => '8.4.27', 'newer' => '8.4.28'][$case];
        $db = ['older' => '10.11.19', 'equal' => '10.11.20', 'newer' => '10.11.21'][$case];

        $site = new Site('Example', CmsType::Typo3, 'https://example.test', 'token-hash');
        $site->recordReport('13.4.0', $php, $db.'-MariaDB', 'mariadb');

        $repository = self::createStub(EndOfLifeReleaseRepository::class);
        $repository->method('findOneByProductAndCycle')->willReturnCallback(
            static fn (string $product): ?EndOfLifeRelease => match ($product) {
                'php' => new EndOfLifeRelease('php', '8.4', false, '8.4.27', 'https://endoflife.date/php'),
                'mariadb' => new EndOfLifeRelease('mariadb', '10.11', false, '10.11.20', 'https://endoflife.date/mariadb'),
                default => null,
            },
        );

        new SiteRuntimeVersionEvaluator($repository, self::createStub(SecurityNotifier::class))->evaluate($site);

        self::assertSame($expectedOutdated, $site->isPhpOutdated());
        self::assertSame($expectedOutdated ? '8.4.27' : null, $site->getPhpLatestVersion());
        self::assertSame($expectedOutdated, $site->isDbOutdated());
        self::assertSame($expectedOutdated ? '10.11.20' : null, $site->getDbLatestVersion());
    }

    /**
     * @return iterable<string, array{?string, string, string, string}>
     */
    public static function dbVersions(): iterable
    {
        // reported platform, reported version, expected product, expected plain version
        yield 'TYPO3 12 label, no platform' => [null, 'MySQL 10.11.19-MariaDB-ubu2204-log', 'mariadb', '10.11.19'];
        yield 'TYPO3 13 format with platform' => ['mariadb', '10.11.19-MariaDB-ubu2204-log', 'mariadb', '10.11.19'];
        yield 'MariaDB behind the 5.5.5 prefix' => [null, '5.5.5-10.11.19-MariaDB', 'mariadb', '10.11.19'];
        yield 'plain MySQL' => [null, '8.0.36', 'mysql', '8.0.36'];
        yield 'PostgreSQL label, no platform' => [null, 'PostgreSQL 15.2', 'postgresql', '15.2'];
        yield 'PostgreSQL with platform' => ['postgresql', '15.2 (Debian 15.2-1.pgdg110+1)', 'postgresql', '15.2'];
        yield 'unparseable' => [null, 'unknown', 'mysql', 'unknown'];
    }

    #[DataProvider('dbVersions')]
    public function testDbVersionIsNormalized(?string $platform, string $raw, string $expectedProduct, string $expectedVersion): void
    {
        self::assertSame([$expectedProduct, $expectedVersion], DbVersionNormalizer::normalize($platform, $raw));
    }

    public function testLegacyTypo3V12DbVersionFindsTheMariadbRelease(): void
    {
        $site = new Site('Example', CmsType::Typo3, 'https://example.test', 'token-hash');
        $site->recordReport('12.4.0', '8.2.0', 'MySQL 10.11.19-MariaDB-ubu2204-log');

        $repository = self::createStub(EndOfLifeReleaseRepository::class);
        $repository->method('findOneByProductAndCycle')->willReturnCallback(
            static fn (string $product, string $cycle): ?EndOfLifeRelease => 'mariadb' === $product && '10.11' === $cycle
                ? new EndOfLifeRelease('mariadb', '10.11', false, '10.11.20', 'https://endoflife.date/mariadb')
                : null,
        );

        new SiteRuntimeVersionEvaluator($repository, self::createStub(SecurityNotifier::class))->evaluate($site);

        self::assertFalse($site->isDbInsecure());
        self::assertTrue($site->isDbOutdated());
        self::assertSame('10.11.20', $site->getDbLatestVersion());
    }

    /**
     * @return iterable<string, array{string, string, bool, string}>
     */
    public static function postgresqlVersions(): iterable
    {
        // reported version, expected cycle, cycle EOL, latest release in the cycle
        yield 'major-only cycle since 10' => ['16.2', '16', false, '16.16'];
        yield 'major.minor cycle before 10' => ['9.6.24', '9.6', true, '9.6.24'];
    }

    #[DataProvider('postgresqlVersions')]
    public function testPostgresqlIsEvaluatedByItsCycle(string $installed, string $cycle, bool $isEol, string $latest): void
    {
        $site = new Site('Example', CmsType::Typo3, 'https://example.test', 'token-hash');
        $site->recordReport('13.4.0', '8.3.0', $installed, 'postgresql');

        $repository = self::createStub(EndOfLifeReleaseRepository::class);
        $repository->method('findOneByProductAndCycle')->willReturnCallback(
            static fn (string $product, string $requestedCycle): ?EndOfLifeRelease => 'postgresql' === $product && $cycle === $requestedCycle
                ? new EndOfLifeRelease('postgresql', $cycle, $isEol, $latest, 'https://endoflife.date/postgresql')
                : null,
        );

        $notifier = $this->createMock(SecurityNotifier::class);
        $notifier->expects($isEol ? self::once() : self::never())->method('addFinding');

        new SiteRuntimeVersionEvaluator($repository, $notifier)->evaluate($site);

        self::assertSame($isEol, $site->isDbInsecure());
        self::assertSame($installed !== $latest, $site->isDbOutdated());
    }
}
