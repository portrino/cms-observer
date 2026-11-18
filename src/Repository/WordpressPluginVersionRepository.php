<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WordpressPluginVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WordpressPluginVersion>
 */
class WordpressPluginVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WordpressPluginVersion::class);
    }

    public function findOneBySlugAndVersion(string $slug, string $version): ?WordpressPluginVersion
    {
        return $this->findOneBy(['slug' => $slug, 'version' => $version]);
    }

    /**
     * Cached versions of one plugin newer than $version, newest first -
     * mirrors Typo3TerExtensionVersionRepository::findNewerThan(). Sorted in
     * PHP via version_compare() since version strings don't sort correctly
     * as plain strings.
     *
     * @return WordpressPluginVersion[]
     */
    public function findNewerThan(string $slug, string $version): array
    {
        $newer = array_filter(
            $this->findBy(['slug' => $slug]),
            static fn (WordpressPluginVersion $v): bool => version_compare($v->getVersion(), $version, '>'),
        );

        usort($newer, static fn (WordpressPluginVersion $a, WordpressPluginVersion $b): int => version_compare($b->getVersion(), $a->getVersion()));

        return $newer;
    }
}
