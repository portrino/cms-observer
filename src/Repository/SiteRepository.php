<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CmsType;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Site>
 */
class SiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    public function findOneByTokenHash(string $tokenHash): ?Site
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * @return Site[]
     */
    /**
     * @return Site[] ordered by name; $query matches name, URL and organization name
     */
    public function search(?string $query, ?CmsType $type, ?bool $slaActive): array
    {
        $builder = $this->createQueryBuilder('s')
            ->leftJoin('s.organization', 'o')
            ->orderBy('s.name', 'ASC');

        if (null !== $query) {
            $builder->andWhere('s.name LIKE :query OR s.url LIKE :query OR o.name LIKE :query')
                ->setParameter('query', '%'.addcslashes($query, '%_\\').'%');
        }
        if (null !== $type) {
            $builder->andWhere('s.type = :type')->setParameter('type', $type);
        }
        if (null !== $slaActive) {
            $builder->andWhere('s.slaActive = :sla')->setParameter('sla', $slaActive);
        }

        return $builder->getQuery()->getResult();
    }
}
