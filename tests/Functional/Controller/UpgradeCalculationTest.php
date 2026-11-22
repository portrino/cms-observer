<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Dto\UpgradeItem;
use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\Typo3CoreRelease;
use App\Entity\Typo3ReleaseType;
use App\Entity\Typo3TerExtensionVersion;
use App\Entity\UpgradeCalculation;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeSection;
use App\Entity\WordpressCoreRelease;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UpgradeCalculationTest extends WebTestCase
{
    use CreatesUserTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient([], ['HTTPS' => 'on']);
        $this->client->loginUser($this->createUser('upgrade@example.test', 'Upgrade Tester', 'password'));
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCreatesACalculationFromTheLatestReport(): void
    {
        $site = $this->typo3Site();

        $crawler = $this->client->request('GET', '/projects/'.$site->getId().'/calculations');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="target"] option[value="14"]');

        $crawler = $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=14');
        self::assertResponseIsSuccessful();
        self::assertInputValueSame('upgrade_calculation_version[packages][0][label]', 'georgringer/news');
        self::assertInputValueSame('upgrade_calculation_version[packages][0][package]', 'news');
        self::assertInputValueSame('upgrade_calculation_version[packages][0][targetVersion]', '14.0.0');
        self::assertInputValueSame('upgrade_calculation_version[hourlyRate]', '');

        $form = $crawler->selectButton('Save as new version')->form([
            'upgrade_calculation_version[positions][0][minHours]' => '4',
            'upgrade_calculation_version[positions][0][maxHours]' => '4',
            'upgrade_calculation_version[packages][0][minHours]' => '1',
            'upgrade_calculation_version[packages][0][maxHours]' => '2',
            'upgrade_calculation_version[packages][0][notes]' => 'Breaking changes, see changelog',
            'upgrade_calculation_version[hourlyRate]' => '100',
            'upgrade_calculation_version[managementPercent]' => '10',
            'upgrade_calculation_version[author]' => 'JD',
            'upgrade_calculation_version[note]' => 'First draft',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertSelectorTextContains('#upgradeTotals', '6.00–7.00 h');
        self::assertSelectorTextContains('#upgradeTotals', '600,00 – 700,00 EUR');
        self::assertSelectorTextContains('#upgrade-packages', 'Breaking changes, see changelog');
        self::assertSelectorTextContains('#upgradeVersions', 'JD');

        $version = $this->calculationOf($site)->getLatestVersion();
        self::assertNotNull($version);
        $news = $version->getItems(UpgradeSection::Packages)[0];
        self::assertSame(UpgradeItemStatus::UpdateFirst, $news->status);
        self::assertSame(2.0, $news->maxHours);
    }

    public function testSavingCreatesANewVersionAndKeepsTheOldOne(): void
    {
        $site = $this->typo3Site();
        $this->createCalculation($site, 'JD');
        $calculation = $this->calculationOf($site);
        $first = $calculation->getLatestVersion();

        $crawler = $this->client->request('GET', '/calculations/'.$calculation->getId().'/edit');
        self::assertInputValueSame('upgrade_calculation_version[hourlyRate]', '100.00');
        $this->client->submit($crawler->selectButton('Save as new version')->form([
            'upgrade_calculation_version[hourlyRate]' => '90',
            'upgrade_calculation_version[author]' => 'PM',
            'upgrade_calculation_version[final]' => true,
        ]));
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $calculation = $this->entityManager->find(UpgradeCalculation::class, $calculation->getId());
        self::assertCount(2, $calculation->getVersions());
        [$latest, $old] = $calculation->getVersions();
        self::assertSame(['PM', 90.0, true], [$latest->getAuthor(), $latest->getHourlyRate(), $latest->isFinal()]);
        self::assertSame(['JD', 100.0, false], [$old->getAuthor(), $old->getHourlyRate(), $old->isFinal()]);
        self::assertSame($first?->getId(), $old->getId());

        $this->client->request('GET', '/calculations/'.$calculation->getId().'?version='.$old->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.upgrade-calculation', 'Older version');
    }

    public function testAuthorAndRateAreRequired(): void
    {
        $site = $this->typo3Site();

        $crawler = $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=14');
        $this->client->submit($crawler->selectButton('Save as new version')->form([
            'upgrade_calculation_version[packages][0][minHours]' => '3',
            'upgrade_calculation_version[packages][0][maxHours]' => '2',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#upgrade-calculation-form', 'The maximum must not be below the minimum.');
        self::assertNull($this->calculationOf($site));
    }

    public function testASingleEffortCountsAsMinAndMax(): void
    {
        $site = $this->typo3Site();

        $crawler = $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=14');
        $this->client->submit($crawler->selectButton('Save as new version')->form([
            'upgrade_calculation_version[positions][0][minHours]' => '4',
            'upgrade_calculation_version[positions][1][maxHours]' => '2',
            'upgrade_calculation_version[hourlyRate]' => '100',
            'upgrade_calculation_version[managementPercent]' => '0',
            'upgrade_calculation_version[author]' => 'JD',
        ]));
        $this->client->followRedirect();

        self::assertSelectorTextContains('#upgradeTotals', '6.00 h');
        [$core, $php] = $this->calculationOf($site)?->getLatestVersion()?->getItems(UpgradeSection::Positions) ?? [];
        self::assertSame([4.0, 4.0, 2.0, 2.0], [$core->minHours, $core->maxHours, $php->minHours, $php->maxHours]);
    }

    public function testARowWithHoursButNoLabelIsReportedInsteadOfDropped(): void
    {
        $site = $this->typo3Site();

        $crawler = $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=14');
        $this->client->submit($crawler->selectButton('Save as new version')->form([
            'upgrade_calculation_version[positions][0][label]' => '',
            'upgrade_calculation_version[positions][0][maxHours]' => '4',
            'upgrade_calculation_version[hourlyRate]' => '100',
            'upgrade_calculation_version[managementPercent]' => '10',
            'upgrade_calculation_version[author]' => 'JD',
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->calculationOf($site));
    }

    public function testNewPackageRowsStartAsCheckManually(): void
    {
        $site = $this->typo3Site();

        $crawler = $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=14');

        self::assertStringContainsString('<option value="manual" selected>', $crawler->filter('#upgrade-packages + template')->html());
    }

    public function testRowsAreSavedInTheirMovedOrder(): void
    {
        $site = $this->typo3Site();

        $crawler = $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=14');
        $this->client->submit($crawler->selectButton('Save as new version')->form([
            'upgrade_calculation_version[positions][0][position]' => '2',
            'upgrade_calculation_version[positions][1][position]' => '0',
            'upgrade_calculation_version[positions][2][position]' => '1',
            'upgrade_calculation_version[hourlyRate]' => '100',
            'upgrade_calculation_version[managementPercent]' => '10',
            'upgrade_calculation_version[author]' => 'JD',
        ]));
        self::assertResponseRedirects();

        $labels = array_map(static fn (UpgradeItem $item): string => $item->label, $this->calculationOf($site)?->getLatestVersion()?->getItems(UpgradeSection::Positions) ?? []);
        self::assertSame(['PHP version upgrade', 'Testing, code reviews and deployments', 'Core upgrade'], $labels);
    }

    public function testUnknownTargetIsNotFound(): void
    {
        $site = $this->typo3Site();

        $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=12');

        self::assertResponseStatusCodeSame(404);
    }

    public function testWordpressProjectsCanBeCalculatedToo(): void
    {
        $site = new Site('WP Upgrade', CmsType::WordPress, 'https://wp-upgrade.test', 'hash-wp-upgrade');
        $site->recordReport('6.7.1', '8.3.14', '10.11.19-MariaDB');
        $this->entityManager->persist($site);
        $this->entityManager->persist(new SitePackage($site, 'akismet', 'Akismet', '5.3', true));
        $this->entityManager->persist(new WordpressCoreRelease('7.0', '7.0.1', false, null));
        $this->entityManager->flush();

        $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=7');

        self::assertResponseIsSuccessful();
        self::assertInputValueSame('upgrade_calculation_version[packages][0][package]', 'akismet');
        self::assertSelectorExists('select[name="upgrade_calculation_version[packages][0][status]"] option[value="manual"][selected]');
    }

    public function testDeletingAProjectDeletesItsCalculations(): void
    {
        $site = $this->typo3Site();
        $this->createCalculation($site, 'JD');
        $siteId = $site->getId();

        $crawler = $this->client->request('GET', '/projects/'.$siteId.'/edit');
        $this->client->request('POST', '/projects/'.$siteId.'/delete', [
            'confirmation' => 'Upgrade Project',
            '_token' => (string) $crawler->filter('form[action$="/delete"] input[name="_token"]')->attr('value'),
        ]);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        self::assertSame(0, $this->entityManager->getRepository(UpgradeCalculation::class)->count([]));
    }

    private function typo3Site(): Site
    {
        $site = new Site('Upgrade Project', CmsType::Typo3, 'https://upgrade.test', 'hash-upgrade');
        $site->recordReport('13.4.20', '8.3.14', '10.11.19-MariaDB');
        $this->entityManager->persist($site);
        $this->entityManager->persist(new SitePackage($site, 'news', 'News', '12.3.1', true, 'georgringer/news'));
        $this->entityManager->persist(new Typo3CoreRelease('14.0.0', new \DateTimeImmutable(), Typo3ReleaseType::Regular, '14.0.0', true, null, false));
        foreach (['12.3.1' => '12, 13', '14.0.0' => '13, 14'] as $version => $majors) {
            $this->entityManager->persist(new Typo3TerExtensionVersion('news', $version, 'News', null, null, 'stable', $majors, null, false));
        }
        $this->entityManager->flush();

        return $site;
    }

    private function createCalculation(Site $site, string $author): void
    {
        $crawler = $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=14');
        $this->client->submit($crawler->selectButton('Save as new version')->form([
            'upgrade_calculation_version[hourlyRate]' => '100',
            'upgrade_calculation_version[managementPercent]' => '10',
            'upgrade_calculation_version[author]' => $author,
        ]));
        self::assertResponseRedirects();
    }

    private function calculationOf(Site $site): ?UpgradeCalculation
    {
        return $this->entityManager->getRepository(UpgradeCalculation::class)->findOneBy(['site' => $site]);
    }
}
