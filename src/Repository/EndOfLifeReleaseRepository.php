<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EndOfLifeRelease;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EndOfLifeRelease>
 */
class EndOfLifeReleaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EndOfLifeRelease::class);
    }

    public function findOneByProductAndCycle(string $product, string $cycle): ?EndOfLifeRelease
    {
        return $this->findOneBy(['product' => $product, 'cycle' => $cycle]);
    }
}
