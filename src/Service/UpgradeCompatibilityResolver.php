<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\UpgradeItem;
use App\Dto\UpgradeSupportPhase;
use App\Entity\Site;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The CMS-specific part of an upgrade calculation: one implementation per
 * CmsType, indexed by its value with #[AsTaggedItem].
 */
#[AutoconfigureTag]
interface UpgradeCompatibilityResolver
{
    /**
     * Core majors above the site's current one that a calculation can target, ascending.
     *
     * @return list<int>
     */
    public function targetMajors(Site $site): array;

    /**
     * One row per package of the site, in the Packages or OwnPackages section.
     *
     * @return list<UpgradeItem>
     */
    public function packageItems(Site $site, int $targetMajor): array;

    /**
     * Support phases of the majors from the current one to the target, oldest first;
     * none for a CMS without support cycles.
     *
     * @return list<UpgradeSupportPhase>
     */
    public function supportPhases(int $fromMajor, int $targetMajor): array;
}
