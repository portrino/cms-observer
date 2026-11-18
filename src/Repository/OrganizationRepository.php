<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organization>
 */
class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    /**
     * @return Organization[]
     */
    public function findAllOrderedByName(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    /**
     * The organization with that name (ignoring case), or a new one, persisted but not flushed.
     */
    public function findOrCreateByName(string $name): Organization
    {
        $existing = $this->createQueryBuilder('o')
            ->where('LOWER(o.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if ($existing instanceof Organization) {
            return $existing;
        }

        $organization = new Organization($name);
        $this->getEntityManager()->persist($organization);

        return $organization;
    }
}
