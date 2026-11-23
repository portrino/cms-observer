<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AgencySettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgencySettings>
 */
class AgencySettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgencySettings::class);
    }

    /** The settings row, or empty settings until they are first saved */
    public function get(): AgencySettings
    {
        return $this->findOneBy([], ['id' => 'ASC']) ?? new AgencySettings();
    }
}
