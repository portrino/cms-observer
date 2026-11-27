<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Craft;

use App\Entity\EndOfLifeRelease;
use App\Repository\Craft\AdvisoryRepository;
use App\Repository\Craft\CoreReleaseRepository;
use App\Repository\EndOfLifeReleaseRepository;
use App\Repository\SitePackageRepository;
use App\Service\Craft\AdvisorySynchronizer;
use App\Service\Craft\CoreReleaseSynchronizer;
use App\Service\PackagistApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class CoreReleaseSynchronizerTest extends KernelTestCase
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $advisories = [];

    public function testRatesEachReleaseByItsMajorAndTheAdvisories(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new EndOfLifeRelease('craft-cms', '4', true, '4.18.9', 'https://endoflife.date/craft-cms'));
        $entityManager->persist(new EndOfLifeRelease('craft-cms', '5', false, '5.12.4', 'https://endoflife.date/craft-cms'));
        $entityManager->flush();
        $this->advisories = ['craftcms/cms' => [self::advisory('PKSA-rce', '>=5.0.0-RC1,<5.10.5')]];

        [$advisorySynchronizer, $coreSynchronizer] = $this->synchronizers();
        $advisorySynchronizer->sync();
        self::assertSame(4, $coreSynchronizer->sync());

        $releases = static::getContainer()->get(CoreReleaseRepository::class);
        self::assertTrue($releases->findOneByVersion('5.10.4')?->isInsecure(), 'affected by the advisory');
        self::assertFalse($releases->findOneByVersion('5.10.6')?->isInsecure());
        self::assertTrue($releases->findOneByVersion('4.18.9')?->isInsecure(), 'major past end of life');
        self::assertSame('5.12.4', $releases->findOneByVersion('5.10.6')->getLatestVersion());
        self::assertSame('4.18.9', $releases->findOneByVersion('4.18.9')->getLatestVersion(), 'latest of its own major');
        self::assertNull($releases->findOneByVersion('6.0.0-beta.2'), 'pre-releases are left out');
    }

    public function testStoresNoReleasesBeforeTheSupportCyclesAndAdvisoriesAreSynced(): void
    {
        $this->advisories = ['craftcms/cms' => [self::advisory('PKSA-rce', '>=5.0.0-RC1,<5.10.5')]];
        [$advisorySynchronizer, $coreSynchronizer] = $this->synchronizers();
        $advisorySynchronizer->sync();

        // without the craft-cms cycles 4.18.9 would count as secure: the core stays unknown instead
        self::assertSame(0, $coreSynchronizer->sync());
        self::assertSame([], static::getContainer()->get(CoreReleaseRepository::class)->findAll());
    }

    /**
     * @return array<string, mixed>
     */
    private static function advisory(string $id, string $affected): array
    {
        return ['advisoryId' => $id, 'title' => 'Title of '.$id, 'link' => 'https://github.com/advisories/'.$id, 'affectedVersions' => $affected, 'cve' => null, 'severity' => 'high'];
    }

    /**
     * @return array{AdvisorySynchronizer, CoreReleaseSynchronizer}
     */
    private function synchronizers(): array
    {
        $versions = ['packages' => ['craftcms/cms' => [
            ['version' => '6.0.0-beta.2', 'time' => '2026-11-20T21:57:53+00:00', 'require' => ['php' => '^8.4']],
            ['version' => '5.12.4', 'time' => '2026-11-24T10:00:00+00:00', 'require' => ['php' => '^8.2']],
            ['version' => '5.10.6'],
            ['version' => '5.10.4'],
            ['version' => '4.18.9', 'require' => ['php' => '^8.0.2']],
        ]], 'minified' => 'composer/2.0'];
        $http = new MockHttpClient(fn (string $method, string $url): JsonMockResponse => str_contains($url, '/p2/')
            ? new JsonMockResponse($versions)
            : new JsonMockResponse(['advisories' => $this->advisories]));

        $container = static::getContainer();
        $api = new PackagistApiClient($http, 'https://observer.test', '');
        $entityManager = $container->get(EntityManagerInterface::class);
        $core = new CoreReleaseSynchronizer($api, $container->get(CoreReleaseRepository::class), $container->get(AdvisoryRepository::class), $container->get(EndOfLifeReleaseRepository::class), $entityManager);

        return [new AdvisorySynchronizer($api, $container->get(AdvisoryRepository::class), $container->get(SitePackageRepository::class), $core, $entityManager), $core];
    }
}
