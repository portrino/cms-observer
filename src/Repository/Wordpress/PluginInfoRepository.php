<?php

declare(strict_types=1);

namespace App\Repository\Wordpress;

use App\Entity\Wordpress\PluginInfo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PluginInfo>
 */
class PluginInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PluginInfo::class);
    }

    public function findOneBySlug(string $slug): ?PluginInfo
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
