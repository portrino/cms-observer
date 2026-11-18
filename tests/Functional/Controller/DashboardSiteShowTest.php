<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CmsType;
use App\Entity\Organization;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\SiteReport;
use App\Entity\Typo3TerExtensionVersion;
use App\Entity\WordpressPluginVersion;
use App\Service\SitePackageTerEvaluator;
use App\Service\SitePackageWordpressEvaluator;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardSiteShowTest extends WebTestCase
{
    use CreatesUserTrait;

    public function testShowsContextClientVersionDomainsAndRejectedReports(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('site-context@example.test', 'Site Context', 'password'));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $site = new Site('Context Site', CmsType::Typo3, 'https://context.test', 'hash-context');
        $site->recordReport('13.4.0', '8.3.0', '10.11.0');
        $site->recordRejectedReport(['staging.context.test']);
        $entityManager->persist($site);
        $entityManager->persist(new SiteReport($site, '13.4.0', '8.3.0', '10.11.0', [
            'clientVersion' => '1.1.0',
            'applicationContext' => 'Production/Live',
            'domains' => ['context.test', 'www.context.test'],
        ]));
        $entityManager->flush();

        $client->request('GET', '/projects/'.$site->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-warning', 'Rejected report from staging.context.test');
        $systemInformation = $client->getCrawler()->filter('table')->first()->text();
        self::assertStringContainsString('Production/Live', $systemInformation);
        self::assertStringContainsString('1.1.0', $systemInformation);
        self::assertStringContainsString('context.test, www.context.test', $systemInformation);
    }

    public function testLinksTheOrganization(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('site-org@example.test', 'Site Org', 'password'));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $organization = new Organization('Example Org');
        $site = new Site('Org Site', CmsType::Typo3, 'https://org-site.test', 'hash-org', organization: $organization);
        $entityManager->persist($organization);
        $entityManager->persist($site);
        $entityManager->flush();

        $client->request('GET', '/projects/'.$site->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/organizations/'.$organization->getId().'"]:contains("Example Org")');
    }

    public function testSaysWhenTheLastReportHadNoDomains(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('site-nodomains@example.test', 'Site No Domains', 'password'));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $site = new Site('No Domains Site', CmsType::Typo3, 'https://nodomains.test', 'hash-nodomains');
        $site->recordReport('13.4.0', '8.3.0', '10.11.0');
        $entityManager->persist($site);
        $entityManager->persist(new SiteReport($site, '13.4.0', '8.3.0', '10.11.0', ['clientVersion' => '1.0.0']));
        $entityManager->flush();

        $client->request('GET', '/projects/'.$site->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'not reported, so not checked against the site URL');
        self::assertSelectorNotExists('.alert-warning');
    }

    public function testWordpressPluginVersionsNeedingANewerWordpressAreHidden(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('site-show@example.test', 'Site Show', 'password'));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $site = new Site('WP Compat Site', CmsType::WordPress, 'https://wp-compat.test', 'hash-compat');
        $site->recordReport('6.5.2', '8.3.0', '10.11.0');
        $entityManager->persist($site);
        $entityManager->persist($package = new SitePackage($site, 'compat-plugin', 'Compat Plugin', '1.0.0', true));
        $entityManager->persist(new WordpressPluginVersion('compat-plugin', '1.1.0', null, '6.4'));
        $entityManager->persist(new WordpressPluginVersion('compat-plugin', '1.2.0', null, null));
        $entityManager->persist(new WordpressPluginVersion('compat-plugin', '2.0.0', null, '6.6'));
        $entityManager->flush();
        static::getContainer()->get(SitePackageWordpressEvaluator::class)->evaluate($package);
        $entityManager->flush();

        $client->request('GET', '/projects/'.$site->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.version-number:contains("1.1.0")');
        // unknown "requires": shown, it may well run
        self::assertSelectorExists('.version-number:contains("1.2.0")');
        self::assertSelectorNotExists('.version-number:contains("2.0.0")');
        self::assertSelectorTextContains('#extensionsAccordion .accordion-button', 'Outdated (1.2.0 available)');
        self::assertSelectorTextContains('#extensionsAccordion .accordion-button', 'WordPress 6.6 compatible version (2.0.0)');
    }

    /**
     * The newest extension version for the site's TYPO3 is installed, newer ones need TYPO3 14:
     * not "Outdated" on the project page nor the packages page, but a hint at the TYPO3 14 version.
     */
    public function testAnUpdateForANewerCoreIsNotOutdated(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('site-newer-core@example.test', 'Site Newer Core', 'password'));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $site = new Site('Newer Core Site', CmsType::Typo3, 'https://newer-core.test', 'hash-newer-core');
        $site->recordReport('13.4.20', '8.3.0', '10.11.0');
        $entityManager->persist($site);
        $entityManager->persist($package = new SitePackage($site, 'newer_core_ext', 'Newer Core Ext', '12.4.0', true));
        foreach ([['12.4.0', '12,13'], ['13.0.0', '14'], ['13.1.0', '14']] as [$version, $majors]) {
            $entityManager->persist(new Typo3TerExtensionVersion('newer_core_ext', $version, 'Newer Core Ext', null, null, 'stable', $majors, null, false));
        }
        $entityManager->flush();
        static::getContainer()->get(SitePackageTerEvaluator::class)->evaluateAll([$package]);
        $entityManager->flush();

        foreach (['/projects/'.$site->getId(), '/packages?q=newer_core_ext'] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', 'TYPO3 14 compatible version (13.1.0)');
            self::assertSelectorTextNotContains('body', 'Outdated');
        }

        // the project list counts the same: no compatible update
        $client->request('GET', '/?q=Newer+Core+Site');
        self::assertSelectorExists('#projects-results .badge-count.text-bg-success');
    }
}
