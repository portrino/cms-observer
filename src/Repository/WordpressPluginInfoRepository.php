<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WordpressPluginInfo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WordpressPluginInfo>
 */
class WordpressPluginInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WordpressPluginInfo::class);
    }

    public function findOneBySlug(string $slug): ?WordpressPluginInfo
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
