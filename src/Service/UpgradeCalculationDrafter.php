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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The rows a new calculation starts with: the usual positions without hours,
 * and the site's packages as its CMS's resolver judges them.
 */
final readonly class UpgradeCalculationDrafter
{
    private const array POSITIONS = [
        'calculation.default.core',
        'calculation.default.php',
        UpgradeSection::Packages,
        UpgradeSection::OwnPackages,
        'calculation.default.testing',
    ];

    /**
     * @param ServiceLocator<UpgradeCompatibilityResolver> $resolvers
     */
    public function __construct(
        #[AutowireLocator(UpgradeCompatibilityResolver::class)]
        private ServiceLocator $resolvers,
        private TranslatorInterface $translator,
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
            // the labels are saved, so in the language of whoever starts the calculation
            ...array_map(fn (string|UpgradeSection $position): UpgradeItem => $position instanceof UpgradeSection
                ? UpgradeItem::sumOf($position)
                : new UpgradeItem(UpgradeSection::Positions, $this->translator->trans($position)), self::POSITIONS),
            ...$this->packageItems($site, $targetMajor),
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
        return array_map(static fn (UpgradeItem $item): UpgradeItem => $item->withPreset(), $this->resolver($site)->packageItems($site, $targetMajor));
    }

    /** Whether the site's CMS has a resolver, i.e. calculations can be drafted for it */
    public function supports(Site $site): bool
    {
        return $this->resolvers->has($site->getType()->value);
    }

    private function resolver(Site $site): UpgradeCompatibilityResolver
    {
        return $this->resolvers->get($site->getType()->value);
    }
}
