<?php

declare(strict_types=1);

namespace App\Repository\Craft;

use App\Entity\Craft\PluginVersion;
use App\Repository\AbstractPackagistVersionRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractPackagistVersionRepository<PluginVersion>
 */
class PluginVersionRepository extends AbstractPackagistVersionRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PluginVersion::class);
    }
}
