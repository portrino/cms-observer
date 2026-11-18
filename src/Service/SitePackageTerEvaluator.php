<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SitePackage;
use App\Repository\Typo3TerExtensionVersionRepository;

/**
 * Applies cached TER data (Typo3TerExtensionVersion) to a set of SitePackage
 * rows: insecure from the exact version, outdated only by versions that run on
 * the site's TYPO3 (PackageUpdateResolver). Callers are
 * responsible for only passing TYPO3 extension packages (e.g. via
 * SitePackageRepository::findByPackageType(PackageType::Typo3CmsExtension)) -
 * see SitePackageWordpressEvaluator for WordPress plugins, which use a
 * different upstream (WordPress.org, not TER).
 */
class SitePackageTerEvaluator
{
    public function __construct(
        private readonly Typo3TerExtensionVersionRepository $versionRepository,
        private readonly PackageUpdateResolver $updateResolver,
        private readonly SecurityNotifier $securityNotifier,
    ) {
    }

    /**
     * @param iterable<SitePackage> $packages
     */
    public function evaluateAll(iterable $packages): void
    {
        foreach ($packages as $package) {
            $wasInsecure = $package->isInsecure();

            $cached = $this->versionRepository->findOneByExtensionKeyAndVersion($package->getName(), $package->getVersion());
            $insecure = $cached?->isInsecure();

            $package->setVersionStatus($insecure, null, null);
            $package->applyUpdate($this->updateResolver->forTypo3Extension($package->getName(), $package->getVersion(), $package->getSite()->getCurrentCoreVersion()));

            if (true === $insecure && true !== $wasInsecure) {
                $this->securityNotifier->addFinding(
                    $package->getSite(),
                    sprintf('Extension %s %s', $package->getName(), $package->getVersion()),
                    'TER marked this exact version insecure (review_state).',
                );
            }
        }
    }
}
