<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Craft;

use App\Entity\CmsType;
use App\Entity\Craft\Advisory;
use App\Entity\Craft\PluginVersion;
use App\Entity\EndOfLifeRelease;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Repository\Craft\AdvisoryRepository;
use App\Repository\Craft\CoreReleaseRepository;
use App\Repository\Craft\PluginVersionRepository;
use App\Repository\EndOfLifeReleaseRepository;
use App\Repository\SitePackageRepository;
use App\Service\Craft\AdvisorySynchronizer;
use App\Service\Craft\CoreReleaseSynchronizer;
use App\Service\Craft\PackagistApiClient;
use App\Service\Craft\PluginVersionSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class CoreReleaseSynchronizerTest extends KernelTestCase
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $advisories = [];

    public function testRatesEachReleaseByItsMajorAndTheAdvisories(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new EndOfLifeRelease('craft-cms', '4', true, '4.18.9', 'https://endoflife.date/craft-cms'));
        $entityManager->persist(new EndOfLifeRelease('craft-cms', '5', false, '5.12.4', 'https://endoflife.date/craft-cms'));
        $entityManager->flush();
        $this->advisories = ['craftcms/cms' => [self::advisory('PKSA-rce', '>=5.0.0-RC1,<5.10.5')]];

        [$advisorySynchronizer, $coreSynchronizer] = $this->synchronizers();
        $advisorySynchronizer->sync();
        self::assertSame(4, $coreSynchronizer->sync());

        $releases = static::getContainer()->get(CoreReleaseRepository::class);
        self::assertTrue($releases->findOneByVersion('5.10.4')?->isInsecure(), 'affected by the advisory');
        self::assertFalse($releases->findOneByVersion('5.10.6')?->isInsecure());
        self::assertTrue($releases->findOneByVersion('4.18.9')?->isInsecure(), 'major past end of life');
        self::assertSame('5.12.4', $releases->findOneByVersion('5.10.6')->getLatestVersion());
        self::assertSame('4.18.9', $releases->findOneByVersion('4.18.9')->getLatestVersion(), 'latest of its own major');
        self::assertNull($releases->findOneByVersion('6.0.0-beta.2'), 'pre-releases are left out');
    }

    public function testStoresThePluginAdvisoriesAndDropsWithdrawnOnes(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $site = new Site('Craft', CmsType::Craft, 'https://craft.test', 'hash-craft-sync');
        $entityManager->persist($site);
        $entityManager->persist(new SitePackage($site, 'seomatic', 'SEOmatic', '3.4.10', true, 'nystudio107/craft-seomatic'));
        $entityManager->flush();
        [$advisorySynchronizer] = $this->synchronizers();

        $this->advisories = ['nystudio107/craft-seomatic' => [self::advisory('PKSA-seo-1', '<3.4.11'), self::advisory('PKSA-seo-2', '<3.2.49')]];
        self::assertSame(2, $advisorySynchronizer->sync());

        $this->advisories = ['nystudio107/craft-seomatic' => [self::advisory('PKSA-seo-1', '<3.4.12')]];
        $advisorySynchronizer->sync();

        $stored = static::getContainer()->get(AdvisoryRepository::class)->findByPackageName('nystudio107/craft-seomatic');
        self::assertSame(['PKSA-seo-1'], array_map(static fn (Advisory $a): string => $a->getAdvisoryId(), $stored));
        self::assertSame('<3.4.12', $stored[0]->getAffectedVersions());
    }

    public function testSyncsThePackagistVersionsOfTheReportedPlugins(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $site = new Site('Craft', CmsType::Craft, 'https://craft.test', 'hash-craft-versions');
        $entityManager->persist($site);
        $entityManager->persist(new SitePackage($site, 'seomatic', 'SEOmatic', '5.1.23', true, 'nystudio107/craft-seomatic'));
        $entityManager->persist(new SitePackage($site, 'private', 'Private', '1.0.0', true, 'acme/private-plugin'));
        $entityManager->flush();
        $http = new MockHttpClient(static fn (string $method, string $url): MockResponse => str_contains($url, 'craft-seomatic')
            ? new MockResponse((string) file_get_contents(__DIR__.'/../../../Fixtures/Packagist/craft-seomatic.json'))
            : new MockResponse('', ['http_code' => 404]));
        $container = static::getContainer();
        $synchronizer = new PluginVersionSynchronizer(new PackagistApiClient($http, 'https://observer.test', ''), $container->get(PluginVersionRepository::class), $container->get(SitePackageRepository::class), $entityManager);

        self::assertSame(1, $synchronizer->sync(), 'the private plugin is unknown to Packagist');
        self::assertSame(1, $synchronizer->sync(), 'a second run updates instead of duplicating');

        $newer = $container->get(PluginVersionRepository::class)->findNewerThan('nystudio107/craft-seomatic', '5.1.23');
        self::assertSame(['5.1.24'], array_map(static fn (PluginVersion $v): string => $v->getVersion(), $newer ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    private static function advisory(string $id, string $affected): array
    {
        return ['advisoryId' => $id, 'title' => 'Title of '.$id, 'link' => 'https://github.com/advisories/'.$id, 'affectedVersions' => $affected, 'cve' => null, 'severity' => 'high'];
    }

    /**
     * @return array{AdvisorySynchronizer, CoreReleaseSynchronizer}
     */
    private function synchronizers(): array
    {
        $versions = ['packages' => ['craftcms/cms' => [
            ['version' => '6.0.0-beta.2', 'time' => '2026-11-20T21:57:53+00:00', 'require' => ['php' => '^8.4']],
            ['version' => '5.12.4', 'time' => '2026-11-24T10:00:00+00:00', 'require' => ['php' => '^8.2']],
            ['version' => '5.10.6'],
            ['version' => '5.10.4'],
            ['version' => '4.18.9', 'require' => ['php' => '^8.0.2']],
        ]], 'minified' => 'composer/2.0'];
        $http = new MockHttpClient(fn (string $method, string $url): JsonMockResponse => str_contains($url, '/p2/')
            ? new JsonMockResponse($versions)
            : new JsonMockResponse(['advisories' => $this->advisories]));

        $container = static::getContainer();
        $api = new PackagistApiClient($http, 'https://observer.test', '');
        $entityManager = $container->get(EntityManagerInterface::class);
        $core = new CoreReleaseSynchronizer($api, $container->get(CoreReleaseRepository::class), $container->get(AdvisoryRepository::class), $container->get(EndOfLifeReleaseRepository::class), $entityManager);

        return [new AdvisorySynchronizer($api, $container->get(AdvisoryRepository::class), $container->get(SitePackageRepository::class), $core, $entityManager), $core];
    }
}
