<?php

declare(strict_types=1);

namespace App\Service\Craft;

use App\Entity\SitePackage;
use App\Repository\Craft\AdvisoryRepository;
use App\Service\PackageUpdate;
use App\Service\PackageUpdateResolver;
use App\Service\SecurityNotifier;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Rates a Craft plugin by its Composer name: insecure when a Packagist advisory affects the
 * installed version, outdated when Packagist has a newer version that installs on the site's
 * Craft. A plugin without Composer name, unknown to Packagist or installed from a branch stays
 * unknown, and so does "insecure" until the advisories were synced once.
 */
class SitePackageEvaluator
{
    private bool $advisoriesSynced = false;

    public function __construct(
        private readonly PackageUpdateResolver $updateResolver,
        private readonly AdvisoryRepository $advisoryRepository,
        private readonly SecurityNotifier $securityNotifier,
    ) {
    }

    public function evaluate(SitePackage $package): void
    {
        $composerName = $package->getComposerName();
        $wasInsecure = $package->isInsecure();

        $advisory = null !== $composerName ? AdvisoryMatcher::mostSevere($this->advisoryRepository->findByPackageName($composerName), $package->getVersion()) : null;
        $insecure = null !== $composerName && AdvisoryMatcher::canRate($package->getVersion()) && $this->areAdvisoriesSynced() ? null !== $advisory : null;

        $package->setVersionStatus($insecure, null, null);
        $package->setMatchedCraftAdvisory($advisory);
        $package->applyUpdate(null !== $composerName
            ? $this->updateResolver->forCraftPlugin($composerName, $package->getVersion(), $package->getSite()->getCurrentCoreVersion())
            : new PackageUpdate());

        if (null !== $advisory && true !== $wasInsecure) {
            $this->securityNotifier->addFinding(
                $package->getSite(),
                new TranslatableMessage('event.subject.plugin', ['name' => $package->getName(), 'version' => $package->getVersion()]),
                new TranslatableMessage('event.detail.advisory', [
                    'id' => $advisory->getCve() ?? $advisory->getAdvisoryId(),
                    'severity' => $advisory->getSeverity() ?? 'unknown',
                    'url' => $advisory->getLink(),
                ]),
            );
        }
    }

    /**
     * @param iterable<SitePackage> $packages
     */
    public function evaluateAll(iterable $packages): void
    {
        foreach ($packages as $package) {
            $this->evaluate($package);
        }
    }

    // craftcms/cms always has advisories: once synced, the table is never empty again
    private function areAdvisoriesSynced(): bool
    {
        return $this->advisoriesSynced = $this->advisoriesSynced || [] !== $this->advisoryRepository->findBy([], limit: 1);
    }
}
