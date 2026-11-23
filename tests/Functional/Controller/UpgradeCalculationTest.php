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
        self::assertSelectorNotExists('#upgradePricing .accordion-button.collapsed', 'pricing is open while still to fill in');

        $form = $crawler->selectButton('Save as new version')->form([
            'upgrade_calculation_version[positions][0][minHours]' => '4',
            'upgrade_calculation_version[positions][0][maxHours]' => '4',
            'upgrade_calculation_version[packages][0][minHours]' => '1',
            'upgrade_calculation_version[packages][0][maxHours]' => '2',
            'upgrade_calculation_version[packages][0][notes]' => 'Breaking changes, see changelog',
            'upgrade_calculation_version[packages][0][noteLevel]' => 'warning',
            'upgrade_calculation_version[hourlyRate]' => '100',
            'upgrade_calculation_version[managementPercent]' => '10',
            'upgrade_calculation_version[author]' => 'JD',
            'upgrade_calculation_version[note]' => 'First draft',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertSelectorTextContains('#upgradeTotals', '7.00 h', 'sums with the maximum effort');
        self::assertSelectorTextContains('#upgradeTotals', '€700.00');
        self::assertSelectorTextContains('#upgrade-packages td span.no-print', '1.00–2.00 h', 'the range of a row as a hint on screen');
        self::assertSelectorTextSame('#upgrade-packages td span.print-only', '2.00 h');
        self::assertSelectorTextContains('#upgrade-packages td.table-warning', 'Breaking changes, see changelog');
        self::assertSelectorTextContains('#upgrade-packages td.table-info', '14.0.0', 'a target for both majors is highlighted');
        self::assertSelectorTextContains('#upgradeBeforehand', '2.00 h');
        self::assertSelectorTextContains('#upgrade-positions tr.upgrade-sum-row', 'Third-party packages', 'the package section among the positions');
        self::assertSelectorTextContains('#upgrade-positions tr.upgrade-sum-row', '€200.00');
        self::assertSelectorTextContains('#upgradeChangelog', 'First draft');
        self::assertSelectorTextContains('#upgradeVersions', 'JD');
        self::assertSelectorTextSame('.topbar-title', 'Upgrade TYPO3 13 → 14 Upgrade Project', 'names the upgrade and the project');

        // the same calculation in German: texts, the sum row's label and the currency format follow the reader
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'de');
        $this->client->request('GET', $this->client->getRequest()->getUri());
        self::assertSelectorTextContains('#upgradeTotals', "700,00\u{a0}€");
        self::assertSelectorTextContains('#upgradeTotals', 'Projektmanagement (10 %)');
        self::assertSelectorTextContains('#upgrade-positions tr.upgrade-sum-row', 'Drittanbieter-Pakete');
        self::assertDoesNotMatchRegularExpression('/\bcalculation\.[a-z_.-]+/', (string) $this->client->getResponse()->getContent(), 'untranslated key');
        $this->client->clickLink('Bearbeiten');
        self::assertSelectorTextContains('#upgrade-calculation-form', 'Als neue Version speichern');
        self::assertSelectorExists('#upgrade-positions input[value="Drittanbieter-Pakete"]', 'the sum row is named in the reader\'s language');
        self::assertDoesNotMatchRegularExpression('/\bcalculation\.[a-z_.-]+/', (string) $this->client->getResponse()->getContent(), 'untranslated key on the edit page');

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
        self::assertSelectorTextSame('.topbar-title', 'Edit calculation: Upgrade TYPO3 13 → 14 Upgrade Project');
        self::assertInputValueSame('upgrade_calculation_version[hourlyRate]', '100.00');
        self::assertSelectorTextContains('#upgradePricing .accordion-button.collapsed', '100.00 EUR/h, project management 10 %');
        $this->client->submit($crawler->selectButton('Save as new version')->form([
            'upgrade_calculation_version[hourlyRate]' => '90',
            'upgrade_calculation_version[author]' => 'PM',
            'upgrade_calculation_version[final]' => true,
        ]));
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $calculation = $this->entityManager->find(UpgradeCalculation::class, $calculation->getId());
        self::assertCount(2, $calculation->getVersions());

        $this->client->request('GET', '/projects/'.$site->getId().'/calculations');
        self::assertSelectorTextSame('#upgradeCalculations .badge-count[title="Saved versions"]', '2');

        $this->client->request('GET', '/calculations/'.$calculation->getId());
        $this->client->clickLink('Compare with previous');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#compareSettings', 'Hourly rate (EUR): 100.00 → 90.00');
        self::assertSelectorTextContains('#compareSettings', 'Final version: no → yes');

        $this->client->request('GET', '/calculations/'.$calculation->getId().'/compare?to='.$first?->getId());
        self::assertResponseStatusCodeSame(404, 'the first version has no previous one');
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
            'upgrade_calculation_version[positions][0][position]' => '4',
            'upgrade_calculation_version[positions][1][position]' => '0',
            'upgrade_calculation_version[positions][2][position]' => '1',
            'upgrade_calculation_version[positions][3][position]' => '2',
            'upgrade_calculation_version[positions][4][position]' => '3',
            'upgrade_calculation_version[hourlyRate]' => '100',
            'upgrade_calculation_version[managementPercent]' => '10',
            'upgrade_calculation_version[author]' => 'JD',
        ]));
        self::assertResponseRedirects();

        $labels = array_map(static fn (UpgradeItem $item): string => $item->label, $this->calculationOf($site)?->getLatestVersion()?->getItems(UpgradeSection::Positions) ?? []);
        self::assertSame(['PHP version upgrade', 'Third-party packages', 'Custom packages', 'Testing, code reviews and deployments', 'Core upgrade'], $labels);
    }

    public function testRateComesFromTheProjectsLastCalculationElseFromTheSettings(): void
    {
        $crawler = $this->client->request('GET', '/settings');
        self::assertSelectorExists('.sidebar-user a[href="/settings"]', 'in the user menu');
        $this->client->submit($crawler->selectButton('Save')->form([
            'agency_settings[defaultHourlyRate]' => '95',
            'agency_settings[defaultManagementPercent]' => '12',
        ]));
        self::assertResponseRedirects('/settings');

        $site = $this->typo3Site();
        $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=14');
        self::assertInputValueSame('upgrade_calculation_version[hourlyRate]', '95.00');
        self::assertInputValueSame('upgrade_calculation_version[managementPercent]', '12');
        self::assertSelectorExists('#upgradePricing .accordion-button.collapsed', 'prefilled pricing is collapsed');

        $this->createCalculation($site, 'JD');
        $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=14');
        self::assertInputValueSame('upgrade_calculation_version[hourlyRate]', '100.00');
    }

    public function testShowsTheTimelineOfTheUpgrade(): void
    {
        $site = $this->typo3Site();
        $crawler = $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=14');
        $this->client->submit($crawler->selectButton('Save as new version')->form([
            'upgrade_calculation_version[headerNotes]' => 'Scheduled for March',
            'upgrade_calculation_version[hourlyRate]' => '100',
            'upgrade_calculation_version[managementPercent]' => '10',
            'upgrade_calculation_version[author]' => 'PM',
        ]));
        $this->client->followRedirect();

        self::assertSelectorTextContains('#upgradeTimeline', 'TYPO3 13 receives free core updates until December 2027.');
        self::assertSelectorTextContains('#upgradeTimeline', 'From January 2028 on, core updates need a paid extended support (ELTS) license, available until December 2030.');
        self::assertSelectorTextContains('#upgradeTimeline', 'TYPO3 14 receives free core updates until June 2029.');
        self::assertSelectorExists('#upgradeTimeline svg rect.upgrade-roadmap-extended');
        self::assertSelectorTextContains('#upgradeTimeline svg rect.upgrade-roadmap-development title', 'until Oct 15, 2024', 'sprint releases until the LTS');
        self::assertSelectorTextContains('#upgradeTimeline svg rect.upgrade-roadmap-regular title', 'until Jul 1, 2026', 'priority bugfixes: the last 1.5 years');
        self::assertSelectorExists('#upgradeTimeline svg line.upgrade-roadmap-tick');
        self::assertSelectorTextContains('#upgradeHeaderNotes', 'Scheduled for March', 'in the head, below the changelog');

        $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=14');
        self::assertSelectorTextSame('textarea[name="upgrade_calculation_version[headerNotes]"]', '', 'the schedule belongs to the other calculation');
    }

    public function testNotesAreRenderedAsSafeMarkdown(): void
    {
        $site = $this->typo3Site();
        $crawler = $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=14');
        $this->client->submit($crawler->selectButton('Save as new version')->form([
            'upgrade_calculation_version[packages][0][notes]' => "**Breaking**\n- one\n- two\n<script>alert(1)</script>\n\n[x](javascript:alert(1)) [ok](https://typo3.org)",
            'upgrade_calculation_version[remarks]' => "License costs\nnot included",
            'upgrade_calculation_version[hourlyRate]' => '100',
            'upgrade_calculation_version[managementPercent]' => '10',
            'upgrade_calculation_version[author]' => 'PM',
        ]));
        $crawler = $this->client->followRedirect();

        $notes = $crawler->filter('#upgrade-packages td.upgrade-notes')->html();
        self::assertStringContainsString('<strong>Breaking</strong>', $notes);
        self::assertStringContainsString('<li>two</li>', $notes);
        self::assertStringNotContainsString('<script>', $notes, 'HTML is escaped');
        self::assertStringNotContainsString('href="javascript:', $notes, 'unsafe links are removed');
        self::assertStringContainsString('<a href="https://typo3.org">ok</a>', $notes);
        self::assertStringContainsString("License costs<br>\nnot included", $crawler->filter('.upgrade-calculation .card-footer')->html(), 'a line break stays one');
    }

    public function testRefreshBringsThePackagesUpToTheLatestReport(): void
    {
        $site = $this->typo3Site();
        $this->entityManager->persist(new SitePackage($site, 'powermail', 'Powermail', '10.0.0', true, 'in2code/powermail'));
        $this->entityManager->persist(new SitePackage($site, 'gone_ext', 'Gone', '1.0.0', true));
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/projects/'.$site->getId().'/calculations/new?target=14');
        $this->client->submit($crawler->selectButton('Save as new version')->form([
            'upgrade_calculation_version[packages][0][maxHours]' => '3',
            'upgrade_calculation_version[packages][0][notes]' => 'See changelog',
            'upgrade_calculation_version[own-packages][1][status]' => 'replace', // powermail, after gone_ext; not in TER here
            'upgrade_calculation_version[hourlyRate]' => '100',
            'upgrade_calculation_version[managementPercent]' => '10',
            'upgrade_calculation_version[author]' => 'JD',
        ]));
        $calculation = $this->calculationOf($site);
        self::assertNotNull($calculation);

        // later report: news updated, one extension gone, a new one installed
        $packages = $this->entityManager->getRepository(SitePackage::class);
        $packages->findOneBy(['site' => $site->getId(), 'name' => 'news'])?->update('News', '14.0.0', true, 'georgringer/news');
        $this->entityManager->remove($packages->findOneBy(['site' => $site->getId(), 'name' => 'gone_ext']) ?? throw new \LogicException());
        $this->entityManager->persist(new SitePackage($this->entityManager->find(Site::class, $site->getId()) ?? throw new \LogicException(), 'new_ext', 'New', '2.0.0', true));
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/calculations/'.$calculation->getId().'/refresh');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.topbar-title', 'Refresh calculation: Upgrade TYPO3 13 → 14');
        $changes = $crawler->filter('#upgradeRefreshChanges')->text();
        self::assertStringContainsString('georgringer/news: installed 12.3.1 → 14.0.0, to-do Update before core → Compatible', $changes);
        self::assertStringContainsString('to-do Update before core → Compatible', $changes);
        self::assertStringContainsString('gone_ext: not installed anymore, left out', $changes);
        self::assertStringContainsString('new_ext: newly installed, added', $changes);
        self::assertStringNotContainsString('powermail', $changes, 'the "Replace" decision is kept');

        $this->client->submit($crawler->selectButton('Save as new version')->form(['upgrade_calculation_version[author]' => 'PM']));
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $latest = $this->entityManager->find(UpgradeCalculation::class, $calculation->getId())?->getLatestVersion();
        self::assertStringStartsWith('Refreshed from the', (string) $latest?->getNote());
        $packages = [];
        foreach ($latest?->getItems() ?? [] as $item) {
            if (null !== $item->package) {
                $packages[$item->package] = [$item->installedVersion, $item->status, $item->maxHours, $item->notes];
            }
        }
        self::assertSame(['14.0.0', UpgradeItemStatus::Compatible, 3.0, 'See changelog'], $packages['news'], 'hours and notes kept');
        self::assertSame(UpgradeItemStatus::Replace, $packages['powermail'][1]);
        self::assertArrayNotHasKey('gone_ext', $packages);
        self::assertSame(UpgradeItemStatus::Manual, $packages['new_ext'][1]);
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

        $crawler = $this->client->getCrawler();
        $this->client->submit($crawler->selectButton('Save as new version')->form([
            'upgrade_calculation_version[hourlyRate]' => '100',
            'upgrade_calculation_version[managementPercent]' => '10',
            'upgrade_calculation_version[author]' => 'PM',
        ]));
        $this->client->followRedirect();
        self::assertSelectorNotExists('#upgradeTimeline', 'no support cycles for WordPress');
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
        $this->entityManager->persist(new Typo3CoreRelease('13.4.20', new \DateTimeImmutable('2025-11-11'), Typo3ReleaseType::Regular, '13.4.20', true, new \DateTimeImmutable('2027-12-31'), false, new \DateTimeImmutable('2030-12-31'), new \DateTimeImmutable('2024-10-15')));
        $this->entityManager->persist(new Typo3CoreRelease('13.0.0', new \DateTimeImmutable('2024-01-30'), Typo3ReleaseType::Regular, '13.4.20', true, new \DateTimeImmutable('2027-12-31'), false, new \DateTimeImmutable('2030-12-31'), new \DateTimeImmutable('2024-10-15')));
        $this->entityManager->persist(new Typo3CoreRelease('14.0.0', new \DateTimeImmutable('2025-11-25'), Typo3ReleaseType::Regular, '14.0.0', true, new \DateTimeImmutable('2029-06-30'), false, new \DateTimeImmutable('2032-06-30')));
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
