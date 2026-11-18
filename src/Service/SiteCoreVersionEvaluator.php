<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CmsType;
use App\Entity\Site;
use App\Repository\Typo3CoreReleaseRepository;
use App\Repository\WordpressCoreReleaseRepository;

/**
 * Cross-references a site's reported core version against the synced
 * core-release data (Typo3CoreRelease for TYPO3, WordpressCoreRelease for
 * WordPress) to determine whether it's insecure and/or outdated. Both
 * release entities expose the same isInsecure()/isLatest()/getLatestVersion()
 * shape, so the actual evaluation logic below is CMS-agnostic once the
 * right repository has resolved the release.
 */
class SiteCoreVersionEvaluator
{
    public function __construct(
        private readonly Typo3CoreReleaseRepository $typo3ReleaseRepository,
        private readonly WordpressCoreReleaseRepository $wordpressReleaseRepository,
        private readonly SecurityNotifier $securityNotifier,
    ) {
    }

    public function evaluate(Site $site): void
    {
        $version = $site->getCurrentCoreVersion();
        if (null === $version) {
            return;
        }

        $release = match ($site->getType()) {
            CmsType::Typo3 => $this->typo3ReleaseRepository->findOneByVersion($version),
            CmsType::WordPress => $this->wordpressReleaseRepository->findOneByVersion($version),
        };

        $wasInsecure = $site->isCoreInsecure();

        if (null === $release) {
            // Unknown version - e.g. release feed not synced yet, or a dev/pre-release build.
            $site->setCoreStatus(null, null, null);

            return;
        }

        $site->setCoreStatus(
            $release->isInsecure(),
            !$release->isLatest(),
            $release->isLatest() ? null : $release->getLatestVersion(),
        );

        if ($release->isInsecure() && true !== $wasInsecure) {
            $this->securityNotifier->addFinding(
                $site,
                sprintf('%s core %s', $site->getType()->label(), $version),
                'Core version is now insecure (unmaintained or superseded by a security release).',
            );
        }
    }

    /**
     * Re-evaluates every given site. Called after syncing fresh release data,
     * since a newly-published security release can change an already-reported
     * version's status without that site pushing a new report.
     *
     * @param iterable<Site> $sites
     */
    public function evaluateAll(iterable $sites): void
    {
        foreach ($sites as $site) {
            $this->evaluate($site);
        }
    }
}
