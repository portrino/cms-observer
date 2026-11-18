<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SitePackage;
use App\Repository\Typo3TerExtensionVersionRepository;

/**
 * Applies cached TER data (Typo3TerExtensionVersion) and a fresh batch
 * "latest version" lookup to a set of SitePackage rows. Callers are
 * responsible for only passing TYPO3 extension packages (e.g. via
 * SitePackageRepository::findByPackageType(PackageType::Typo3CmsExtension)) -
 * see SitePackageWordpressEvaluator for WordPress plugins, which use a
 * different upstream (WordPress.org, not TER).
 */
class SitePackageTerEvaluator
{
    public function __construct(
        private readonly Typo3TerExtensionVersionRepository $versionRepository,
        private readonly TerExtensionApiClient $apiClient,
        private readonly SecurityNotifier $securityNotifier,
    ) {
    }

    /**
     * @param iterable<SitePackage> $packages
     */
    public function evaluateAll(iterable $packages): void
    {
        $packages = is_array($packages) ? $packages : iterator_to_array($packages);

        $keys = [];
        foreach ($packages as $package) {
            $keys[$package->getName()] = true;
        }
        $latestVersions = $this->apiClient->getLatestVersions(array_keys($keys));

        foreach ($packages as $package) {
            $wasInsecure = $package->isInsecure();

            $cached = $this->versionRepository->findOneByExtensionKeyAndVersion($package->getName(), $package->getVersion());
            $insecure = $cached?->isInsecure();

            $latest = $latestVersions[$package->getName()] ?? null;
            $outdated = null === $latest ? null : version_compare($latest, $package->getVersion(), '>');

            $package->setVersionStatus($insecure, $outdated, true === $outdated ? $latest : null);

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
