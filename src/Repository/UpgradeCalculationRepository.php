<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UpgradeCalculation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UpgradeCalculation>
 */
class UpgradeCalculationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UpgradeCalculation::class);
    }
}
