<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WordpressThemeInfo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WordpressThemeInfo>
 */
class WordpressThemeInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WordpressThemeInfo::class);
    }

    public function findOneBySlug(string $slug): ?WordpressThemeInfo
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
