<?php

declare(strict_types=1);

namespace App\Service\Typo3;

use App\Entity\SitePackage;
use App\Repository\Typo3\TerExtensionVersionRepository;
use App\Service\PackageUpdateResolver;
use App\Service\SecurityNotifier;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Applies cached TER data (Typo3\TerExtensionVersion) to a set of SitePackage
 * rows: insecure from the exact version, outdated only by versions that run on
 * the site's TYPO3, from the TER and Packagist (PackageUpdateResolver). Callers are
 * responsible for only passing TYPO3 extension packages (e.g. via
 * SitePackageRepository::findByPackageType(PackageType::Typo3CmsExtension)) -
 * see Wordpress\SitePackageEvaluator for WordPress plugins, which use a
 * different upstream (WordPress.org, not TER).
 */
class SitePackageTerEvaluator
{
    public function __construct(
        private readonly TerExtensionVersionRepository $versionRepository,
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
            $package->applyUpdate($this->updateResolver->forTypo3Extension($package->getName(), $package->getComposerName(), $package->getVersion(), $package->getSite()->getCurrentCoreVersion()));

            if (true === $insecure && true !== $wasInsecure) {
                $this->securityNotifier->addFinding(
                    $package->getSite(),
                    new TranslatableMessage('event.subject.extension', ['name' => $package->getName(), 'version' => $package->getVersion()]),
                    new TranslatableMessage('event.detail.ter_insecure'),
                );
            }
        }
    }
}
