<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Typo3;

use App\Entity\Typo3\CoreRelease;
use App\Repository\Typo3\CoreReleaseRepository;
use App\Service\Typo3\CoreReleaseSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class CoreReleaseSynchronizerTest extends TestCase
{
    public function testStoresTheSupportPhasesOfTheMajor(): void
    {
        $http = new MockHttpClient(static fn (string $method, string $url): JsonMockResponse => str_contains($url, '/major/')
            ? new JsonMockResponse([['version' => 13.0, 'release_date' => '2024-10-15T00:00:00+02:00', 'maintained_until' => '2027-12-31T00:00:00+01:00', 'elts_until' => '2030-12-31T00:00:00+01:00']])
            : new JsonMockResponse([['version' => '13.4.20', 'date' => '2025-11-11T10:00:00+01:00', 'type' => 'regular']]));

        $persisted = [];
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $repository = self::createStub(CoreReleaseRepository::class);

        new CoreReleaseSynchronizer($http, $entityManager, $repository)->sync();

        self::assertCount(1, $persisted);
        $release = $persisted[0];
        self::assertInstanceOf(CoreRelease::class, $release);
        self::assertSame('2027-12-31', $release->getMaintainedUntil()?->format('Y-m-d'));
        self::assertSame('2030-12-31', $release->getEltsUntil()?->format('Y-m-d'));
        self::assertSame('2024-10-15', $release->getLtsReleaseDate()?->format('Y-m-d'));
    }

    public function testReleasesThatOnlyCameWithEltsAreSecureUntilEltsEnds(): void
    {
        $http = new MockHttpClient(static fn (string $method, string $url): JsonMockResponse => str_contains($url, '/major/')
            ? new JsonMockResponse([
                ['version' => 12.0, 'release_date' => '2023-04-25T00:00:00+02:00', 'maintained_until' => '2026-04-30T00:00:00+02:00', 'elts_until' => '2099-04-30T00:00:00+02:00'],
                ['version' => 9.0, 'release_date' => '2018-10-02T00:00:00+02:00', 'maintained_until' => '2021-09-30T00:00:00+02:00', 'elts_until' => '2024-09-30T00:00:00+02:00'],
            ])
            : new JsonMockResponse([
                ['version' => '12.4.40', 'date' => '2026-04-14T10:00:00+02:00', 'type' => 'regular'], // public, before maintained_until
                ['version' => '12.4.51', 'date' => '2026-10-22T10:00:00+02:00', 'type' => 'regular'],
                ['version' => '12.4.52', 'date' => '2026-11-17T10:00:00+01:00', 'type' => 'security'],
                ['version' => '9.5.48', 'date' => '2024-06-11T10:00:00+02:00', 'type' => 'security'], // ELTS has ended
            ]));

        $persisted = [];
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        new CoreReleaseSynchronizer($http, $entityManager, self::createStub(CoreReleaseRepository::class))->sync();

        $insecure = [];
        foreach ($persisted as $release) {
            self::assertInstanceOf(CoreRelease::class, $release);
            $insecure[$release->getVersion()] = $release->isInsecure();
        }
        self::assertSame(['12.4.40' => true, '12.4.51' => true, '12.4.52' => false, '9.5.48' => true], $insecure);
    }
}
