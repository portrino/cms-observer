<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\UpgradeItem;
use App\Entity\Site;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeSection;
use App\Entity\WordpressCoreRelease;
use App\Repository\SitePackageRepository;
use App\Repository\WordpressCoreReleaseRepository;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Lists the packages with their installed version only.
 * shortcut: every package is "check manually"; judge by "Requires at least" / "Tested up to" once WordPress calculations are used.
 */
#[AsTaggedItem(index: 'wordpress')]
final class WordpressUpgradeCompatibilityResolver implements UpgradeCompatibilityResolver
{
    public function __construct(
        private readonly SitePackageRepository $packageRepository,
        private readonly WordpressCoreReleaseRepository $coreReleaseRepository,
    ) {
    }

    public function targetMajors(Site $site): array
    {
        return UpgradeTargets::above(
            $site->getCurrentCoreVersion(),
            array_map(static fn (WordpressCoreRelease $release): int => $release->getMajor(), $this->coreReleaseRepository->findAll()),
        );
    }

    public function packageItems(Site $site, int $targetMajor): array
    {
        $items = [];
        foreach ($this->packageRepository->findBy(['site' => $site], ['name' => 'ASC']) as $package) {
            $items[] = new UpgradeItem(
                UpgradeSection::Packages, $package->getName(), $package->getName(), $package->getPackageType(), $package->getVersion(), null, UpgradeItemStatus::Manual,
            );
        }

        return $items;
    }
}
