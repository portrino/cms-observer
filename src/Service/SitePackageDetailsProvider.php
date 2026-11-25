<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CmsType;
use App\Entity\PackageType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\Wordpress\PluginVulnerability;
use App\Repository\Typo3\TerExtensionMetaRepository;
use App\Repository\Typo3\TerExtensionVersionRepository;
use App\Repository\Wordpress\PluginInfoRepository;
use App\Repository\Wordpress\PluginVersionRepository;
use App\Repository\Wordpress\PluginVulnerabilityRepository;

/**
 * The per-package details of a project's detail page: newer versions with
 * their security and compatibility state, and the TER or WordPress.org metadata.
 */
class SitePackageDetailsProvider
{
    public function __construct(
        private readonly TerExtensionVersionRepository $terVersionRepository,
        private readonly TerExtensionMetaRepository $terMetaRepository,
        private readonly PluginInfoRepository $wordpressPluginInfoRepository,
        private readonly PluginVersionRepository $wordpressPluginVersionRepository,
        private readonly PluginVulnerabilityRepository $wordpressVulnerabilityRepository,
    ) {
    }

    /**
     * @param list<SitePackage> $packages
     *
     * @return array<int, array<string, mixed>> keyed by package id
     */
    public function forSite(Site $site, array $packages): array
    {
        $coreVersion = $site->getCurrentCoreVersion();

        $details = [];
        foreach ($packages as $package) {
            $details[(int) $package->getId()] = match ($site->getType()) {
                CmsType::Typo3 => $this->typo3Details($package, $coreVersion),
                CmsType::WordPress => $this->wordpressDetails($package, $coreVersion),
            };
        }

        return $details;
    }

    /**
     * @return array<string, mixed>
     */
    private function wordpressDetails(SitePackage $package, ?string $coreVersion): array
    {
        if (PackageType::WordpressTheme === $package->getPackageType()) {
            // no theme versions or info synced, and the plugin data of the same slug isn't this theme's
            return ['wordpressPluginInfo' => null, 'newerVersions' => [], 'versionStates' => [], 'latestVersion' => null];
        }

        $vulnerabilities = array_filter(
            $this->wordpressVulnerabilityRepository->findBySlug($package->getName(), $package->getPackageType()),
            static fn (PluginVulnerability $v): bool => !$v->isInformational(),
        );

        // like the TER versions: only what installs on the site's WordPress, unknown "requires" stays
        $newerVersions = [];
        $versionStates = [];
        foreach ($this->wordpressPluginVersionRepository->findNewerThan($package->getName(), $package->getVersion()) as $version) {
            $compatible = null !== $coreVersion && null !== $version->getRequiresWpVersion()
                ? version_compare($coreVersion, $version->getRequiresWpVersion(), '>=')
                : null;
            if (false === $compatible) {
                continue;
            }

            $newerVersions[] = $version;
            $versionStates[$version->getVersion()] = [
                'insecure' => [] !== array_filter($vulnerabilities, static fn (PluginVulnerability $v): bool => $v->affects($version->getVersion())),
                'compatible' => $compatible,
            ];
        }

        return [
            'wordpressPluginInfo' => $this->wordpressPluginInfoRepository->findOneBySlug($package->getName()),
            'newerVersions' => $newerVersions,
            'versionStates' => $versionStates,
            // newest first: the first version without a known vulnerability
            'latestVersion' => array_find_key($versionStates, static fn (array $state): bool => !$state['insecure']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function typo3Details(SitePackage $package, ?string $coreVersion): array
    {
        $cached = $this->terVersionRepository->findOneByExtensionKeyAndVersion($package->getName(), $package->getVersion());
        $meta = $this->terMetaRepository->findOneByExtensionKey($package->getName());

        return [
            'description' => $cached?->getDescription(),
            'packagistUrl' => $meta?->getPackagistUrl(),
            'repositoryUrl' => $meta?->getRepositoryUrl(),
            'manualUrl' => $meta?->getManualUrl(),
            'newerVersions' => null !== $coreVersion
                ? $this->terVersionRepository->findNewerCompatibleWith($package->getName(), $package->getVersion(), (int) explode('.', $coreVersion)[0])
                : $this->terVersionRepository->findNewerThan($package->getName(), $package->getVersion()),
        ];
    }
}
