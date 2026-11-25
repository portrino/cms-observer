<?php

declare(strict_types=1);

namespace App\Repository\Wordpress;

use App\Entity\Wordpress\ThemeInfo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ThemeInfo>
 */
class ThemeInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ThemeInfo::class);
    }

    public function findOneBySlug(string $slug): ?ThemeInfo
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
