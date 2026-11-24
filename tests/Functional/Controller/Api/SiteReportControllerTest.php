<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Entity\CmsType;
use App\Entity\PackageType;
use App\Entity\Site;
use App\Entity\SiteEvent;
use App\Entity\WordpressPluginVulnerability;
use App\Repository\SiteEventRepository;
use App\Repository\SitePackageRepository;
use App\Repository\SiteReportRepository;
use App\Security\SiteTokenFactory;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SiteReportControllerTest extends WebTestCase
{
    use CreatesUserTrait;

    public function testReportIsAcceptedAndPersisted(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token, $site] = $this->createSite();

        $client->request('POST', '/api/v1/sites/report', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'coreVersion' => '13.4.0',
            'phpVersion' => '8.4.0',
            'dbVersion' => '11.8.0',
            'clientVersion' => '1.0.0',
            'applicationContext' => 'Production/Staging',
            'extensions' => [
                ['name' => 'news', 'title' => 'News', 'version' => '14.1.1'],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(202);
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', (string) $client->getResponse()->getContent());

        $reports = static::getContainer()->get(SiteReportRepository::class)->findBy(['site' => $site]);
        self::assertCount(1, $reports);
        self::assertSame('13.4.0', $reports[0]->getCoreVersion());
        self::assertSame('1.0.0', $reports[0]->getPayload()['clientVersion']);
        self::assertSame('Production/Staging', $reports[0]->getPayload()['applicationContext']);
    }

    /**
     * TYPO3 12 (DBAL 3) prefixes the server version with the platform label, older
     * clients send that without dbPlatform. It must neither overflow the 32-char
     * columns nor be evaluated as MySQL.
     */
    public function testLegacyTypo3V12DbVersionIsNormalized(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token, $site] = $this->createSite();

        $this->postReport($client, $token, ['dbVersion' => 'MySQL 10.11.19-MariaDB-ubu2204-log']);

        self::assertResponseStatusCodeSame(202);
        $report = static::getContainer()->get(SiteReportRepository::class)->findOneBy(['site' => $site]);
        self::assertSame('10.11.19', $report?->getDbVersion());
        self::assertSame('MySQL 10.11.19-MariaDB-ubu2204-log', $report->getPayload()['dbVersion']);
        $site = static::getContainer()->get(EntityManagerInterface::class)->find(Site::class, $site->getId());
        self::assertSame('mariadb', $site?->getCurrentDbPlatform());
        self::assertSame('10.11.19', $site->getCurrentDbVersion());
    }

    public function testDbPlatformIsStored(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token, $site] = $this->createSite();

        $this->postReport($client, $token, ['dbVersion' => '10.11.19-MariaDB-ubu2204-log', 'dbPlatform' => 'mariadb']);

        self::assertResponseStatusCodeSame(202);
        $report = static::getContainer()->get(SiteReportRepository::class)->findOneBy(['site' => $site]);
        self::assertSame('10.11.19', $report?->getDbVersion());
        self::assertSame('mariadb', $report->getPayload()['dbPlatform']);
        $site = static::getContainer()->get(EntityManagerInterface::class)->find(Site::class, $site->getId());
        self::assertSame('mariadb', $site?->getCurrentDbPlatform());
    }

    /**
     * A newly reported WordPress plugin is checked against the stored Wordfence data right away,
     * not only by the next daily vulnerability sync.
     */
    public function testReportedWordpressPluginIsCheckedForVulnerabilitiesAtIngest(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $this->createUser('ingest-notify@example.test', 'Ingest Notify', 'password');
        [$token, $site] = $this->createSite(CmsType::WordPress);

        $vulnerability = new WordpressPluginVulnerability('848ccbdc-c6f1-480f-a272-cd459e706713', 'contact-form-7');
        $vulnerability->update(
            'Contact Form 7 <= 6.0.5 - Order Replay Vulnerability',
            [['from_version' => '*', 'from_inclusive' => true, 'to_version' => '6.0.5', 'to_inclusive' => true]],
            ['6.0.6'],
            false,
            5.3,
            'Medium',
            'CVE-2025-3247',
            'https://www.wordfence.com/threat-intel/vulnerabilities/id/848ccbdc-c6f1-480f-a272-cd459e706713',
            [],
            null,
            null,
        );
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($vulnerability);
        $entityManager->flush();

        $client->request('POST', '/api/v1/sites/report', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'coreVersion' => '6.5.2',
            'phpVersion' => '8.4.0',
            'dbVersion' => '11.8.0',
            'extensions' => [
                ['name' => 'contact-form-7', 'title' => 'Contact Form 7', 'version' => '6.0.4'],
                ['name' => 'akismet', 'title' => 'Akismet', 'version' => '5.3.3'],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(202);

        $packages = static::getContainer()->get(SitePackageRepository::class);
        $vulnerable = $packages->findOneBySiteAndName($site, 'contact-form-7');
        self::assertTrue($vulnerable?->isInsecure());
        self::assertSame('CVE-2025-3247', $vulnerable->getMatchedWordpressVulnerability()?->getCve());
        self::assertFalse($packages->findOneBySiteAndName($site, 'akismet')?->isInsecure());

        self::assertEmailCount(1);
        self::assertEmailHtmlBodyContains(self::getMailerMessage() ?? self::fail('No email sent'), 'CVE-2025-3247');
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function acceptedDomains(): iterable
    {
        yield 'matching domain' => [['domains' => ['example.test', 'www.example.test']]];
        yield 'domains missing (older clients)' => [[]];
        yield 'no domain known (relative TYPO3 base)' => [['domains' => []]];
    }

    /**
     * @param array<string, mixed> $fields
     */
    #[DataProvider('acceptedDomains')]
    public function testReportIsAcceptedWhenTheDomainsMatchOrAreUnknown(array $fields): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token, $site] = $this->createSite();

        $this->postReport($client, $token, $fields + ['dbVersion' => '11.8.0']);

        self::assertResponseStatusCodeSame(202);
        self::assertCount(1, static::getContainer()->get(SiteReportRepository::class)->findBy(['site' => $site]));
    }

    /**
     * A staging copy that pushes with the production token must not overwrite the production data.
     */
    public function testReportFromAnotherDomainIsRejected(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token, $site] = $this->createSite();

        $this->postReport($client, $token, ['dbVersion' => '11.8.0', 'domains' => ['staging.example.test']]);

        self::assertResponseStatusCodeSame(409);
        self::assertJsonStringEqualsJsonString(
            '{"status":"rejected","reason":"domain_mismatch","message":"Report from staging.example.test does not match the site URL https://example.test"}',
            (string) $client->getResponse()->getContent(),
        );
        self::assertSame([], static::getContainer()->get(SiteReportRepository::class)->findBy(['site' => $site]));
        $site = static::getContainer()->get(EntityManagerInterface::class)->find(Site::class, $site->getId());
        self::assertNotNull($site);
        self::assertNull($site->getCurrentCoreVersion());
        self::assertSame(['staging.example.test'], $site->getLastRejectedReportDomains());
        self::assertNotNull($site->getLastRejectedReportAt());
    }

    public function testWrongTokenIsRejected(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $this->createSite();

        $client->request('POST', '/api/v1/sites/report', server: [
            'HTTP_AUTHORIZATION' => 'Bearer wrong-token',
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'coreVersion' => '13.4.0',
            'phpVersion' => '8.4.0',
            'dbVersion' => '11.8.0',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
    }

    public function testMissingRequiredFieldIsRejected(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token] = $this->createSite();

        $client->request('POST', '/api/v1/sites/report', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'phpVersion' => '8.4.0',
            'dbVersion' => '11.8.0',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * Clients may be newer than the Observer: a field it doesn't know yet must not fail the push.
     */
    public function testUnknownFieldsAreIgnored(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token] = $this->createSite();

        $this->postReport($client, $token, ['dbVersion' => '10.11.0', 'someFutureField' => [['name' => 'x']]]);

        self::assertResponseStatusCodeSame(202);
    }

    public function testPackagesCarryTheirTypeAndThemesAreCheckedAgainstThemeVulnerabilities(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $this->createUser('themes@example.test', 'Themes', 'password');
        [$token, $site] = $this->createSite(CmsType::WordPress);

        // only the theme "astra" is vulnerable, not the plugin of the same slug
        $vulnerability = new WordpressPluginVulnerability('5c3b5e2a-0000-4000-8000-000000000001', 'astra', PackageType::WordpressTheme);
        $vulnerability->update('Astra <= 4.0.0 - XSS', [['from_version' => '*', 'from_inclusive' => true, 'to_version' => '4.0.0', 'to_inclusive' => true]], ['4.0.1'], false, 6.4, 'Medium', 'CVE-2026-0001', 'https://www.wordfence.com/threat-intel/vulnerabilities/id/5c3b5e2a', [], null, null);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($vulnerability);
        $entityManager->flush();

        $plugin = ['name' => 'astra', 'type' => 'plugin', 'title' => 'Astra Plugin', 'version' => '3.0.0', 'active' => true];
        $theme = ['name' => 'astra', 'type' => 'theme', 'title' => 'Astra', 'version' => '3.0.0', 'active' => true];
        $this->postReport($client, $token, ['dbVersion' => '11.8.0', 'packages' => [$plugin, $theme]]);
        self::assertResponseStatusCodeSame(202);
        self::assertEmailCount(1);
        self::assertEmailHtmlBodyContains(self::getMailerMessage() ?? self::fail('No email sent'), 'Theme astra 3.0.0');

        $packages = static::getContainer()->get(SitePackageRepository::class);
        self::assertTrue($packages->findOneBySiteAndName($site, 'astra', PackageType::WordpressTheme)?->isInsecure());
        self::assertFalse($packages->findOneBySiteAndName($site, 'astra')?->isInsecure());

        // an older client sends "extensions": its plugins only, the themes stay
        $this->postReport($client, $token, ['dbVersion' => '11.8.0', 'extensions' => [array_diff_key($plugin, ['type' => true])]]);
        self::assertNotNull($packages->findOneBySiteAndName($site, 'astra', PackageType::WordpressTheme));

        // "packages" is complete for every type: no theme in it means none installed; an unknown type is left out
        $this->postReport($client, $token, ['dbVersion' => '11.8.0', 'packages' => [$plugin, ['type' => 'block-pattern'] + $plugin]]);
        self::assertResponseStatusCodeSame(202);
        self::assertNull($packages->findOneBySiteAndName($site, 'astra', PackageType::WordpressTheme));
        self::assertNotNull($packages->findOneBySiteAndName($site, 'astra'));
        self::assertCount(1, $packages->findBy(['site' => $site]));
        self::assertNotNull(static::getContainer()->get(SiteEventRepository::class)->findOneBy(['site' => $site, 'subject' => 'astra (theme)']));
    }

    public function testTypo3PackagesWithAndWithoutType(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token, $site] = $this->createSite();

        $this->postReport($client, $token, ['dbVersion' => '10.11.0', 'packages' => [
            ['name' => 'news', 'type' => 'extension', 'title' => 'News', 'version' => '12.0.0'],
            ['name' => 'powermail', 'title' => 'Powermail', 'version' => '13.0.0'],
        ]]);

        self::assertResponseStatusCodeSame(202);
        $packages = static::getContainer()->get(SitePackageRepository::class);
        self::assertSame(PackageType::Typo3CmsExtension, $packages->findOneBySiteAndName($site, 'news')?->getPackageType());
        self::assertNotNull($packages->findOneBySiteAndName($site, 'powermail'));
    }

    public function testANonProductionContextIsMailedOnceAndItsFixRecorded(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $this->createUser('context@example.test', 'Context', 'password');
        [$token, $site] = $this->createSite();

        $this->postReport($client, $token, ['dbVersion' => '10.11.0', 'applicationContext' => 'Production/Live']);
        self::assertEmailCount(0);

        $this->postReport($client, $token, ['dbVersion' => '10.11.0', 'applicationContext' => 'Development']);
        self::assertEmailCount(1);
        self::assertEmailHtmlBodyContains(self::getMailerMessage() ?? self::fail('No email sent'), 'Application context Development');

        // still Development: already mailed
        $this->postReport($client, $token, ['dbVersion' => '10.11.0', 'applicationContext' => 'Development']);
        self::assertEmailCount(0);

        $this->postReport($client, $token, ['dbVersion' => '10.11.0', 'applicationContext' => 'Production']);
        self::assertEmailCount(0);

        $events = static::getContainer()->get(SiteEventRepository::class)->findBy(['site' => $site], ['id' => 'ASC']);
        $types = array_map(static fn (SiteEvent $event): string => $event->getType()->value, $events);
        self::assertSame(1, count(array_keys($types, 'became-insecure', true)));
        self::assertSame(1, count(array_keys($types, 'became-secure', true)));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function postReport(KernelBrowser $client, string $token, array $fields): void
    {
        $client->request('POST', '/api/v1/sites/report', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode($fields + ['coreVersion' => '12.4.0', 'phpVersion' => '8.2.0'], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{0: string, 1: Site}
     */
    private function createSite(CmsType $type = CmsType::Typo3): array
    {
        [$token, $hash] = new SiteTokenFactory()->generate();
        $site = new Site('Example', $type, 'https://example.test', $hash);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($site);
        $entityManager->flush();

        return [$token, $site];
    }
}
