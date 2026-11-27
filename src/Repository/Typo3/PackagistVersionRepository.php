<?php

declare(strict_types=1);

namespace App\Repository\Typo3;

use App\Entity\Typo3\PackagistVersion;
use App\Repository\AbstractPackagistVersionRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractPackagistVersionRepository<PackagistVersion>
 */
class PackagistVersionRepository extends AbstractPackagistVersionRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PackagistVersion::class);
    }
}
