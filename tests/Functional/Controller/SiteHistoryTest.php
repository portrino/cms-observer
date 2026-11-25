<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SiteEvent;
use App\Entity\SiteEventType;
use App\Entity\Typo3ExtensionAdvisory;
use App\Entity\Typo3TerExtensionVersion;
use App\Repository\SiteEventRepository;
use App\Repository\SitePackageRepository;
use App\Security\SiteTokenFactory;
use App\Service\SitePackageTerEvaluator;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SiteHistoryTest extends WebTestCase
{
    use CreatesUserTrait;

    public function testFirstReportIsRecordedAsOneEvent(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token, $site] = $this->createSite();

        $this->postReport($client, $token, '13.4.20', [['name' => 'news', 'version' => '12.3.0'], ['name' => 'powermail', 'version' => '13.0.0']]);

        self::assertResponseStatusCodeSame(202);
        self::assertSame([
            ['first-report', null, null, 'TYPO3 13.4.20', '2 packages'],
        ], $this->events($site));
    }

    public function testFirstReportCountsThePackagesOfOlderClients(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token, $site] = $this->createSite();

        $this->postReport($client, $token, '13.4.20', [['name' => 'news', 'version' => '12.3.0'], ['name' => 'powermail', 'version' => '13.0.0']], field: 'extensions');

        self::assertResponseStatusCodeSame(202);
        self::assertSame([
            ['first-report', null, null, 'TYPO3 13.4.20', '2 packages'],
        ], $this->events($site));
    }

    public function testChangesBetweenReportsAreRecorded(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token, $site] = $this->createSite();
        $this->postReport($client, $token, '13.4.20', [
            ['name' => 'news', 'version' => '12.3.0'],
            ['name' => 'powermail', 'version' => '13.0.0'],
            ['name' => 'old_ext', 'version' => '1.0.0'],
        ], clientVersion: '1.0.0');

        $this->postReport($client, $token, '13.4.21', [
            ['name' => 'news', 'version' => '12.3.1'],
            ['name' => 'powermail', 'version' => '13.0.0', 'active' => false],
            ['name' => 'container', 'version' => '3.1.0'],
        ], clientVersion: '1.1.0', phpVersion: '8.3.24');

        self::assertResponseStatusCodeSame(202);
        self::assertEqualsCanonicalizing([
            ['first-report', null, null, 'TYPO3 13.4.20', '3 packages'],
            ['core-changed', null, '13.4.20', '13.4.21', null],
            ['php-changed', null, '8.2.29', '8.3.24', null],
            ['client-changed', null, '1.0.0', '1.1.0', null],
            ['package-updated', 'news', '12.3.0', '12.3.1', null],
            ['package-deactivated', 'powermail', null, '13.0.0', null],
            ['package-installed', 'container', null, '3.1.0', null],
            ['package-removed', 'old_ext', '1.0.0', null, null],
        ], $this->events($site));

        // the removed package is gone, so it isn't checked for vulnerabilities anymore
        $packages = static::getContainer()->get(SitePackageRepository::class);
        self::assertNull($packages->findOneBySiteAndName($site, 'old_ext'));
        self::assertNotNull($packages->findOneBySiteAndName($site, 'container'));
    }

    /**
     * "v7.1.1" is stored and shown as "7.1.1"; with the "v" it counted as older than the same
     * version from the TER and was always flagged outdated.
     */
    public function testAVPrefixedVersionIsStoredWithoutTheV(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token, $site] = $this->createSite();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new Typo3TerExtensionVersion(
            extensionKey: 'secure_downloads',
            version: '7.1.1',
            title: 'Secure Downloads',
            insecure: false,
            description: null,
            uploadComment: null,
            state: 'stable',
            typo3Versions: '13',
            uploadDate: null,
        ));
        $entityManager->flush();

        $this->postReport($client, $token, '13.4.20', [['name' => 'secure_downloads', 'version' => 'v7.1.1']]);
        // the same version again, now without "v": no "version changed" entry
        $this->postReport($client, $token, '13.4.20', [['name' => 'secure_downloads', 'version' => '7.1.1']]);

        self::assertResponseStatusCodeSame(202);
        $package = static::getContainer()->get(SitePackageRepository::class)->findOneBySiteAndName($site, 'secure_downloads');
        self::assertSame('7.1.1', $package?->getVersion());
        static::getContainer()->get(SitePackageTerEvaluator::class)->evaluateAll([$package]);
        self::assertFalse($package->isOutdated());
        self::assertCount(1, $this->events($site));
    }

    public function testReportWithoutExtensionsLeavesThePackagesAlone(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token, $site] = $this->createSite();
        $this->postReport($client, $token, '13.4.20', [['name' => 'news', 'version' => '12.3.0']]);

        $this->postReport($client, $token, '13.4.20', null);

        self::assertResponseStatusCodeSame(202);
        self::assertNotNull(static::getContainer()->get(SitePackageRepository::class)->findOneBySiteAndName($site, 'news'));
        self::assertCount(1, $this->events($site));
    }

    public function testRejectedReportIsRecorded(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [$token, $site] = $this->createSite();

        $this->postReport($client, $token, '13.4.20', [], domains: ['staging.example.test']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame([['report-rejected', null, null, null, 'staging.example.test']], $this->events($site));
    }

    public function testPackageBecomingInsecureIsRecorded(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $this->createUser('history-notify@example.test', 'History Notify', 'password');
        [$token, $site] = $this->createSite();
        $advisory = new Typo3ExtensionAdvisory(
            advisoryId: 'TYPO3-EXT-SA-2026-001',
            extensionKey: 'news',
            title: 'Cross-Site Scripting in news',
            url: 'https://typo3.org/security/advisory/typo3-ext-sa-2026-001',
            publishedAt: new \DateTimeImmutable(),
            affectedVersionsRaw: '12.3.0 and below',
            severity: 'medium',
            cveIds: null,
        );
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($advisory);
        $entityManager->flush();

        $this->postReport($client, $token, '13.4.20', [['name' => 'news', 'version' => '12.3.0']]);

        self::assertResponseStatusCodeSame(202);
        $types = array_column($this->events($site), 0);
        self::assertContains('became-insecure', $types);
    }

    public function testHistoryPageIsPagedAndFiltered(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('history@example.test', 'History', 'password'));
        [, $site] = $this->createSite();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        for ($i = 1; $i <= 50; ++$i) {
            $entityManager->persist(new SiteEvent($site, SiteEventType::PackageUpdated, 'news', '12.0.'.($i - 1), '12.0.'.$i));
        }
        $entityManager->persist(new SiteEvent($site, SiteEventType::PackageInstalled, 'powermail', null, '13.0.0'));
        $entityManager->flush();

        $client->request('GET', '/projects/'.$site->getId());
        self::assertSelectorExists('a[href="/projects/'.$site->getId().'/history"]');

        $client->request('GET', '/projects/'.$site->getId().'/history');
        self::assertResponseIsSuccessful();
        self::assertCount(50, $client->getCrawler()->filter('#siteHistory tbody tr'));
        self::assertSelectorTextContains('#siteHistory tbody tr', 'powermail');
        self::assertSelectorExists('.pagination a[href*="page=2"]');

        $client->request('GET', '/projects/'.$site->getId().'/history?page=2');
        self::assertCount(1, $client->getCrawler()->filter('#siteHistory tbody tr'));

        $client->request('GET', '/projects/'.$site->getId().'/history?q=powermail');
        self::assertCount(1, $client->getCrawler()->filter('#siteHistory tbody tr'));

        $client->request('GET', '/projects/'.$site->getId().'/history?type=package-updated');
        self::assertCount(50, $client->getCrawler()->filter('#siteHistory tbody tr'));
        self::assertSelectorNotExists('.pagination');

        $client->request('GET', '/projects/'.$site->getId().'/history?page=0');
        self::assertResponseStatusCodeSame(404);
    }

    public function testHistoryPageNeedsALogin(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        [, $site] = $this->createSite();

        $client->request('GET', '/projects/'.$site->getId().'/history');

        self::assertResponseRedirects();
    }

    /**
     * @param list<array{name: string, version: string, active?: bool}>|null $packages
     * @param list<string>|null                                              $domains
     * @param 'extensions'|'packages'                                        $field    current clients send packages, older ones extensions
     */
    private function postReport(KernelBrowser $client, string $token, string $coreVersion, ?array $packages, string $clientVersion = '1.0.0', string $phpVersion = '8.2.29', ?array $domains = null, string $field = 'packages'): void
    {
        $payload = [
            'coreVersion' => $coreVersion,
            'phpVersion' => $phpVersion,
            'dbVersion' => '10.11.19-MariaDB',
            'dbPlatform' => 'mariadb',
            'clientVersion' => $clientVersion,
            'domains' => $domains,
        ];
        if (null !== $packages) {
            $payload[$field] = array_map(static fn (array $package): array => $package + ['title' => $package['name'], 'active' => true], $packages);
        }

        $client->request('POST', '/api/v1/sites/report', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<array{string, ?string, ?string, ?string, ?string}> type, subject, old, new, detail
     */
    private function events(Site $site): array
    {
        $events = static::getContainer()->get(SiteEventRepository::class)->search($site, null, null, 1, 100);

        return array_values(array_map(
            static fn (SiteEvent $event): array => [$event->getType()->value, $event->getSubject(), $event->getOldValue(), $event->getNewValue(), $event->getDetail()],
            array_reverse(iterator_to_array($events)),
        ));
    }

    /**
     * @return array{0: string, 1: Site}
     */
    private function createSite(): array
    {
        [$token, $hash] = new SiteTokenFactory()->generate();
        $site = new Site('History Site', CmsType::Typo3, 'https://example.test', $hash);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($site);
        $entityManager->flush();

        return [$token, $site];
    }
}
