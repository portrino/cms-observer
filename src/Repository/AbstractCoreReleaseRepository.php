<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CoreReleaseInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * The core releases of one CMS, split into newer releases of a site's own
 * major line and the latest release of the next major.
 *
 * Filtered and sorted in PHP via version_compare() rather than SQL string
 * comparison: the tables are small (a few hundred rows) and version strings
 * don't sort correctly as plain strings (e.g. "12.4.9" > "12.4.10").
 *
 * @template T of CoreReleaseInterface
 *
 * @extends ServiceEntityRepository<T>
 */
abstract class AbstractCoreReleaseRepository extends ServiceEntityRepository
{
    /**
     * @return T|null
     */
    public function findOneByVersion(string $version): ?CoreReleaseInterface
    {
        return $this->findOneBy(['version' => $version]);
    }

    /**
     * Releases newer than $version within the same major line only (e.g. for
     * "13.4.28", other 13.x releases - never 14.x; see findLatestInNewerMajor()
     * for that). Newest first.
     *
     * @return list<T>
     */
    public function findNewerThan(?string $version): array
    {
        if (null === $version) {
            return [];
        }

        $currentMajor = $this->majorOf($version);

        $newer = array_filter(
            $this->findAll(),
            static fn (CoreReleaseInterface $release): bool => $release->getMajor() === $currentMajor
                && version_compare($release->getVersion(), $version, '>'),
        );

        return $this->newestFirst($newer);
    }

    /**
     * The latest release of the next major line above $version, if one
     * exists - e.g. for "13.4.28", the latest 14.x release. Used to hint "a
     * newer major is available" without listing every 14.x release in the
     * same-major "newer versions" list.
     *
     * @return T|null
     */
    public function findLatestInNewerMajor(?string $version): ?CoreReleaseInterface
    {
        if (null === $version) {
            return null;
        }

        $currentMajor = $this->majorOf($version);

        $newerMajorReleases = array_filter(
            $this->findAll(),
            fn (CoreReleaseInterface $release): bool => $release->getMajor() > $currentMajor && $this->countsForNewerMajor($release),
        );

        if ([] === $newerMajorReleases) {
            return null;
        }

        $nextMajor = min(array_map(static fn (CoreReleaseInterface $r): int => $r->getMajor(), $newerMajorReleases));

        return $this->newestFirst(array_filter($newerMajorReleases, static fn (CoreReleaseInterface $r): bool => $r->getMajor() === $nextMajor))[0];
    }

    /**
     * Whether a release may stand for a newer major, e.g. not a development
     * release.
     *
     * @param T $release
     */
    protected function countsForNewerMajor(CoreReleaseInterface $release): bool
    {
        return true;
    }

    /**
     * @param array<T> $releases
     *
     * @return list<T>
     */
    private function newestFirst(array $releases): array
    {
        usort($releases, static fn (CoreReleaseInterface $a, CoreReleaseInterface $b): int => version_compare($b->getVersion(), $a->getVersion()));

        return $releases;
    }

    private function majorOf(string $version): int
    {
        return (int) explode('.', $version)[0];
    }
}
