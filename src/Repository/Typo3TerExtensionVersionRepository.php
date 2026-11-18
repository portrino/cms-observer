<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Typo3TerExtensionVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Typo3TerExtensionVersion>
 */
class Typo3TerExtensionVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Typo3TerExtensionVersion::class);
    }

    public function findOneByExtensionKeyAndVersion(string $extensionKey, string $version): ?Typo3TerExtensionVersion
    {
        return $this->findOneBy(['extensionKey' => $extensionKey, 'version' => $version]);
    }

    /**
     * Cached versions of one extension newer than $version, newest first.
     * Unfiltered by TYPO3 compatibility - only used as a fallback when the
     * site's core major isn't known (see findNewerCompatibleWith()
     * otherwise). Filtered/sorted in PHP via version_compare() since version
     * strings don't sort correctly as plain strings.
     *
     * @return Typo3TerExtensionVersion[]
     */
    public function findNewerThan(string $extensionKey, string $version): array
    {
        $newer = array_filter(
            $this->findBy(['extensionKey' => $extensionKey]),
            static fn (Typo3TerExtensionVersion $v): bool => version_compare($v->getVersion(), $version, '>'),
        );

        usort($newer, static fn (Typo3TerExtensionVersion $a, Typo3TerExtensionVersion $b): int => version_compare($b->getVersion(), $a->getVersion()));

        return $newer;
    }

    /**
     * Cached versions of one extension newer than $version AND compatible
     * with $coreMajor (the site's current TYPO3 core major) - a version that
     * only supports a newer core major than the site is running isn't
     * something the site could actually install, so it's excluded here (see
     * hasNewerVersionForNewerMajor() for surfacing that separately).
     *
     * @return Typo3TerExtensionVersion[]
     */
    public function findNewerCompatibleWith(string $extensionKey, string $version, int $coreMajor): array
    {
        $newer = array_filter(
            $this->findBy(['extensionKey' => $extensionKey]),
            static fn (Typo3TerExtensionVersion $v): bool => version_compare($v->getVersion(), $version, '>')
                && in_array($coreMajor, $v->getTypo3MajorVersions(), true),
        );

        usort($newer, static fn (Typo3TerExtensionVersion $a, Typo3TerExtensionVersion $b): int => version_compare($b->getVersion(), $a->getVersion()));

        return $newer;
    }

    /**
     * Whether a newer version of this extension exists that supports some
     * TYPO3 major greater than $coreMajor but not $coreMajor itself - i.e.
     * an upgrade only reachable by also upgrading core to a newer major.
     */
    public function hasNewerVersionForNewerMajor(string $extensionKey, string $version, int $coreMajor): bool
    {
        foreach ($this->findBy(['extensionKey' => $extensionKey]) as $v) {
            if (!version_compare($v->getVersion(), $version, '>')) {
                continue;
            }

            $majors = $v->getTypo3MajorVersions();
            if ([] === $majors || in_array($coreMajor, $majors, true)) {
                continue;
            }

            if (max($majors) > $coreMajor) {
                return true;
            }
        }

        return false;
    }
}
