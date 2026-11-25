<?php

declare(strict_types=1);

namespace App\Repository\Wordpress;

use App\Entity\Wordpress\CoreRelease;
use App\Repository\AbstractCoreReleaseRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * WordPress's stable-check feed gives insecure/outdated/latest directly per
 * version, so there's no separate "is this major line still maintained"
 * concept to track.
 *
 * @extends AbstractCoreReleaseRepository<CoreRelease>
 */
class CoreReleaseRepository extends AbstractCoreReleaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoreRelease::class);
    }
}
