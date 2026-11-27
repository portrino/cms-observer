<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Typo3;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\Typo3\CoreRelease;
use App\Entity\Typo3\PackagistVersion;
use App\Entity\Typo3\ReleaseType;
use App\Entity\Typo3\TerExtensionMeta;
use App\Repository\SitePackageRepository;
use App\Repository\Typo3\CoreReleaseRepository;
use App\Repository\Typo3\PackagistVersionRepository;
use App\Service\PackagistApiClient;
use App\Service\Typo3\ExtensionVersionCatalog;
use App\Service\Typo3\PackagistVersionSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class PackagistVersionSynchronizerTest extends KernelTestCase
{
    public function testSyncsTheVersionsWithTheTypo3MajorsTheyInstallOn(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $site = new Site('TYPO3', CmsType::Typo3, 'https://typo3.test', 'hash-typo3-packagist');
        $entityManager->persist($site);
        $entityManager->persist(new SitePackage($site, 'http2', 'HTTP/2', '3.0.0', true, 'b13/http2'));
        $entityManager->persist(new SitePackage($site, 'private', 'Private', '1.0.0', true, 'acme/private'));
        // an older client reports no Composer name, the TER's Packagist link has it
        $entityManager->persist(new SitePackage($site, 'legacy_ext', 'Legacy', '1.0.0', true));
        $entityManager->persist(new TerExtensionMeta('legacy_ext', 'https://packagist.org/packages/vendor/legacy-ext', null, null));
        foreach ([12, 13, 14] as $major) {
            $entityManager->persist(new CoreRelease("{$major}.4.0", new \DateTimeImmutable(), ReleaseType::Regular, "{$major}.4.0", true, false, null));
        }
        $entityManager->flush();

        // minified like the real p2 files: 4.0.0 inherits the "require" of 4.1.0
        $http = new MockHttpClient(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, 'vendor/legacy-ext') => new JsonMockResponse(['packages' => ['vendor/legacy-ext' => [['version' => '1.1.0', 'require' => ['typo3/cms-core' => '^13.4']]]]]),
            str_contains($url, 'b13/http2') => new JsonMockResponse(['minified' => 'composer/2.0', 'packages' => ['b13/http2' => [
                ['name' => 'b13/http2', 'version' => '4.1.0', 'time' => '2026-05-22T10:00:00+00:00', 'require' => ['php' => '^8.2', 'typo3/cms-core' => '^14.3']],
                ['version' => '4.0.0', 'time' => '2026-04-30T10:00:00+00:00'],
                ['version' => '3.0.0', 'require' => ['typo3/cms-frontend' => '^12.4 || ^13.4', 'typo3/cms-composer-installers' => '^5.0']],
                ['version' => '2.0.0', 'require' => ['php' => '^7.4']],
                ['version' => '4.2.0-beta1'],
            ]]]),
            default => new MockResponse('', ['http_code' => 404]),
        });
        $container = static::getContainer();
        $synchronizer = new PackagistVersionSynchronizer(
            new PackagistApiClient($http, 'https://observer.test', ''),
            $container->get(PackagistVersionRepository::class),
            $container->get(SitePackageRepository::class),
            $entityManager,
            $container->get(CoreReleaseRepository::class),
            $container->get(ExtensionVersionCatalog::class),
        );

        self::assertSame(2, $synchronizer->sync(), 'the private package is unknown to Packagist');
        self::assertSame(2, $synchronizer->sync(), 'a second run updates instead of duplicating');
        self::assertSame([13], $container->get(PackagistVersionRepository::class)->findByPackageName('vendor/legacy-ext')[0]->getTypo3Majors());

        $majors = [];
        foreach ($container->get(PackagistVersionRepository::class)->findByPackageName('b13/http2') as $version) {
            $majors[$version->getVersion()] = $version->getTypo3Majors();
        }
        ksort($majors);
        self::assertSame(['2.0.0' => null, '3.0.0' => [12, 13], '4.0.0' => [14], '4.1.0' => [14]], $majors, 'no pre-release; no core package says nothing');
        self::assertInstanceOf(PackagistVersion::class, $container->get(PackagistVersionRepository::class)->findNewerThan('b13/http2', '3.0.0')[0] ?? null);
    }
}
