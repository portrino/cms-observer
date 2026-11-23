<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Typo3CoreRelease;
use App\Repository\Typo3CoreReleaseRepository;
use App\Service\Typo3CoreReleaseSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class Typo3CoreReleaseSynchronizerTest extends TestCase
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
        $repository = self::createStub(Typo3CoreReleaseRepository::class);

        new Typo3CoreReleaseSynchronizer($http, $entityManager, $repository)->sync();

        self::assertCount(1, $persisted);
        $release = $persisted[0];
        self::assertInstanceOf(Typo3CoreRelease::class, $release);
        self::assertSame('2027-12-31', $release->getMaintainedUntil()?->format('Y-m-d'));
        self::assertSame('2030-12-31', $release->getEltsUntil()?->format('Y-m-d'));
        self::assertSame('2024-10-15', $release->getLtsReleaseDate()?->format('Y-m-d'));
    }
}
