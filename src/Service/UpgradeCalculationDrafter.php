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
            ...array_map(static fn (string $label): UpgradeItem => new UpgradeItem(UpgradeSection::Positions, $label), self::POSITIONS),
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

    private function resolver(Site $site): UpgradeCompatibilityResolver
    {
        return $this->resolvers->get($site->getType()->value);
    }
}
