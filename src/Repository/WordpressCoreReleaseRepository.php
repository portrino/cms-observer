<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WordpressCoreRelease;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Mirrors Typo3CoreReleaseRepository's shape for WordPress - see there for
 * the rationale behind the same-major/newer-major split. WordPress's
 * stable-check feed gives insecure/outdated/latest directly per version, so
 * there's no separate "is this major line still maintained" concept to track.
 *
 * @extends ServiceEntityRepository<WordpressCoreRelease>
 */
class WordpressCoreReleaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WordpressCoreRelease::class);
    }

    public function findOneByVersion(string $version): ?WordpressCoreRelease
    {
        return $this->findOneBy(['version' => $version]);
    }

    /**
     * @return WordpressCoreRelease[]
     */
    public function findNewerThan(?string $version): array
    {
        if (null === $version) {
            return [];
        }

        $currentMajor = $this->majorOf($version);

        $newer = array_filter(
            $this->findAll(),
            static fn (WordpressCoreRelease $release): bool => $release->getMajor() === $currentMajor
                && version_compare($release->getVersion(), $version, '>'),
        );

        usort($newer, static fn (WordpressCoreRelease $a, WordpressCoreRelease $b): int => version_compare($b->getVersion(), $a->getVersion()));

        return $newer;
    }

    public function findLatestInNewerMajor(?string $version): ?WordpressCoreRelease
    {
        if (null === $version) {
            return null;
        }

        $currentMajor = $this->majorOf($version);

        $newerMajorReleases = array_filter(
            $this->findAll(),
            static fn (WordpressCoreRelease $release): bool => $release->getMajor() > $currentMajor,
        );

        if ([] === $newerMajorReleases) {
            return null;
        }

        $nextMajor = min(array_map(static fn (WordpressCoreRelease $r): int => $r->getMajor(), $newerMajorReleases));
        $inNextMajor = array_filter($newerMajorReleases, static fn (WordpressCoreRelease $r): bool => $r->getMajor() === $nextMajor);

        usort($inNextMajor, static fn (WordpressCoreRelease $a, WordpressCoreRelease $b): int => version_compare($b->getVersion(), $a->getVersion()));

        return $inNextMajor[0];
    }

    private function majorOf(string $version): int
    {
        return (int) explode('.', $version)[0];
    }
}
