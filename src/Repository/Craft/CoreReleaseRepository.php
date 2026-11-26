<?php

declare(strict_types=1);

namespace App\Repository\Craft;

use App\Entity\Craft\CoreRelease;
use App\Repository\AbstractCoreReleaseRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractCoreReleaseRepository<CoreRelease>
 */
class CoreReleaseRepository extends AbstractCoreReleaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoreRelease::class);
    }
}
