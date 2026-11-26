<?php

declare(strict_types=1);

namespace App\Repository\Craft;

use App\Entity\Craft\PluginVersion;
use App\Service\Craft\AdvisoryMatcher;
use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
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

    /**
     * @return list<PluginVersion>
     */
    public function findByPackageName(string $packageName): array
    {
        return $this->findBy(['packageName' => $packageName]);
    }

    /**
     * Versions newer than the installed one, newest first.
     *
     * @return list<PluginVersion>|null null for an installed branch like "dev-main", which can't be compared
     */
    public function findNewerThan(string $packageName, string $installedVersion): ?array
    {
        if (!AdvisoryMatcher::canRate($installedVersion)) {
            return null; // a branch or not a version at all: whether it's outdated is unknown
        }
        $installed = new VersionParser()->normalize($installedVersion);

        $newer = array_filter(
            $this->findByPackageName($packageName),
            static fn (PluginVersion $v): bool => Comparator::greaterThan(new VersionParser()->normalize($v->getVersion()), $installed),
        );
        usort($newer, static fn (PluginVersion $a, PluginVersion $b): int => version_compare(new VersionParser()->normalize($b->getVersion()), new VersionParser()->normalize($a->getVersion())));

        return $newer;
    }
}
