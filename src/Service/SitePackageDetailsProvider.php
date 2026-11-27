<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Typo3\ExtensionVersion;
use App\Entity\CmsType;
use App\Entity\PackageType;
use App\Entity\Site;
use App\Entity\SitePackage;
use App\Entity\Wordpress\PluginVulnerability;
use App\Repository\Craft\AdvisoryRepository as CraftAdvisoryRepository;
use App\Repository\Craft\PluginVersionRepository as CraftPluginVersionRepository;
use App\Repository\Typo3\TerExtensionMetaRepository;
use App\Repository\Typo3\TerExtensionVersionRepository;
use App\Repository\Wordpress\PluginInfoRepository;
use App\Repository\Wordpress\PluginVersionRepository;
use App\Repository\Wordpress\PluginVulnerabilityRepository;
use App\Service\Typo3\ExtensionVersionCatalog;
use App\Utility\Craft\AdvisoryUtility;
use App\Utility\Craft\PluginVersionUtility;

/**
 * The per-package details of a project's detail page: newer versions with
 * their security and compatibility state, and the TER or WordPress.org metadata (Craft: Packagist).
 */
class SitePackageDetailsProvider
{
    public function __construct(
        private readonly TerExtensionVersionRepository $terVersionRepository,
        private readonly TerExtensionMetaRepository $terMetaRepository,
        private readonly ExtensionVersionCatalog $typo3VersionCatalog,
        private readonly PluginInfoRepository $wordpressPluginInfoRepository,
        private readonly PluginVersionRepository $wordpressPluginVersionRepository,
        private readonly PluginVulnerabilityRepository $wordpressVulnerabilityRepository,
        private readonly CraftPluginVersionRepository $craftPluginVersionRepository,
        private readonly CraftAdvisoryRepository $craftAdvisoryRepository,
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
                CmsType::Craft => $this->craftDetails($package, $coreVersion),
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
            return ['wordpressPluginInfo' => null] + self::versionTable([], []);
        }

        $vulnerabilities = array_filter(
            $this->wordpressVulnerabilityRepository->findBySlug($package->getName(), $package->getPackageType()),
            static fn (PluginVulnerability $v): bool => !$v->isInformational(),
        );

        // every newer version, also those for a newer WordPress; an unknown "requires" counts as installable
        $newerVersions = array_values($this->wordpressPluginVersionRepository->findNewerThan($package->getName(), $package->getVersion()));
        $versionStates = [];
        foreach ($newerVersions as $version) {
            $versionStates[$version->getVersion()] = [
                'insecure' => [] !== array_filter($vulnerabilities, static fn (PluginVulnerability $v): bool => $v->affects($version->getVersion())),
                'compatible' => null !== $coreVersion && null !== $version->getRequiresWpVersion()
                    ? version_compare($coreVersion, $version->getRequiresWpVersion(), '>=')
                    : null,
            ];
        }

        return ['wordpressPluginInfo' => $this->wordpressPluginInfoRepository->findOneBySlug($package->getName())]
            + self::versionTable($newerVersions, $versionStates);
    }

    /**
     * Like WordPress: every newer version, also those for a newer Craft CMS.
     *
     * @return array<string, mixed>
     */
    private function craftDetails(SitePackage $package, ?string $coreVersion): array
    {
        $composerName = $package->getComposerName();
        $newerVersions = null !== $composerName ? $this->craftPluginVersionRepository->findNewerThan($composerName, $package->getVersion()) ?? [] : [];
        $advisories = null !== $composerName ? $this->craftAdvisoryRepository->findByPackageName($composerName) : [];

        $versionStates = [];
        foreach ($newerVersions as $version) {
            $versionStates[$version->getVersion()] = [
                'insecure' => null !== AdvisoryUtility::mostSevere($advisories, $version->getVersion()),
                'compatible' => null !== $coreVersion && null !== $version->getCraftConstraint() ? PluginVersionUtility::installsOnCraft($version, $coreVersion) : null,
            ];
        }

        return self::versionTable($newerVersions, $versionStates);
    }

    /**
     * @return array<string, mixed>
     */
    private function typo3Details(SitePackage $package, ?string $coreVersion): array
    {
        $cached = $this->terVersionRepository->findOneByExtensionKeyAndVersion($package->getName(), $package->getVersion());
        $meta = $this->terMetaRepository->findOneByExtensionKey($package->getName());

        // from the TER and Packagist, also those for a newer TYPO3
        $newerVersions = array_values(array_filter(
            $this->typo3VersionCatalog->versionsOf($package->getName(), $package->getComposerName()),
            static fn (ExtensionVersion $v): bool => version_compare($v->version, $package->getVersion(), '>'),
        ));
        $versionStates = [];
        foreach ($newerVersions as $version) {
            $versionStates[$version->version] = [
                'insecure' => $version->insecure,
                'compatible' => null === $coreVersion || [] === $version->typo3MajorVersions ? null : $version->supports((int) explode('.', $coreVersion)[0]),
            ];
        }

        return [
            'description' => $cached?->getDescription(),
            'packagistUrl' => $meta?->getPackagistUrl() ?? (null !== $package->getComposerName() ? 'https://packagist.org/packages/'.$package->getComposerName() : null),
            'repositoryUrl' => $meta?->getRepositoryUrl(),
            'manualUrl' => $meta?->getManualUrl(),
        ] + self::versionTable($newerVersions, $versionStates);
    }

    /**
     * The version table of the package details: every newer version, newest first, each with
     * whether it's insecure and whether it installs on the site's core (null when unknown).
     *
     * @param list<object>                                            $newerVersions
     * @param array<string, array{insecure: bool, compatible: ?bool}> $versionStates by version
     *
     * @return array{newerVersions: list<object>, versionStates: array<string, array{insecure: bool, compatible: ?bool}>, latestVersion: ?string, updateForCore: bool}
     */
    private static function versionTable(array $newerVersions, array $versionStates): array
    {
        $installable = array_filter($versionStates, static fn (array $state): bool => false !== $state['compatible']);

        return [
            'newerVersions' => $newerVersions,
            'versionStates' => $versionStates,
            // newest first: the first that installs on the site's core without a known vulnerability
            'latestVersion' => array_find_key($installable, static fn (array $state): bool => !$state['insecure']),
            // false: newer versions exist, but all need a newer core
            'updateForCore' => [] !== $installable,
        ];
    }
}
