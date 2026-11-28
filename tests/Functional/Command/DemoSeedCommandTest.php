<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SiteEvent;
use App\Entity\SitePackage;
use App\Entity\Typo3\CoreRelease;
use App\Entity\Typo3\ReleaseType;
use App\Entity\UpgradeCalculation;
use App\Repository\AgencySettingsRepository;
use App\Service\Demo\DemoProjects;
use App\Service\Demo\DemoSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;

class DemoSeedCommandTest extends KernelTestCase
{
    public function testSeedsTheDemoProjectsWithTwoReportsEach(): void
    {
        $kernel = self::bootKernel();
        $tester = new CommandTester(new Application($kernel)->find('app:demo:seed'));

        // other tests' sites may be there: forced, as on a developer's database
        self::assertSame(Command::SUCCESS, $tester->execute(['--force' => true]));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $site = $entityManager->getRepository(Site::class)->findOneBy(['name' => 'Bergblick Tourismus – Shop']);
        self::assertNotNull($site);
        self::assertSame('6.7.2', $site->getCurrentCoreVersion(), 'the current report');
        self::assertSame('Bergblick Tourismus GmbH', $site->getOrganization()?->getName());
        self::assertSame('https://bergblick-tourismus-shop.example.test', $site->getUrl());
        self::assertCount(9, $entityManager->getRepository(SitePackage::class)->findBy(['site' => $site]), '5–10 packages per project');
        $events = array_map(static fn (SiteEvent $event): string => $event->getType()->value, $entityManager->getRepository(SiteEvent::class)->findBy(['site' => $site]));
        self::assertContains('package-updated', $events, 'WooCommerce 9.6.1 → 9.6.2 between the reports');
        self::assertStringContainsString('--sync', $tester->getDisplay(), 'rated once synced');
        self::assertSame(100.0, static::getContainer()->get(AgencySettingsRepository::class)->get()->getDefaultHourlyRate(), 'rates for the calculations');
    }

    public function testDoesNothingOnAnObserverWithProjectsUnlessForced(): void
    {
        $kernel = self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new Site('Real project', CmsType::Typo3, 'https://real.test', 'hash-real-project'));
        $entityManager->flush();
        $tester = new CommandTester(new Application($kernel)->find('app:demo:seed'));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertNull($entityManager->getRepository(Site::class)->findOneBy(['name' => DemoProjects::CALCULATION_PROJECT]));
    }

    public function testDraftsTheDemoCalculationAndAReportToRefreshItWith(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach (['13.4.10', '14.0.0'] as $version) {
            $entityManager->persist(new CoreRelease($version, new \DateTimeImmutable(), ReleaseType::Regular, $version, true, false, null));
        }
        $entityManager->flush();
        $seeder = static::getContainer()->get(DemoSeeder::class);
        $seeder->seedProjects();

        self::assertTrue($seeder->seedCalculation());

        $site = $entityManager->getRepository(Site::class)->findOneBy(['name' => DemoProjects::CALCULATION_PROJECT]);
        $calculation = $entityManager->getRepository(UpgradeCalculation::class)->findOneBy(['site' => $site]);
        self::assertSame([13, 14], [$calculation?->getFromMajor(), $calculation?->getTargetMajor()]);
        self::assertSame('Demo', $calculation?->getLatestVersion()?->getAuthor());
        $news = $entityManager->getRepository(SitePackage::class)->findOneBy(['site' => $site, 'name' => 'news']);
        self::assertSame('14.0.0', $news?->getVersion(), 'updated after the calculation, for its refresh');
    }

    public function testOfflineRatesTheDemoProjectsFromTheSnapshotWithoutAnyRequest(): void
    {
        $kernel = self::bootKernel();
        static::getContainer()->set('http_client', new MockHttpClient(static fn (string $method, string $url): never => throw new \LogicException('Requested '.$url)));
        $tester = new CommandTester(new Application($kernel)->find('app:demo:seed'));

        self::assertSame(Command::SUCCESS, $tester->execute(['--offline' => true, '--force' => true]), $tester->getDisplay());

        $sites = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Site::class);
        $magazine = $sites->findOneBy(['name' => 'Nordlicht Verlag – Magazin']);
        self::assertSame([false, false], [$magazine?->isCoreInsecure(), $magazine?->isPhpInsecure()], 'the up-to-date project');
        $portal = $sites->findOneBy(['name' => 'Stadtwerke Musterstadt – Kundenportal']);
        self::assertSame([true, true], [$portal?->isCoreInsecure(), $portal?->isPhpInsecure()], 'TYPO3 12.4.20 on PHP 8.1.31');
        $packages = static::getContainer()->get(EntityManagerInterface::class)->getRepository(SitePackage::class);
        self::assertTrue($packages->findOneBy(['site' => $portal, 'name' => 'news'])?->isInsecure(), 'news 11.4.2, rated against its TER versions');
        self::assertTrue($packages->findOneBy(['site' => $sites->findOneBy(['name' => 'Bergblick Tourismus – Shop']), 'name' => 'woocommerce'])?->isInsecure(), 'a Wordfence vulnerability');
        self::assertStringContainsString('Drafted an upgrade calculation', $tester->getDisplay());
    }

    public function testOfflineRefusesAnObserverWithSyncedReleaseData(): void
    {
        $kernel = self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new CoreRelease('13.4.10', new \DateTimeImmutable(), ReleaseType::Regular, '13.4.10', true, false, null));
        $entityManager->flush();
        $tester = new CommandTester(new Application($kernel)->find('app:demo:seed'));

        self::assertSame(Command::FAILURE, $tester->execute(['--offline' => true, '--force' => true]));
        self::assertStringContainsString('--sync instead', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->execute(['--offline' => true, '--sync' => true, '--force' => true]), 'one or the other');
        self::assertNull($entityManager->getRepository(Site::class)->findOneBy(['name' => DemoProjects::CALCULATION_PROJECT]));
    }
}
