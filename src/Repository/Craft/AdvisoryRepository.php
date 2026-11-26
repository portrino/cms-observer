<?php

declare(strict_types=1);

namespace App\Repository\Craft;

use App\Entity\Craft\Advisory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Advisory>
 */
class AdvisoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Advisory::class);
    }

    /**
     * @return list<Advisory>
     */
    public function findByPackageName(string $packageName): array
    {
        return $this->findBy(['packageName' => $packageName]);
    }
}
