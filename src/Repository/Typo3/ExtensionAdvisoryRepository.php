<?php

declare(strict_types=1);

namespace App\Repository\Typo3;

use App\Entity\Typo3\ExtensionAdvisory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExtensionAdvisory>
 */
class ExtensionAdvisoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExtensionAdvisory::class);
    }

    public function findOneByAdvisoryId(string $advisoryId): ?ExtensionAdvisory
    {
        return $this->findOneBy(['advisoryId' => $advisoryId]);
    }

    /**
     * @return ExtensionAdvisory[]
     */
    public function findByExtensionKey(string $extensionKey): array
    {
        return $this->findBy(['extensionKey' => $extensionKey]);
    }
}
