<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\UpgradeItem;
use App\Dto\UpgradeSupportPhase;
use App\Entity\Site;
use App\Entity\UpgradeCalculation;
use App\Entity\UpgradeSection;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The rows a new calculation starts with: the usual positions without hours,
 * and the site's packages as its CMS's resolver judges them.
 */
final class UpgradeCalculationDrafter
{
    private const array POSITIONS = [
        'Core upgrade',
        'PHP version upgrade',
        UpgradeSection::Packages,
        UpgradeSection::OwnPackages,
        'Testing, code reviews and deployments',
    ];

    /**
     * @param ServiceLocator<UpgradeCompatibilityResolver> $resolvers
     */
    public function __construct(
        #[AutowireLocator(UpgradeCompatibilityResolver::class)]
        private readonly ServiceLocator $resolvers,
    ) {
    }

    /**
     * @return list<int>
     */
    public function targetMajors(Site $site): array
    {
        return $this->resolver($site)->targetMajors($site);
    }

    /**
     * @return list<UpgradeItem>
     */
    public function draft(Site $site, int $targetMajor): array
    {
        return [
            // the order of the agency's Excel template; package sections as their sums
            ...array_map(static fn (string|UpgradeSection $position): UpgradeItem => $position instanceof UpgradeSection
                ? UpgradeItem::sumOf($position)
                : new UpgradeItem(UpgradeSection::Positions, $position), self::POSITIONS),
            ...$this->resolver($site)->packageItems($site, $targetMajor),
        ];
    }

    /**
     * @return list<UpgradeSupportPhase>
     */
    public function supportPhases(UpgradeCalculation $calculation): array
    {
        return $this->resolver($calculation->getSite())->supportPhases($calculation->getFromMajor(), $calculation->getTargetMajor());
    }

    /**
     * The site's packages as of its latest report.
     *
     * @return list<UpgradeItem>
     */
    public function packageItems(Site $site, int $targetMajor): array
    {
        return $this->resolver($site)->packageItems($site, $targetMajor);
    }

    private function resolver(Site $site): UpgradeCompatibilityResolver
    {
        return $this->resolvers->get($site->getType()->value);
    }
}
