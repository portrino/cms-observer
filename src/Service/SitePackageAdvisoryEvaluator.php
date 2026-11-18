<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SitePackage;
use App\Entity\Typo3ExtensionAdvisory;
use App\Repository\Typo3ExtensionAdvisoryRepository;

/**
 * Cross-references a site's reported extension version against synced
 * Typo3ExtensionAdvisory records to find a matching known vulnerability.
 * TYPO3 extension packages only - see evaluateAll().
 */
class SitePackageAdvisoryEvaluator
{
    public function __construct(
        private readonly Typo3ExtensionAdvisoryRepository $advisoryRepository,
        private readonly SecurityNotifier $securityNotifier,
    ) {
    }

    public function evaluate(SitePackage $package): void
    {
        $previousAdvisory = $package->getMatchedAdvisory();
        $matched = array_find(
            $this->advisoryRepository->findByExtensionKey($package->getName()),
            fn (Typo3ExtensionAdvisory $advisory): bool => true === AffectedVersionRangeMatcher::matches($advisory->getAffectedVersionsRaw(), $package->getVersion()),
        );

        $package->setMatchedAdvisory($matched);

        if (null !== $matched && $matched !== $previousAdvisory) {
            $this->notify($package, $matched);
        }
    }

    private function notify(SitePackage $package, Typo3ExtensionAdvisory $advisory): void
    {
        $this->securityNotifier->addFinding(
            $package->getSite(),
            sprintf('Extension %s %s', $package->getName(), $package->getVersion()),
            sprintf(
                'Matches %s (%s): %s',
                $advisory->getAdvisoryId(),
                $advisory->getSeverity() ?? 'unknown severity',
                $advisory->getUrl(),
            ),
        );
    }

    /**
     * Re-evaluates every given package. Called after syncing fresh advisory
     * data, since a newly-published advisory can affect an already-reported
     * version without that site pushing a new report.
     *
     * Callers are responsible for only passing TYPO3 extension packages (e.g.
     * via SitePackageRepository::findByPackageType(PackageType::Typo3CmsExtension))
     * - a WordPress plugin can never match a Typo3ExtensionAdvisory, so
     * evaluating it here would just waste a query.
     *
     * @param iterable<SitePackage> $packages
     */
    public function evaluateAll(iterable $packages): void
    {
        foreach ($packages as $package) {
            $this->evaluate($package);
        }
    }
}
