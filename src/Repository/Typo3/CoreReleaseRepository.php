<?php

declare(strict_types=1);

namespace App\Repository\Typo3;

use App\Entity\CoreReleaseInterface;
use App\Entity\Typo3\CoreRelease;
use App\Entity\Typo3\ReleaseType;
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

    /**
     * Every TYPO3 major with a release, ascending.
     *
     * @return list<int>
     */
    public function findMajors(): array
    {
        $majors = array_unique(array_map(static fn (CoreRelease $release): int => $release->getMajor(), $this->findAll()));
        sort($majors);

        return $majors;
    }

    /**
     * A development release doesn't stand for the next major.
     *
     * @param CoreRelease $release
     */
    protected function countsForNewerMajor(CoreReleaseInterface $release): bool
    {
        return ReleaseType::Development !== $release->getType();
    }
}
