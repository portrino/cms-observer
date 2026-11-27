<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AbstractPackagistVersion;
use App\Utility\ComposerUtility;
use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * The Packagist versions of one CMS's packages, compared as Composer does.
 *
 * @template T of AbstractPackagistVersion
 *
 * @extends ServiceEntityRepository<T>
 */
abstract class AbstractPackagistVersionRepository extends ServiceEntityRepository
{
    /**
     * @return list<T>
     */
    public function findByPackageName(string $packageName): array
    {
        return $this->findBy(['packageName' => $packageName]);
    }

    /**
     * Versions newer than the installed one, newest first.
     *
     * @return list<T>|null null for an installed branch like "dev-main", which can't be compared
     */
    public function findNewerThan(string $packageName, string $installedVersion): ?array
    {
        if (!ComposerUtility::canRate($installedVersion)) {
            return null; // a branch or not a version at all: whether it's outdated is unknown
        }
        $installed = new VersionParser()->normalize($installedVersion);

        $newer = array_filter(
            $this->findByPackageName($packageName),
            static fn (AbstractPackagistVersion $v): bool => Comparator::greaterThan(new VersionParser()->normalize($v->getVersion()), $installed),
        );
        usort($newer, static fn (AbstractPackagistVersion $a, AbstractPackagistVersion $b): int => version_compare(new VersionParser()->normalize($b->getVersion()), new VersionParser()->normalize($a->getVersion())));

        return $newer;
    }
}
