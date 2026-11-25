<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Wordpress;

use App\Entity\CmsType;
use App\Entity\PackageType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\Wordpress\PluginVersion;
use App\Repository\SitePackageRepository;
use App\Repository\Wordpress\PluginInfoRepository;
use App\Repository\Wordpress\PluginVersionRepository;
use App\Service\Wordpress\ApiClient;
use App\Service\Wordpress\PluginSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class PluginSynchronizerTest extends KernelTestCase
{
    private const string SVN = 'https://plugins.svn.wordpress.org/sync-plugin/tags/';

    public function testStoresTheNewerVersionsWithTheirRequiredWordpressVersion(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $site = new Site('Plugin Site', CmsType::WordPress, 'https://plugins.test', 'hash-plugins');
        $entityManager->persist($site);
        $entityManager->persist(new SitePackage($site, 'sync-plugin', 'Sync Plugin', '1.0', true, null, PackageType::WordpressPlugin));
        // already cached: never fetched again
        $entityManager->persist(new PluginVersion('sync-plugin', '1.2', null, '6.3'));
        $entityManager->flush();

        $requested = [];
        $api = new MockHttpClient(static function (string $method, string $url) use (&$requested): MockResponse {
            $requested[] = $method.' '.$url;

            return match (true) {
                str_contains($url, 'slug=sync-plugin') => new JsonMockResponse(['version' => '1.3', 'requires' => '6.4']),
                'PROPFIND' === $method && self::SVN === $url => new MockResponse(self::tagListing(['0.9' => '2023-06-01', '1.0' => '2023-09-01', '1.1' => '2024-01-11', '1.2' => '2024-02-01', '1.3' => '2024-03-01'])),
                // not named after the slug: found by listing the tag and probing its .php files
                self::SVN.'1.3/' === $url => new MockResponse('<a href="readme.txt">readme.txt</a> <a href="main.php">main.php</a>'),
                self::SVN.'1.3/main.php' === $url => new MockResponse("<?php\n/*\n * Plugin Name: Sync Plugin\n * Requires at least: 6.4\n */"),
                self::SVN.'1.1/main.php' === $url => new MockResponse("<?php\n/*\n * Plugin Name: Sync Plugin\n * Requires at least: 6.2\n */"),
                default => new MockResponse('', ['http_code' => 404]),
            };
        });

        $synchronizer = new PluginSynchronizer(
            new ApiClient($api, new NullLogger()),
            $container->get(SitePackageRepository::class),
            $container->get(PluginInfoRepository::class),
            $container->get(PluginVersionRepository::class),
            $entityManager,
        );

        self::assertSame(1, $synchronizer->sync());

        $info = $container->get(PluginInfoRepository::class)->findOneBySlug('sync-plugin');
        self::assertSame('main.php', $info?->getBootstrapFilename());
        self::assertSame('1.3', $info->getLatestVersion());

        $versions = $container->get(PluginVersionRepository::class);
        self::assertNull($versions->findOneBySlugAndVersion('sync-plugin', '0.9'), 'older than the installed version');
        self::assertNull($versions->findOneBySlugAndVersion('sync-plugin', '1.0'), 'the installed version itself');
        self::assertSame('6.2', $versions->findOneBySlugAndVersion('sync-plugin', '1.1')?->getRequiresWpVersion());
        self::assertSame('2024-01-11', $versions->findOneBySlugAndVersion('sync-plugin', '1.1')->getReleaseDate()?->format('Y-m-d'));
        self::assertSame('6.4', $versions->findOneBySlugAndVersion('sync-plugin', '1.3')?->getRequiresWpVersion());
        self::assertNotContains('GET '.self::SVN.'1.2/main.php', $requested);
    }

    /**
     * @param array<string, string> $datesByVersion
     */
    private static function tagListing(array $datesByVersion): string
    {
        $entries = '<D:response><D:href>/sync-plugin/tags/</D:href><D:propstat><D:prop><D:getlastmodified>Fri, 01 Mar 2024 10:00:00 GMT</D:getlastmodified></D:prop></D:propstat></D:response>';
        foreach ($datesByVersion as $version => $date) {
            $entries .= sprintf('<D:response><D:href>/sync-plugin/tags/%s/</D:href><D:propstat><D:prop><D:getlastmodified>%s 10:00:00 GMT</D:getlastmodified></D:prop></D:propstat></D:response>', $version, $date);
        }

        return '<?xml version="1.0" encoding="utf-8"?><D:multistatus xmlns:D="DAV:">'.$entries.'</D:multistatus>';
    }
}
