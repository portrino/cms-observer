<?php

declare(strict_types=1);

namespace App\Repository\Typo3;

use App\Entity\Typo3\TerExtensionVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TerExtensionVersion>
 */
class TerExtensionVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TerExtensionVersion::class);
    }

    public function findOneByExtensionKeyAndVersion(string $extensionKey, string $version): ?TerExtensionVersion
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
     * @return TerExtensionVersion[]
     */
    public function findNewerThan(string $extensionKey, string $version): array
    {
        $newer = array_filter(
            $this->findBy(['extensionKey' => $extensionKey]),
            static fn (TerExtensionVersion $v): bool => version_compare($v->getVersion(), $version, '>'),
        );

        usort($newer, static fn (TerExtensionVersion $a, TerExtensionVersion $b): int => version_compare($b->getVersion(), $a->getVersion()));

        return $newer;
    }

    /**
     * Cached versions of one extension newer than $version AND compatible
     * with $coreMajor (the site's current TYPO3 core major) - a version that
     * only supports a newer core major than the site is running isn't
     * something the site could actually install, so it's excluded here.
     *
     * @return TerExtensionVersion[]
     */
    public function findNewerCompatibleWith(string $extensionKey, string $version, int $coreMajor): array
    {
        $newer = array_filter(
            $this->findBy(['extensionKey' => $extensionKey]),
            static fn (TerExtensionVersion $v): bool => version_compare($v->getVersion(), $version, '>')
                && in_array($coreMajor, $v->getTypo3MajorVersions(), true),
        );

        usort($newer, static fn (TerExtensionVersion $a, TerExtensionVersion $b): int => version_compare($b->getVersion(), $a->getVersion()));

        return $newer;
    }
}
