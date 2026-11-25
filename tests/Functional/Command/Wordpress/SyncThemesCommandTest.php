<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command\Wordpress;

use App\Command\Wordpress\SyncThemesCommand;
use App\Entity\CmsType;
use App\Entity\PackageType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Repository\SitePackageRepository;
use App\Repository\WordpressThemeInfoRepository;
use App\Service\SecurityNotifier;
use App\Service\Wordpress\ApiClient;
use App\Service\Wordpress\SitePackageEvaluator;
use App\Service\Wordpress\ThemeSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class SyncThemesCommandTest extends KernelTestCase
{
    public function testRatesTheReportedThemesByTheirLatestVersion(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $site = new Site('Themes Site', CmsType::WordPress, 'https://themes.test', 'hash-themes');
        $site->recordReport('6.5.2', '8.3.0', '10.11.0');
        $entityManager->persist($site);
        foreach (['twentytwentyfour' => '1.0', 'needs-newer-wp' => '1.0', 'premium-theme' => '3.0'] as $slug => $version) {
            $entityManager->persist(new SitePackage($site, $slug, ucfirst($slug), $version, true, null, PackageType::WordpressTheme));
        }
        $entityManager->flush();

        $api = new MockHttpClient(static function (string $method, string $url): MockResponse {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            return match ($query['request']['slug'] ?? null) {
                'twentytwentyfour' => new JsonMockResponse(['slug' => 'twentytwentyfour', 'version' => '1.6', 'requires' => '6.4']),
                'needs-newer-wp' => new JsonMockResponse(['slug' => 'needs-newer-wp', 'version' => '2.0', 'requires' => '6.8']),
                default => new MockResponse('', ['http_code' => 404]),
            };
        });

        $command = new SyncThemesCommand(
            new ThemeSynchronizer(new ApiClient($api, new NullLogger()), $container->get(SitePackageRepository::class), $container->get(WordpressThemeInfoRepository::class), $entityManager),
            $container->get(SitePackageEvaluator::class),
            $container->get(SitePackageRepository::class),
            $entityManager,
            $container->get(SecurityNotifier::class),
        );

        $tester = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Synced 2 theme slug(s)', $tester->getDisplay());

        $packages = $container->get(SitePackageRepository::class);
        $current = $packages->findOneBySiteAndName($site, 'twentytwentyfour', PackageType::WordpressTheme);
        self::assertTrue($current?->isOutdated());
        self::assertSame('1.6', $current->getLatestVersion());

        // needs WordPress 6.8, the site runs 6.5.2: not outdated, but a hint at the newer WordPress
        $newer = $packages->findOneBySiteAndName($site, 'needs-newer-wp', PackageType::WordpressTheme);
        self::assertFalse($newer?->isOutdated());
        self::assertSame('6.8', $newer->getNewerCoreVersion());

        // unknown to WordPress.org
        self::assertNull($packages->findOneBySiteAndName($site, 'premium-theme', PackageType::WordpressTheme)?->isOutdated());
    }
}
