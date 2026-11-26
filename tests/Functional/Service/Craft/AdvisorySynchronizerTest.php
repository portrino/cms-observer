<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Craft;

use App\Entity\CmsType;
use App\Entity\Craft\Advisory;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Repository\Craft\AdvisoryRepository;
use App\Repository\Craft\CoreReleaseRepository;
use App\Repository\EndOfLifeReleaseRepository;
use App\Repository\SitePackageRepository;
use App\Service\Craft\AdvisorySynchronizer;
use App\Service\Craft\CoreReleaseSynchronizer;
use App\Service\Craft\PackagistApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class AdvisorySynchronizerTest extends KernelTestCase
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $advisories = [];

    public function testStoresThePluginAdvisoriesAndDropsWithdrawnOnes(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $site = new Site('Craft', CmsType::Craft, 'https://craft.test', 'hash-craft-sync');
        $entityManager->persist($site);
        $entityManager->persist(new SitePackage($site, 'seomatic', 'SEOmatic', '3.4.10', true, 'nystudio107/craft-seomatic'));
        $entityManager->flush();
        $advisorySynchronizer = $this->synchronizer();

        $this->advisories = ['nystudio107/craft-seomatic' => [self::advisory('PKSA-seo-1', '<3.4.11'), self::advisory('PKSA-seo-2', '<3.2.49')]];
        self::assertSame(2, $advisorySynchronizer->sync());

        $this->advisories = ['nystudio107/craft-seomatic' => [self::advisory('PKSA-seo-1', '<3.4.12')]];
        $advisorySynchronizer->sync();

        $stored = static::getContainer()->get(AdvisoryRepository::class)->findByPackageName('nystudio107/craft-seomatic');
        self::assertSame(['PKSA-seo-1'], array_map(static fn (Advisory $a): string => $a->getAdvisoryId(), $stored));
        self::assertSame('<3.4.12', $stored[0]->getAffectedVersions());
    }

    /**
     * @return array<string, mixed>
     */
    private static function advisory(string $id, string $affected): array
    {
        return ['advisoryId' => $id, 'title' => 'Title of '.$id, 'link' => 'https://github.com/advisories/'.$id, 'affectedVersions' => $affected, 'cve' => null, 'severity' => 'high'];
    }

    private function synchronizer(): AdvisorySynchronizer
    {
        $container = static::getContainer();
        $api = new PackagistApiClient(new MockHttpClient(fn (): JsonMockResponse => new JsonMockResponse(['advisories' => $this->advisories])), 'https://observer.test', '');
        $entityManager = $container->get(EntityManagerInterface::class);
        $core = new CoreReleaseSynchronizer($api, $container->get(CoreReleaseRepository::class), $container->get(AdvisoryRepository::class), $container->get(EndOfLifeReleaseRepository::class), $entityManager);

        return new AdvisorySynchronizer($api, $container->get(AdvisoryRepository::class), $container->get(SitePackageRepository::class), $core, $entityManager);
    }
}
