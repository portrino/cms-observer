<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Site;
use App\Entity\SiteEvent;
use App\Entity\SiteEventType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteEvent>
 */
class SiteEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteEvent::class);
    }

    /**
     * Newest first; $query matches subject, old/new value and detail.
     *
     * @return Paginator<SiteEvent>
     */
    public function search(Site $site, ?string $query, ?SiteEventType $type, int $page, int $perPage): Paginator
    {
        $builder = $this->createQueryBuilder('e')
            ->where('e.site = :site')
            ->setParameter('site', $site)
            // events of one report share a timestamp, the id keeps their order stable
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if (null !== $query) {
            $builder->andWhere('e.subject LIKE :query OR e.oldValue LIKE :query OR e.newValue LIKE :query OR e.detail LIKE :query')
                ->setParameter('query', '%'.addcslashes($query, '%_\\').'%');
        }
        if (null !== $type) {
            $builder->andWhere('e.type = :type')->setParameter('type', $type);
        }

        return new Paginator($builder->getQuery(), fetchJoinCollection: false);
    }
}
