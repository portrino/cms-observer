<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Typo3CoreRelease;
use App\Entity\Typo3ReleaseType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Typo3CoreRelease>
 */
class Typo3CoreReleaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Typo3CoreRelease::class);
    }

    public function findOneByVersion(string $version): ?Typo3CoreRelease
    {
        return $this->findOneBy(['version' => $version]);
    }

    /**
     * Releases newer than $version within the same major line only (e.g. for
     * "13.4.28", other 13.x releases - never 14.x; see findLatestInNewerMajor()
     * for that). Newest first. Filtered/sorted in PHP via version_compare()
     * rather than SQL string comparison - the table is small (a few hundred
     * rows) and version strings don't sort correctly as plain strings (e.g.
     * "12.4.9" > "12.4.10").
     *
     * @return Typo3CoreRelease[]
     */
    public function findNewerThan(?string $version): array
    {
        if (null === $version) {
            return [];
        }

        $currentMajor = $this->majorOf($version);

        $newer = array_filter(
            $this->findAll(),
            static fn (Typo3CoreRelease $release): bool => $release->getMajor() === $currentMajor
                && version_compare($release->getVersion(), $version, '>'),
        );

        usort($newer, static fn (Typo3CoreRelease $a, Typo3CoreRelease $b): int => version_compare($b->getVersion(), $a->getVersion()));

        return $newer;
    }

    /**
     * The latest real (non-development) release of the next major line above
     * $version, if one exists - e.g. for "13.4.28", the latest 14.x release.
     * Used to hint "a newer major is available" without listing every 14.x
     * release in the same-major "newer versions" list.
     */
    public function findLatestInNewerMajor(?string $version): ?Typo3CoreRelease
    {
        if (null === $version) {
            return null;
        }

        $currentMajor = $this->majorOf($version);

        $newerMajorReleases = array_filter(
            $this->findAll(),
            static fn (Typo3CoreRelease $release): bool => $release->getMajor() > $currentMajor
                && Typo3ReleaseType::Development !== $release->getType(),
        );

        if ([] === $newerMajorReleases) {
            return null;
        }

        $nextMajor = min(array_map(static fn (Typo3CoreRelease $r): int => $r->getMajor(), $newerMajorReleases));
        $inNextMajor = array_filter($newerMajorReleases, static fn (Typo3CoreRelease $r): bool => $r->getMajor() === $nextMajor);

        usort($inNextMajor, static fn (Typo3CoreRelease $a, Typo3CoreRelease $b): int => version_compare($b->getVersion(), $a->getVersion()));

        return $inNextMajor[0];
    }

    private function majorOf(string $version): int
    {
        return (int) explode('.', $version)[0];
    }
}
