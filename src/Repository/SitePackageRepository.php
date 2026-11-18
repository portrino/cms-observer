<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CmsType;
use App\Entity\PackageType;
use App\Entity\Site;
use App\Entity\SitePackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SitePackage>
 */
class SitePackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SitePackage::class);
    }

    public function findOneBySiteAndName(Site $site, string $name): ?SitePackage
    {
        return $this->findOneBy(['site' => $site, 'name' => $name]);
    }

    /**
     * @return SitePackage[]
     */
    public function findByPackageType(PackageType $packageType): array
    {
        return $this->findBy(['packageType' => $packageType]);
    }

    /**
     * @return SitePackage[]
     */
    public function search(?string $query, ?PackageType $packageType = null): array
    {
        $qb = $this->createQueryBuilder('se')
            ->addSelect('s')
            ->join('se.site', 's')
            ->orderBy('se.name', 'ASC')
            ->addOrderBy('s.name', 'ASC');

        if (null !== $query && '' !== $query) {
            $qb->andWhere('se.name LIKE :query OR se.title LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (null !== $packageType) {
            $qb->andWhere('se.packageType = :packageType')
                ->setParameter('packageType', $packageType);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Aggregates each site's packages into a single insecure/outdated
     * tri-state (true if any package is, false if all known are not, null
     * if none are resolved yet) - for the dashboard list's compact status
     * column, which can't show one badge per package.
     *
     * @return array<int, array{insecure: ?bool, outdated: ?bool}> keyed by site id
     */
    public function getAggregateStatusBySite(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT site_id, MAX(insecure) AS any_insecure, MAX(outdated) AS any_outdated FROM site_package GROUP BY site_id',
        );

        $status = [];
        foreach ($rows as $row) {
            $status[(int) $row['site_id']] = [
                'insecure' => null === $row['any_insecure'] ? null : (bool) $row['any_insecure'],
                'outdated' => null === $row['any_outdated'] ? null : (bool) $row['any_outdated'],
            ];
        }

        return $status;
    }

    /**
     * Per-site extension/plugin counts for the sites table listing: how many
     * are installed, and how many have a newer version available that's
     * compatible with the site's current core version. TYPO3 sites use TER's
     * per-version compatibility list (falls back to "any newer version" when
     * the core major is unknown); WordPress sites use WordpressPluginInfo's
     * requires/tested range (falls back the same way when either the site's
     * core version or the plugin's declared range is unknown - never treat
     * missing data as "incompatible").
     *
     * @param iterable<Site> $sites
     *
     * @return array<int, array{total: int, updatable: int, insecureCount: int}>
     */
    public function getExtensionSummaryBySite(
        iterable $sites,
        Typo3TerExtensionVersionRepository $terVersionRepository,
        WordpressPluginInfoRepository $wordpressPluginInfoRepository,
    ): array {
        $summary = [];

        foreach ($sites as $site) {
            $packages = $this->findBy(['site' => $site]);
            $coreVersion = $site->getCurrentCoreVersion();

            $updatable = 0;
            $insecureCount = 0;
            foreach ($packages as $package) {
                if (CmsType::WordPress === $site->getType()) {
                    if ($this->isWordpressPluginUpdatable($package, $coreVersion, $wordpressPluginInfoRepository)) {
                        ++$updatable;
                    }
                } else {
                    $coreMajor = null !== $coreVersion ? (int) explode('.', $coreVersion)[0] : null;
                    $newer = null !== $coreMajor
                        ? $terVersionRepository->findNewerCompatibleWith($package->getName(), $package->getVersion(), $coreMajor)
                        : $terVersionRepository->findNewerThan($package->getName(), $package->getVersion());

                    if ([] !== $newer) {
                        ++$updatable;
                    }
                }

                if (true === $package->isInsecure()) {
                    ++$insecureCount;
                }
            }

            $summary[(int) $site->getId()] = [
                'total' => count($packages),
                'updatable' => $updatable,
                'insecureCount' => $insecureCount,
            ];
        }

        return $summary;
    }

    private function isWordpressPluginUpdatable(SitePackage $package, ?string $coreVersion, WordpressPluginInfoRepository $wordpressPluginInfoRepository): bool
    {
        $info = $wordpressPluginInfoRepository->findOneBySlug($package->getName());
        if (null === $info || version_compare($info->getLatestVersion(), $package->getVersion(), '<=')) {
            return false;
        }

        if (null !== $coreVersion && false === $info->isCompatibleWith($coreVersion)) {
            return false;
        }

        return true;
    }
}
