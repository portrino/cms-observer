<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Tests\Functional\CreatesUserTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardPackagesTest extends WebTestCase
{
    use CreatesUserTrait;

    public function testFilteringByPackageType(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->loginUser($this->createUser('packages-filter@example.test', 'Packages Filter', 'password'));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $typo3Site = new Site('TYPO3 Site', CmsType::Typo3, 'https://typo3.test', 'hash-1');
        $wpSite = new Site('WP Site', CmsType::WordPress, 'https://wp.test', 'hash-2');
        $entityManager->persist($typo3Site);
        $entityManager->persist($wpSite);
        $entityManager->persist(new SitePackage($typo3Site, 'news', 'News', '14.1.1', true));
        $entityManager->persist(new SitePackage($wpSite, 'akismet', 'Akismet', '5.3', true));
        $entityManager->flush();

        $client->request('GET', '/packages?type=wordpress-plugin');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('code:contains("akismet")');
        // the number of projects it is installed in, like the organization list
        self::assertSelectorTextSame('#packagesAccordion .accordion-button .badge-count[title="Projects"]', '1');
        self::assertSelectorNotExists('code:contains("news")');
    }
}
