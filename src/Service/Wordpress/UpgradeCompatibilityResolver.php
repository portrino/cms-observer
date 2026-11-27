<?php

declare(strict_types=1);

namespace App\Service\Wordpress;

use App\Dto\UpgradeItem;
use App\Entity\Site;
use App\Entity\UpgradeItemStatus;
use App\Entity\UpgradeSection;
use App\Entity\Wordpress\CoreRelease;
use App\Repository\SitePackageRepository;
use App\Repository\Wordpress\CoreReleaseRepository;
use App\Service\UpgradeCompatibilityResolver as CompatibilityResolver;
use App\Utility\VersionUtility;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Lists the packages with their installed version only.
 * shortcut: every package is "check manually"; judge by "Requires at least" / "Tested up to" once WordPress calculations are used.
 */
#[AsTaggedItem(index: 'wordpress')]
final readonly class UpgradeCompatibilityResolver implements CompatibilityResolver
{
    public function __construct(
        private SitePackageRepository $packageRepository,
        private CoreReleaseRepository $coreReleaseRepository,
    ) {
    }

    public function targetMajors(Site $site): array
    {
        return VersionUtility::majorsAbove(
            $site->getCurrentCoreVersion(),
            array_map(static fn (CoreRelease $release): int => $release->getMajor(), $this->coreReleaseRepository->findAll()),
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

    // WordPress has no support cycles per major, only the latest version gets updates
    public function supportPhases(int $fromMajor, int $targetMajor): array
    {
        return [];
    }
}
