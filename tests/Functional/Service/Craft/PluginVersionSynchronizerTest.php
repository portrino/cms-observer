<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Craft;

use App\Entity\CmsType;
use App\Entity\Craft\PluginVersion;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Repository\Craft\PluginVersionRepository;
use App\Repository\SitePackageRepository;
use App\Service\Craft\PackagistApiClient;
use App\Service\Craft\PluginVersionSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PluginVersionSynchronizerTest extends KernelTestCase
{
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
}
