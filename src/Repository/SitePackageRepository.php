<?php

declare(strict_types=1);

namespace App\Repository;

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
     * Per site: installed packages, those with an update for the site's core (the stored
     * "outdated", the same rule as the badges, see PackageUpdateResolver) and insecure ones.
     *
     * @param iterable<Site> $sites
     *
     * @return array<int, array{total: int, updatable: int, insecureCount: int}>
     */
    public function getExtensionSummaryBySite(iterable $sites): array
    {
        $summary = [];
        foreach ($sites as $site) {
            $packages = $this->findBy(['site' => $site]);
            $summary[(int) $site->getId()] = [
                'total' => count($packages),
                'updatable' => count(array_filter($packages, static fn (SitePackage $package): bool => true === $package->isOutdated())),
                'insecureCount' => count(array_filter($packages, static fn (SitePackage $package): bool => true === $package->isInsecure())),
            ];
        }

        return $summary;
    }
}
