<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CmsType;
use App\Entity\CoreReleaseInterface;
use App\Entity\Site;
use App\Repository\AbstractCoreReleaseRepository;
use App\Repository\Craft\CoreReleaseRepository as CraftCoreReleaseRepository;
use App\Repository\Typo3\CoreReleaseRepository as Typo3CoreReleaseRepository;
use App\Repository\Wordpress\CoreReleaseRepository as WordpressCoreReleaseRepository;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Cross-references a site's reported core version against the synced
 * core-release data of its CMS to determine whether it's insecure and/or
 * outdated. Every CMS's release implements CoreReleaseInterface, so the
 * evaluation below is CMS-agnostic once the right repository has resolved
 * the release.
 */
class SiteCoreVersionEvaluator
{
    public function __construct(
        private readonly Typo3CoreReleaseRepository $typo3ReleaseRepository,
        private readonly WordpressCoreReleaseRepository $wordpressReleaseRepository,
        private readonly CraftCoreReleaseRepository $craftReleaseRepository,
        private readonly SecurityNotifier $securityNotifier,
    ) {
    }

    public function evaluate(Site $site): void
    {
        $version = $site->getCurrentCoreVersion();
        if (null === $version) {
            return;
        }

        $release = $this->releaseRepository($site->getType())->findOneByVersion($version);

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
                new TranslatableMessage('event.subject.core', ['cms' => $site->getType()->label(), 'version' => $version]),
                new TranslatableMessage('event.detail.core_insecure'),
            );
        } elseif (!$release->isInsecure() && true === $wasInsecure) {
            $this->securityNotifier->recordResolved($site, new TranslatableMessage('event.subject.core', ['cms' => $site->getType()->label(), 'version' => $version]), new TranslatableMessage('event.detail.core_secure'));
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

    /**
     * Major-version-aware core status for a sites table listing: whether a
     * newer release exists within the site's own major line, that release's
     * version, and the latest release of a newer major if one exists. Sites
     * with no reported core version yet get a null/unknown entry, matching
     * the tri-state convention used elsewhere.
     *
     * @param iterable<Site> $sites
     *
     * @return array<int, array{outdated: ?bool, latestInMajor: ?string, newerMajor: ?CoreReleaseInterface}>
     */
    public function majorStatusBySite(iterable $sites): array
    {
        $status = [];

        foreach ($sites as $site) {
            $version = $site->getCurrentCoreVersion();
            $siteId = (int) $site->getId();

            if (null === $version) {
                $status[$siteId] = ['outdated' => null, 'latestInMajor' => null, 'newerMajor' => null];

                continue;
            }

            $newer = $this->newerReleases($site);

            $status[$siteId] = [
                'outdated' => [] !== $newer['inMajor'],
                'latestInMajor' => ($newer['inMajor'][0] ?? null)?->getVersion(),
                'newerMajor' => $newer['newerMajor'],
            ];
        }

        return $status;
    }

    /**
     * Releases newer than the site's core version within its major line
     * (newest first), and the latest release of the next major, both from the
     * release data of the site's own CMS.
     *
     * @return array{inMajor: list<CoreReleaseInterface>, newerMajor: ?CoreReleaseInterface}
     */
    public function newerReleases(Site $site): array
    {
        $version = $site->getCurrentCoreVersion();
        $repository = $this->releaseRepository($site->getType());

        return [
            'inMajor' => $repository->findNewerThan($version),
            'newerMajor' => $repository->findLatestInNewerMajor($version),
        ];
    }

    /**
     * @return AbstractCoreReleaseRepository<covariant CoreReleaseInterface>
     */
    private function releaseRepository(CmsType $type): AbstractCoreReleaseRepository
    {
        return match ($type) {
            CmsType::Typo3 => $this->typo3ReleaseRepository,
            CmsType::WordPress => $this->wordpressReleaseRepository,
            CmsType::Craft => $this->craftReleaseRepository,
        };
    }
}
