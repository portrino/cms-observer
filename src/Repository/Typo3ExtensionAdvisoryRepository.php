<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Typo3ExtensionAdvisory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Typo3ExtensionAdvisory>
 */
class Typo3ExtensionAdvisoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Typo3ExtensionAdvisory::class);
    }

    public function findOneByAdvisoryId(string $advisoryId): ?Typo3ExtensionAdvisory
    {
        return $this->findOneBy(['advisoryId' => $advisoryId]);
    }

    /**
     * @return Typo3ExtensionAdvisory[]
     */
    public function findByExtensionKey(string $extensionKey): array
    {
        return $this->findBy(['extensionKey' => $extensionKey]);
    }
}
