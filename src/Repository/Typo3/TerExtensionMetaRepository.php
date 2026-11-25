<?php

declare(strict_types=1);

namespace App\Repository\Typo3;

use App\Entity\Typo3\TerExtensionMeta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TerExtensionMeta>
 */
class TerExtensionMetaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TerExtensionMeta::class);
    }

    public function findOneByExtensionKey(string $extensionKey): ?TerExtensionMeta
    {
        return $this->findOneBy(['extensionKey' => $extensionKey]);
    }

    /**
     * Every extension key that's already had at least one sync run - lets
     * the synchronizer prioritize never-synced keys over already-known ones.
     * Least recently fetched first, so refreshes rotate through all keys.
     *
     * @return string[]
     */
    public function findAllExtensionKeys(): array
    {
        return array_column(
            $this->createQueryBuilder('m')
                ->select('m.extensionKey')
                ->orderBy('m.fetchedAt', 'ASC')
                ->getQuery()
                ->getScalarResult(),
            'extensionKey',
        );
    }
}
