<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Entity\SitePackage;
use App\Entity\Typo3\ExtensionAdvisory;
use App\Repository\Typo3\ExtensionAdvisoryRepository;
use App\Service\SecurityNotifier;
use App\Utility\Typo3\ExtensionUtility;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Cross-references a site's reported extension version against synced
 * Typo3\ExtensionAdvisory records to find a matching known vulnerability.
 * TYPO3 extension packages only - see evaluateAll().
 */
class SitePackageAdvisoryEvaluator
{
    public function __construct(
        private readonly ExtensionAdvisoryRepository $advisoryRepository,
        private readonly SecurityNotifier $securityNotifier,
    ) {
    }

    public function evaluate(SitePackage $package): void
    {
        $previousAdvisory = $package->getMatchedAdvisory();
        $matched = array_find(
            $this->advisoryRepository->findByExtensionKey($package->getName()),
            fn (ExtensionAdvisory $advisory): bool => true === ExtensionUtility::matchesAffectedVersions($advisory->getAffectedVersionsRaw(), $package->getVersion()),
        );

        $package->setMatchedAdvisory($matched);

        if (null !== $matched && $matched !== $previousAdvisory) {
            $this->notify($package, $matched);
        }
    }

    private function notify(SitePackage $package, ExtensionAdvisory $advisory): void
    {
        $this->securityNotifier->addFinding(
            $package->getSite(),
            new TranslatableMessage('event.subject.extension', ['name' => $package->getName(), 'version' => $package->getVersion()]),
            new TranslatableMessage('event.detail.advisory', [
                'id' => $advisory->getAdvisoryId(),
                'severity' => $advisory->getSeverity() ?? 'unknown',
                'url' => $advisory->getUrl(),
            ]),
        );
    }

    /**
     * Re-evaluates every given package. Called after syncing fresh advisory
     * data, since a newly-published advisory can affect an already-reported
     * version without that site pushing a new report.
     *
     * Callers are responsible for only passing TYPO3 extension packages (e.g.
     * via SitePackageRepository::findByPackageType(PackageType::Typo3CmsExtension))
     * - a WordPress plugin can never match a Typo3\ExtensionAdvisory, so
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
