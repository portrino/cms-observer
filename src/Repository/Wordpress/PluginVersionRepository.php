<?php

declare(strict_types=1);

namespace App\Repository\Wordpress;

use App\Entity\Wordpress\PluginVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PluginVersion>
 */
class PluginVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PluginVersion::class);
    }

    public function findOneBySlugAndVersion(string $slug, string $version): ?PluginVersion
    {
        return $this->findOneBy(['slug' => $slug, 'version' => $version]);
    }

    /**
     * Cached versions of one plugin newer than $version, newest first -
     * mirrors Typo3\TerExtensionVersionRepository::findNewerThan(). Sorted in
     * PHP via version_compare() since version strings don't sort correctly
     * as plain strings.
     *
     * @return PluginVersion[]
     */
    public function findNewerThan(string $slug, string $version): array
    {
        $newer = array_filter(
            $this->findBy(['slug' => $slug]),
            static fn (PluginVersion $v): bool => version_compare($v->getVersion(), $version, '>'),
        );

        usort($newer, static fn (PluginVersion $a, PluginVersion $b): int => version_compare($b->getVersion(), $a->getVersion()));

        return $newer;
    }
}
