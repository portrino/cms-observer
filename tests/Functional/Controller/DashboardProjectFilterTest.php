<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CmsType;
use App\Entity\Organization;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class DashboardProjectFilterTest extends WebTestCase
{
    use CreatesUserTrait;

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function filters(): iterable
    {
        yield 'no filter' => ['', ['Filter Insecure Core', 'Filter Insecure Package', 'Filter Outdated PHP', 'Filter Secure WP']];
        yield 'search by name' => ['q=outdated', ['Filter Outdated PHP']];
        yield 'search by organization' => ['q=Filter+Customer', ['Filter Secure WP']];
        yield 'type' => ['type=wordpress', ['Filter Secure WP']];
        yield 'SLA' => ['sla=with', ['Filter Insecure Core']];
        yield 'without SLA' => ['sla=without', ['Filter Insecure Package', 'Filter Outdated PHP', 'Filter Secure WP']];
        // PHP/DB problems are a separate status, they don't make a project "insecure"
        yield 'insecure' => ['security=insecure', ['Filter Insecure Core', 'Filter Insecure Package']];
        yield 'infrastructure' => ['security=infrastructure', ['Filter Outdated PHP']];
        yield 'no known issues' => ['security=ok', ['Filter Secure WP']];
        yield 'combined' => ['q=filter&type=typo3&security=insecure&sla=without', ['Filter Insecure Package']];
        yield 'empty values mean all' => ['q=&type=&security=&sla=', ['Filter Insecure Core', 'Filter Insecure Package', 'Filter Outdated PHP', 'Filter Secure WP']];
    }

    /**
     * @param list<string> $expectedNames
     */
    #[DataProvider('filters')]
    public function testFiltersTheProjectList(string $queryString, array $expectedNames): void
    {
        $client = $this->createClientWithSites();

        $client->request('GET', '/?'.$queryString);

        self::assertResponseIsSuccessful();
        $names = $client->getCrawler()->filter('#projects-results tbody td:first-child a')->each(static fn (Crawler $link): string => $link->text());
        self::assertSame($expectedNames, array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'Filter '))));
    }

    public function testInvalidFilterValueIsNotFound(): void
    {
        $client = $this->createClientWithSites();

        $client->request('GET', '/?security=bogus');

        self::assertResponseStatusCodeSame(404);
    }

    public function testInfrastructureBadgeExplainsTheWarning(): void
    {
        $client = $this->createClientWithSites();

        $client->request('GET', '/?q=outdated');

        self::assertSelectorExists('#projects-results .status-icon.text-bg-warning[title="Outdated PHP version 8.2.10 (8.2.29 available)"]');
        // the row stays unmarked, only core/package issues color it
        self::assertSelectorNotExists('#projects-results tr.row-insecure');
    }

    public function testLinksTheOrganizationByIcon(): void
    {
        $client = $this->createClientWithSites();

        $crawler = $client->request('GET', '/?q=Filter+Customer');

        $link = $crawler->filter('#projects-results a[aria-label="Organization: Filter Customer"]');
        self::assertCount(1, $link);
        self::assertSame('Filter Customer', $link->attr('title'));

        // an organization's own project list doesn't repeat it
        $client->click($link->link());
        self::assertSelectorTextNotContains('table thead', 'Org');
    }

    private function createClientWithSites(): KernelBrowser
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('filter@example.test', 'Filter', 'password'));
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $insecureCore = new Site('Filter Insecure Core', CmsType::Typo3, 'https://insecure-core.test', 'hash-f1', slaActive: true);
        $insecureCore->recordReport('12.4.0', '8.3.0', '10.11.0');
        $insecureCore->setCoreStatus(true, true, '12.4.40');

        $insecurePackage = new Site('Filter Insecure Package', CmsType::Typo3, 'https://insecure-package.test', 'hash-f2');
        $insecurePackage->recordReport('13.4.0', '8.3.0', '10.11.0');
        $package = new SitePackage($insecurePackage, 'news', 'News', '11.0.0', true);
        $package->setVersionStatus(true, true, '12.0.0');

        $outdatedPhp = new Site('Filter Outdated PHP', CmsType::Typo3, 'https://outdated-php.test', 'hash-f3');
        $outdatedPhp->recordReport('13.4.0', '8.2.10', '10.11.0');
        $outdatedPhp->setPhpStatus(false, true, '8.2.29', null);

        $organization = new Organization('Filter Customer');
        $secureWp = new Site('Filter Secure WP', CmsType::WordPress, 'https://secure-wp.test', 'hash-f4', organization: $organization);
        $secureWp->recordReport('6.8.0', '8.3.0', '10.11.0');
        $secureWp->setPhpStatus(false, false, null, null);

        foreach ([$insecureCore, $insecurePackage, $package, $outdatedPhp, $organization, $secureWp] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return $client;
    }
}
